"""Import the received 01–11 outcomes; never run later prompts or alter lists.

The A10 HTML is a data source, not an iframe: only whitelisted A09/A10
fields are extracted. A12/A13 branches and inferred tactical selectors stay out.
Source sections remain intact for provenance, including editorial uncertainty.
"""
import argparse
import hashlib
import json
import re
from pathlib import Path
import build_study_sections as seven
from build_checked_study import APP, SOURCE, sections

OUT = APP / 'assets/editorial/20261107-study-eleven'
VERSION = '20261107-study-eleven-139'
TITLES = seven.TITLES + ['Interacciones y piezas iniciales', 'Fichas de combos', 'Guías visuales', 'Cadenas y resolución']


def digest(raw):
    return hashlib.sha256(raw).hexdigest()


def build(live, max_section=11, output=None):
    assert max_section in (10, 11)
    destination = Path(output) if output else OUT
    # Reuse the identity/inventory checks and A06 map extraction already in use.
    seven.OUT = destination
    seven.build(live)
    manifest = {'version': VERSION if max_section == 11 else '20261107-study-ten-143', 'scope': f'received_sections_01_to_{max_section}', 'decks': {}}
    for file in sorted((SOURCE / 'decks').glob('DECK_*.md')):
        deck_id = int(file.name.split('_')[1])
        raw_source = file.read_bytes()
        parts = sections(file.read_text(encoding='utf-8'))
        data = json.loads((destination / f'{deck_id}.json').read_text(encoding='utf-8'))
        data['sections'] = [dict(number=n, title=TITLES[n-1], source_text=parts[n].strip(),
                                 source_sha256=digest(parts[n].strip().encode()),
                                 status='received_documentation', rules_certified=False,
                                 visibility='internal' if n == 1 else 'support' if n == 3 else 'reader')
                            for n in range(1, max_section + 1)]
        # Refresh the actual A05 text instead of retaining yesterday's descriptions.
        cards = {c['id']: c for c in data['cards']}
        refreshed = 0
        for m in re.finditer(r'^#### (\d+)\. (.*?)\n(.*?)(?=^#### |^### |\Z)', parts[5], re.M | re.S):
            key = f'{deck_id}:A08:C{int(m[1]):03}'
            card = cards.get(key)
            if not card:
                # Source IDs can be padded differently; CID is still exact.
                photo = re.search(r'\.\./imagenes/(?:tcg|rush)/(\d+)\.', m[3])
                card = next((c for c in data['cards'] if photo and c['cid'] == int(photo[1])), None)
            assert card, (deck_id, m[1], 'unbound card study')
            body = re.sub(r'!\[[^\]]*\]\([^)]*\)', '', m[3]).strip()
            card['text'] = body
            card['fields'] = [{'title': f[1].rstrip(':'), 'text': f[2].strip()}
                              for f in re.finditer(r'^- \*\*(.*?)\*\*[:：]?\s*(.*?)(?=^- \*\*|\Z)', body, re.M | re.S)]
            assert card['fields']
            refreshed += 1
        assert refreshed == len(data['cards'])
        data['identity']['source_date'] = '2026-11-07'
        data['identity']['inventory_verified_on'] = '2026-11-07'
        # Images must resolve to this deck's registered card/Skill, not a name guess.
        photo_map = {Path(c['image']).name: c['image'] for c in data['cards'] + data['skills']}
        def convert(value):
            if isinstance(value, str) and value.startswith('../imagenes/'):
                assert Path(value).name in photo_map, (deck_id, 'foreign or unknown photograph', value)
                return photo_map[Path(value).name]
            if isinstance(value, dict):
                return {k: convert(v) for k, v in value.items()}
            if isinstance(value, list):
                return [convert(v) for v in value]
            return value
        guide = SOURCE / 'guias_visuales_a10' / f'DECK_{deck_id}_GUIAS.html'
        html = guide.read_text(encoding='utf-8')
        payload = json.loads(re.search(r'window.GUIDE_DATA=(.*?);</script>', html, re.S)[1])
        assert payload['id'] == deck_id and {'TCG': 'tcg_advanced', 'RUSH': 'rush_duel'}[payload['format']] == data['format']
        allowed = ['id', 'name', 'category', 'hand', 'hand_photos', 'hand_labels', 'initial_items',
                   'steps', 'summary', 'source', 'source_full', 'local_responses']
        data['routes'] = []
        for route in payload['routes']:
            assert route['id'].startswith(f'{deck_id}:A09:')
            # Some Rush alternatives combine the shared heading with a subsection.
            assert all(line in parts[9] for line in route['source_full'].splitlines() if line.strip()), route['id']
            item = convert({k: route[k] for k in allowed if k in route})
            # A source may discuss later chapters, but their actual content is not imported.
            assert not re.search(r'^#{3,5} A(?:12|13)\b', item['source_full'], re.M)
            item['rules_certified'] = False
            item['status'] = 'Documentado · verificación integral pendiente'
            data['routes'].append(item)
        assert len({r['id'] for r in data['routes']}) == len(data['routes'])
        data['guide_source_sha256'] = digest(guide.read_bytes())
        data['scope'] = f'Resultados recibidos 01-{max_section}, carga documental autorizada por el propietario. Apartado 01 interno; 03 de soporte. Sin certificación integral de rutas.'
        encoded = (json.dumps(data, ensure_ascii=False, indent=2) + '\n').encode()
        (destination / f'{deck_id}.json').write_bytes(encoded)
        manifest['decks'][str(deck_id)] = dict(path='./'+(destination/f'{deck_id}.json').relative_to(APP).as_posix(),
            cards=len(data['cards']), sections=max_section, routes=len(data['routes']),
            steps=sum(len(r['steps']) for r in data['routes']), sha256=digest(encoded))
    (destination / 'manifest.json').write_bytes((json.dumps(manifest, ensure_ascii=False, indent=2)+'\n').encode())
    print(json.dumps({'decks': len(manifest['decks']), 'sections': len(manifest['decks'])*max_section,
                      'routes': sum(x['routes'] for x in manifest['decks'].values()),
                      'steps': sum(x['steps'] for x in manifest['decks'].values()), 'inventory_writes': 0}))
    return manifest


if __name__ == '__main__':
    # The permissive documentary export above is historical. Active imports must
    # use the guide's per-unit eligibility gate and preserve the old archive.
    from import_checked_content import build as build_checked
    parser = argparse.ArgumentParser()
    parser.add_argument('--live-snapshot', required=True)
    args = parser.parse_args()
    build_checked(json.loads(Path(args.live_snapshot).read_text(encoding='utf-8-sig')))
