"""Build only individually checked editorial card studies; never change inventories.

The supplied live snapshot is read-only DML_DATA (cards/allDecks). Source Markdown
is retained unchanged. A08 FICHA_A05 approves a card study, not a combo or engine.
"""
from pathlib import Path
import argparse, collections, hashlib, json, re, shutil, unicodedata

ROOT = Path(__file__).resolve().parents[2]
APP = ROOT / 'torneos_yugioh'
SOURCE = APP / 'context/gpt/STATION_X_PROMPTS_MODULARES_V8_APARTADO_01_ROL_OBJETIVO_11_DECKS'
OUT = APP / 'assets/editorial/20261104-checked-study'

def norm(value):
    value = re.sub(r'\[[^\]]*\]', '', value)
    return ''.join(c for c in unicodedata.normalize('NFKD', value).lower() if c.isalnum())

def sections(text):
    return {int(m[1]): m[2] for m in re.finditer(r'^### APARTADO_(\d+)[^\n]*\n(.*?)(?=^### APARTADO_|\Z)', text, re.M | re.S)}

def inventory(text):
    result = []; zone = None
    for line in text.split('```text', 1)[1].split('```', 1)[0].splitlines():
        for title, key in [('Main Deck', 'main'), ('Extra Deck', 'extra'), ('Side Deck', 'side'), ('Reserva', 'reserve')]:
            if line.startswith(title): zone = key
        match = re.match(r'(\d+)x \| EN: (.*?) \| ES: (.*)', line)
        if match:
            result.append({'section': zone, 'quantity': int(match[1]), 'names': [match[2], match[3]]})
        if line.strip().startswith('Nombre guardado:'):
            result[-1]['names'].append(line.split('Nombre guardado:', 1)[1].strip())
    return result

def build(live):
    OUT.mkdir(parents=True, exist_ok=True)
    manifest = {'version': '20261104-checked-study-136', 'scope': 'checked_card_studies_only', 'decks': {}}
    issues = []; totals = collections.Counter()
    for file in sorted((SOURCE / 'decks').glob('DECK_*.md')):
        deck_id = int(file.name.split('_')[1]); text = file.read_text(encoding='utf-8'); parts = sections(text)
        native = next(d for d in live['allDecks'] if int(d['id']) == deck_id)
        expected_format = re.search(r'FORMATO: (\w+)', text)[1]
        assert expected_format in native['format_codes'], (deck_id, 'format')
        assert norm(re.search(r'^DECK: (.+)', text, re.M)[1]) == norm(native['name']), (deck_id, 'name')
        rows = [c for c in live['cards'] if int(c['deck_id']) == deck_id]
        inv = inventory(text)
        # Exact bilingual names and section/quantity, not fuzzy archetype matching.
        for item in inv:
            matches = [r for r in rows if r['section'] == item['section'] and {norm(r['card_name']), *(norm(n) for n in r['card_name'].split(' / '))}.intersection({norm(n) for n in item['names']})]
            assert len(matches) == 1 and int(matches[0]['quantity']) == item['quantity'], (deck_id, 'inventory mismatch', item)
            item['native'] = matches[0]
        assert len(inv) == len(rows), (deck_id, 'extra native inventory entries')
        approved = {}
        for line in parts[8].splitlines():
            cells = [c.strip() for c in line.strip('|').split('|')]
            if len(cells) == 6 and '[X] FICHA_A05' in cells[-1]:
                n = int(re.search(r':C(\d+)', cells[0])[1]); assert n not in approved
                approved[n] = cells
        cards = []
        for m in re.finditer(r'^#### (\d+)\. (.*?)\n(.*?)(?=^#### |^### |\Z)', parts[5], re.M | re.S):
            n = int(m[1]); body = m[3].strip()
            if n not in approved: continue
            photo = re.search(r'!\[[^\]]*\]\((\.\./imagenes/(?:tcg|rush)/([0-9]+)\.[a-z]+)\)', body)
            assert photo, (deck_id, n, 'missing photo')
            cid = int(photo[2]); matched = []
            for item in inv:
                notes = json.loads(item['native'].get('notes') or '{}')
                if int(notes.get('konami_cid') or 0) == cid: matched.append(item)
            if not matched:
                names = {norm(x) for x in [m[2], approved[n][1], *m[2].split(' — ')]}
                matched = [i for i in inv if not json.loads(i['native'].get('notes') or '{}').get('konami_cid') and names.intersection({norm(x) for x in i['names']})]
            assert matched, (deck_id, n, cid, 'CID absent from native list')
            image = (file.parent / photo[1]).resolve(); assert image.is_relative_to(SOURCE.resolve()) and image.is_file()
            target = OUT / 'images' / image.parent.name / image.name; target.parent.mkdir(parents=True, exist_ok=True); shutil.copyfile(image, target)
            declared_zone = approved[n][2]
            zone_names = {'Main': 'main', 'Extra': 'extra', 'Side': 'side', 'Reserva': 'reserve'}
            zone_ok = zone_names.get(declared_zone) in {x['section'] for x in matched}
            if not zone_ok: issues.append({'deck_id': deck_id, 'unit': approved[n][0], 'reason': 'A08 zone contradicts approved inventory; classification omitted'})
            # Classification with pending prerequisites is not a completed play.
            classification = approved[n][3] if zone_ok and not any(s in approved[n][3].lower() for s in ['potencial', 'por comprobar', 'sujeto a condición']) else None
            clean = re.sub(r'!\[[^\]]*\]\([^)]*\)', '', body).strip()
            fields = []
            for field in re.finditer(r'^- \*\*(.*?)\*\*[:：]?\s*(.*?)(?=^- \*\*|\Z)', clean, re.M | re.S):
                fields.append({'title': field[1].rstrip(':'), 'text': field[2].strip()})
            assert fields, (deck_id, n, 'no card fields')
            cards.append({'id': approved[n][0], 'name': m[2].strip(), 'cid': cid, 'image': './assets/editorial/20261104-checked-study/images/'+image.parent.name+'/'+image.name,
                          'slots': [{'section': i['section'], 'quantity': i['quantity'], 'card_id': i['native']['id']} for i in matched],
                          'classification': classification, 'fields': fields, 'text': clean, 'check': 'FICHA_A05', 'rules_certified': False})
        assert len(cards) == len(approved), (deck_id, len(cards), len(approved))
        record = {'deck_id': deck_id, 'name': native['name'], 'format': expected_format, 'source_file': file.name,
                  'source_sha256': hashlib.sha256(file.read_bytes()).hexdigest(), 'inventory': [{'section': i['section'], 'quantity': i['quantity'], 'card_id': i['native']['id'], 'name': i['native']['card_name']} for i in inv],
                  'cards': cards, 'combos': [], 'engine_relations': [], 'scope': 'Fichas editoriales individuales respaldadas por checks A08:FICHA_A05; sin certificación de combos.'}
        destination = OUT / f'{deck_id}.json'; destination.write_bytes((json.dumps(record, ensure_ascii=False, indent=2)+'\n').encode())
        manifest['decks'][str(deck_id)] = {'path': f'./assets/editorial/20261104-checked-study/{deck_id}.json', 'cards': len(cards), 'sha256': hashlib.sha256(destination.read_bytes()).hexdigest()}
        totals['cards'] += len(cards); totals['inventory_slots'] += len(inv)
    assert len(manifest['decks']) == 11 and totals['cards'] == 370, totals
    (OUT / 'manifest.json').write_bytes((json.dumps(manifest, ensure_ascii=False, indent=2)+'\n').encode())
    print(json.dumps({'decks': 11, **totals, 'combos': 0, 'partial_engine_relations_imported': 0, 'omitted_contradictory_classifications': issues}, ensure_ascii=False))
    return manifest

if __name__ == '__main__':
    parser = argparse.ArgumentParser(); parser.add_argument('--live-snapshot', required=True)
    build(json.loads(Path(parser.parse_args().live_snapshot).read_text(encoding='utf-8-sig')))
