"""Import the current per-deck GPT delivery through native inventories and local assets.

Remote asset publication is deliberately a separate operation. Existing nonempty
inventories must match the source exactly; native editor revisions take precedence.
"""
import argparse
import hashlib
import http.cookiejar
import json
import re
import shutil
import urllib.parse
import urllib.request
from pathlib import Path

import combo_source_sync as sync
import import_folder_delivery as legacy

APP = Path(__file__).resolve().parents[1]
ROOT = APP.parent
SOURCE = APP / 'context/gpt/01_INFORMACION_PARA_IMPORTAR'
OUT = ROOT / 'outputs/import-20261117-gpt'
VERSION = '20261117-gpt-delivery'
TARGET = APP / 'assets/editorial' / VERSION
URL = 'https://stationxtulua.com/yugioh/'
SESSION = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
DECKS = {'12': (671, 'Memento', 'tcg_advanced', (41, 15, 14)),
         '13': (677, 'Crystron', 'tcg_advanced', (40, 15, 15)),
         '14': (660, 'Purrely', 'tcg_advanced', (48, 15, 7)),
         '15': (673, 'Salamangreat', 'tcg_advanced', (40, 15, 15)),
         '16': (699, 'Goat Control', 'goat', (40, 14, 0))}


def read(p):
    return json.loads(p.read_text(encoding='utf-8-sig'))


def write(p, value):
    p.parent.mkdir(parents=True, exist_ok=True)
    p.write_text(json.dumps(value, ensure_ascii=False, indent=2) + '\n', encoding='utf8')


def request(params=None, payload=None):
    url = URL + ('?' + urllib.parse.urlencode(params) if params else '')
    req = urllib.request.Request(url, data=json.dumps(payload).encode() if payload else None,
                                 headers={'Content-Type': 'application/json'} if payload else {})
    with SESSION.open(req, timeout=60) as response:
        return response.read().decode('utf8')


def snapshot():
    raw = request()
    marker = re.search(r'window\.DML_DATA\s*=\s*', raw)
    if not marker:
        raise ValueError('Public snapshot missing')
    return json.JSONDecoder().raw_decode(raw[marker.end():])[0]


def inventories():
    result = {}
    for ref, (deck, name, fmt, expected) in DECKS.items():
        file = next(SOURCE.glob(f'decks/DECK_NUEVO_{ref}_*/*.md'))
        raw = file.read_text(encoding='utf8')
        if int(ref) < 14:
            cards = legacy.new_inventory(file)
        else:
            inventory = raw[raw.index('## A03'):raw.index('## A04')]
            cards, zone = [], None
            for line in inventory.splitlines():
                if line.startswith('### '):
                    zone = next((z for z in ('main', 'extra', 'side') if z in line.lower()), None)
                match = re.match(r'^\|\s*(\d+)\s*\|\s*([^|]+)\|\s*$', line)
                if match:
                    assert zone, line
                    cards.append(dict(card_name=match[2].strip(), card_id=0, section=zone,
                                      quantity=int(match[1]), role='', notes=json.dumps({
                                          'source_document': file.name,
                                          'source_identity': f'NUEVO_{ref}:{zone}:{len(cards)+1}',
                                          'side_information': 'not_received' if ref == '16' else 'received'}, ensure_ascii=False)))
        assert tuple(sum(c['quantity'] for c in cards if c['section'] == z)
                     for z in ('main', 'extra', 'side')) == expected, name
        assert len({(c['card_name'], c['section']) for c in cards}) == len(cards)
        result[str(deck)] = dict(name=name, format=fmt, source=str(file.relative_to(ROOT)), cards=cards)
    return result


def signature(cards):
    return sorted((c['card_name'], c['section'], int(c['quantity'])) for c in cards)


def fetch(save_native=False):
    plan = inventories()
    before = snapshot()
    write(OUT / 'live-before.json', before)
    write(OUT / 'inventory-plan.json', plan)
    feed = json.loads(request({'api': 'combo_creator', 'source_prompts': 1}))
    assert feed.get('ok') and feed.get('schema') == 'station-x-combo-source/1'
    write(OUT / 'editor-feed.json', feed)
    # Validate every deck and inventory before the first native write.
    pending = []
    for key, item in plan.items():
        deck = next(d for d in before['allDecks'] if int(d['id']) == int(key))
        assert deck['name'] == item['name'] and item['format'] in deck['format_codes']
        current = json.loads(request({'api': 'deck_builder', 'deck_id': key}))
        assert current['ok'] and current['can_edit'], key
        if current['cards']:
            assert signature(current['cards']) == signature(item['cards']), ('Inventory conflict', key)
        else:
            pending.append((key, item, current))
    if pending and not save_native:
        raise ValueError('Empty native lists require --save-native: ' + ', '.join(k for k, _, _ in pending))
    saved = []
    for key, item, current in pending:
        result = json.loads(request({'api': 'deck_builder', 'deck_id': key}, {
            'version': current['version'], 'token': current['token'], 'documented_import': True,
            'cards': [{k: c[k] for k in ('card_name', 'card_id', 'section', 'quantity', 'role', 'notes')}
                      for c in item['cards']]}))
        assert result.get('ok'), result
        after = json.loads(request({'api': 'deck_builder', 'deck_id': key}))
        assert signature(after['cards']) == signature(item['cards']), key
        saved.append(int(key))
        write(OUT / 'native-import.json', {'saved': saved})
    live = snapshot()
    native_signature = lambda rows: sorted((int(c['id']), int(c['deck_id']), c['section'], int(c['quantity'])) for c in rows)
    assert native_signature(before['cards']) == native_signature([c for c in live['cards'] if int(c['deck_id']) not in saved])
    write(OUT / 'live.json', live)
    return saved


def routes(raw):
    result = []
    for title, body in legacy.sections(raw, 3) + legacy.sections(raw, 4):
        match = re.match(r'(?:MEMD-|[PSG]-)?([CV]\d{2})\b', title)
        if match:
            result.append((match[1], legacy.plain(title), body))
    assert len({r[0] for r in result}) == len(result)
    return result


def build():
    plan, live, feed = inventories(), read(OUT / 'live.json'), read(OUT / 'editor-feed.json')
    registry_file = APP / 'assets/editorial/strategy-content-state.json'
    registry = read(registry_file)
    write(OUT / 'registry-before.json', registry)
    # Use the existing revision synchronizer with a catalog of all active routes.
    staging = OUT / 'source-sync'
    catalog = [{'id': r['id'], 'deck_id': int(key)} for key, entry in registry['checked_studies'].items()
               for r in read(APP / entry['path'].removeprefix('./'))['routes']]
    for key, item in plan.items():
        for rid, _, _ in routes((ROOT / item['source']).read_text(encoding='utf8')):
            if not any(r['id'] == f'{key}:A09:{rid}' for r in catalog):
                catalog.append({'id': f'{key}:A09:{rid}', 'deck_id': int(key)})
    write(staging / 'COMBOS_EXISTENTES_INDICE_EDITOR.json', {'rutas': catalog})
    revision_dir = SOURCE / 'revisiones_editor'
    if revision_dir.exists():
        shutil.copytree(revision_dir, staging / 'revisiones_editor', dirs_exist_ok=True)
    sync.apply_feed(feed, staging)
    shutil.copytree(staging / 'revisiones_editor', revision_dir, dirs_exist_ok=True)
    legacy.TARGET = TARGET
    TARGET.mkdir(parents=True, exist_ok=True)
    report, entries = [], {}
    template = read(APP / 'assets/editorial/20261116-folder-delivery-v31/671.json')
    for key, item in plan.items():
        deck = int(key)
        file = ROOT / item['source']
        raw = file.read_text(encoding='utf8')
        rows = [c for c in live['cards'] if int(c['deck_id']) == deck]
        assert signature(rows) == signature(item['cards']), deck
        old = registry['checked_studies'].get(key)
        data = read(APP / old['path'].removeprefix('./')) if old else {
            k: [] if isinstance(v, list) else {} if isinstance(v, dict) else '' for k, v in template.items()}
        data.update(schema=template['schema'], deck_id=deck, name=item['name'], format=item['format'])
        by_name = {c['name']: c for c in data['cards']}
        if old:
            descriptions = {c['cid']: c['description'] for c in item['cards'] if c.get('cid')}
            for card in data['cards']:
                if card.get('cid') in descriptions:
                    card['role'] = descriptions[card['cid']]
                    card['functional'] = {'purpose': card['role'], 'explanation': card['role']}
        if not old:
            for name in dict.fromkeys(c['card_name'] for c in rows):
                own = [c for c in rows if c['card_name'] == name]
                card = dict(id=f'{deck}:CARD:{own[0]["id"]}', cid=None, name=name, image='', full_image='',
                            slots=[dict(section=c['section'], quantity=int(c['quantity']), card_id=int(c['id'])) for c in own],
                            fields=[], practical=[], operational={}, learning={}, functional={}, role='',
                            synergy={'labels': [], 'resource': '', 'relation': ''}, sources=[])
                data['cards'].append(card)
                by_name[name] = card
        # Only explicit named table rows provide card-level tactical evidence.
        for line in raw.splitlines():
            if not line.startswith('|'):
                continue
            cells = [legacy.plain(c.strip()) for c in line.strip('|').split('|')]
            if len(cells) < 3:
                continue
            name = re.sub(r'\s*×\s*\d+.*$', '', cells[0]).strip()
            if name in by_name:
                c = by_name[name]
                c['role'] = cells[1]
                c['fields'] = [{'title': 'Qué hace en este deck', 'text': cells[1]},
                               {'title': 'Restricciones', 'text': ' | '.join(cells[2:])}]
                c['functional'] = {'purpose': cells[1], 'explanation': ' | '.join(cells[1:])}
        parts = legacy.sections(raw, 2)
        intro = next((body for title, body in parts if re.match(r'(?:APARTADO_0?1\b|A01\b)', title)), '')
        data['profile'] = {'Arquetipo': item['name'], 'Cómo se juega': legacy.plain(intro)}
        previous = {r['id']: r for r in data['routes']}
        updated = []
        for rid, title, body in routes(raw):
            identity = f'{deck}:A09:{rid}'
            route = previous.get(identity, dict(id=identity, family='Desarrollo de la jugada', category='APERTURA',
                        board=[], resources=[], hand_photos=[], purpose='', result='', steps=[]))
            steps = [dict(number=int(m[1]), action=legacy.plain(m[2]), detail=legacy.plain(m[2]),
                          photos=[], requirements=[], resources=[], board=[])
                     for m in re.finditer(r'^(\d+)\.\s+(.*?)(?=^\d+\.\s|\n\n|^\*\*(?:Final|Salida|Campo|Interrupci)|\Z)', body, re.M | re.S)]
            assert steps and [s['number'] for s in steps] == list(range(1, len(steps)+1)), identity
            first = re.search(r'^1\.\s', body, re.M)
            tail = body[re.search(r'^'+str(len(steps))+r'\.\s', body, re.M).start():]
            final = re.search(r'^\*\*(?:Final|Salida documentada|Campo final|Resultado)[^\n]*', tail, re.M)
            purpose = re.search(r'(?:Propósito|Objetivo)\s*:\*{0,2}\s*([^\n]+)', body)
            route.update(name=title[:160], title=title[:160], steps=steps, opening=legacy.plain(body[:first.start()]),
                         initial_details=legacy.plain(body[:first.start()]), documentary_source=body,
                         purpose=legacy.plain(purpose[1]) if purpose else title,
                         result=legacy.plain(final[0]) if final else '',
                         technical=legacy.plain(body), board=[], resources=[], hand_photos=[])
            route.pop('endboard', None)
            if identity not in previous:
                data['routes'].append(route)
            updated.append(identity)
        sync.apply_overrides(data, SOURCE)
        # Internal progress and publication instructions never become a player guide.
        player = [(title, re.sub(r'^### Certificado[^\n]*\n.*\Z', '', body, flags=re.M | re.S))
                  for title, body in parts
                  if not re.search(r'CONTROL|AVANCE|ESTADO DE LOS|TABLERO DE AVANCE|CIERRE|FIN Y|CERTIFICADO|IDENTIDAD BÁSICA', title, re.I)]
        (TARGET / f'{deck}-guide.html').write_text(legacy.render_page(item['name'], player, file), encoding='utf8')
        data['folder_delivery'] = dict(source_sha256=hashlib.sha256(file.read_bytes()).hexdigest(),
            source_file=item['source'].replace('\\', '/'), reader=f'./assets/editorial/{VERSION}/{deck}-guide.html')
        if deck == 699:
            data['profile']['Qué vigilar'] = 'Side no recibido; Extra: 14 copias documentadas. No completar cartas por inferencia.'
        target = TARGET / f'{deck}.json'
        write(target, data)
        entries[key] = dict(path=f'./assets/editorial/{VERSION}/{deck}.json', cards=len(data['cards']),
                            routes=len(data['routes']), sha256=hashlib.sha256(target.read_bytes()).hexdigest(), presentation='curated-v62')
        report.append(dict(deck_id=deck, name=item['name'], cards=len(data['cards']), routes=len(data['routes']),
                           updated_routes=len(updated), missing_images=sum(not c['image'] for c in data['cards'])))
    registry['checked_studies'].update(entries)
    registry['version'] = VERSION
    write(TARGET / 'manifest.json', {'version': VERSION, 'decks': entries})
    write(registry_file, registry)
    write(OUT / 'report.json', {'version': VERSION, 'decks': report, 'other_decks_changed': 0,
                               'remote_assets_published': False, 'sporting_writes': 0})
    state_file = APP / 'context/editorial-sync-state.json'
    state = read(state_file)
    state['incremental_gpt_import'] = {
        'version': VERSION, 'applied_on': '2026-11-17',
        'source': str(SOURCE.relative_to(ROOT)).replace('\\', '/'),
        'manifest': str((TARGET / 'manifest.json').relative_to(ROOT)).replace('\\', '/'),
        'report': str((OUT / 'report.json').relative_to(ROOT)).replace('\\', '/'),
        'updated_deck_ids': sorted(map(int, plan)),
        'active_decks': len(registry['checked_studies']),
        'active_cards': sum(e['cards'] for e in registry['checked_studies'].values()),
        'active_routes': sum(e['routes'] for e in registry['checked_studies'].values()),
        'native_list_import': '660:48/15/7;673:40/15/15;699:40/14/Side not received',
        'remote_assets_published': False, 'rules_certified': False}
    write(state_file, state)
    print(json.dumps(report))


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--fetch', action='store_true')
    parser.add_argument('--save-native', action='store_true')
    args = parser.parse_args()
    if args.fetch:
        print('Native lists created:', fetch(args.save_native))
    build()
