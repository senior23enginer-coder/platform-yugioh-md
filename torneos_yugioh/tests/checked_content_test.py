"""Check the active eligibility gate, including negative publication cases."""
import hashlib
import json
from pathlib import Path
import re
import sys

APP = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(APP/'tools'))
from build_checked_study import SOURCE, sections

policy = json.loads((APP/'assets/editorial/strategy-content-state.json').read_text(encoding='utf-8'))
if any(e.get('eligibility_policy') != 'checked-only-v1' for e in policy['checked_studies'].values()):
    # This policy remains a historical test after the owner's later 01-10 order.
    entries = {}
    for file in (APP/'assets/editorial/20261107-checked-content').glob('*.json'):
        data = json.loads(file.read_bytes())
        entries[str(data['deck_id'])] = dict(path='./'+file.relative_to(APP).as_posix(),
            sha256=hashlib.sha256(file.read_bytes()).hexdigest(), cases=len(data['checked_cases']))
    policy = dict(checked_studies=entries)
totals = dict(cards=0, interactions=0, cases=0)
assert len(policy['checked_studies']) == 11
for deck_id, entry in policy['checked_studies'].items():
    raw = (APP/entry['path']).read_bytes()
    assert hashlib.sha256(raw).hexdigest() == entry['sha256']
    data = json.loads(raw)
    source = SOURCE/'decks'/data['source_file']
    parts = sections(source.read_text(encoding='utf-8'))
    assert data['source_sha256'] == hashlib.sha256(source.read_bytes()).hexdigest()
    assert data['guide_sha256'] == hashlib.sha256((SOURCE/'GUIA_CODEX_STATION_X.md').read_bytes()).hexdigest()
    assert not any(k in data for k in ['sections', 'goal', 'engine_maps', 'skills', 'engine_notes'])
    assert data['routes'] == data['combos'] == data['engine_relations'] == []
    previous = json.loads((APP/f'assets/editorial/20261107-study-eleven/{deck_id}.json').read_text(encoding='utf-8'))
    assert data['inventory'] == previous['inventory']
    for card in data['cards']:
        rows = [l for l in parts[8].splitlines() if f'| {card["id"]} |' in l]
        assert len(rows) == 1 and '[X] FICHA_A05' in rows[0]
        assert (APP/card['image']).is_file()
    for unit in data['checked_interactions']:
        assert '[X]' in unit['check'] and 'CHEQUEAD' in unit['check']
        assert '[~]' not in unit['check'] and unit['id'] in parts[8]
    for unit in data['checked_cases']:
        assert unit['check'] and unit['sources'] and unit['reference_ids']
        assert all(line in parts[11] for line in unit['text'].splitlines() if line.strip()) and unit['full_combo_certified'] is False
        assert data['format'] != 'rush_duel' or not re.search(r'\bSkill\b', unit['text'], re.I)
    for key, collection in [('cards', 'cards'), ('interactions', 'checked_interactions'), ('cases', 'checked_cases')]:
        totals[key] += len(data[collection])
assert totals == dict(cards=370, interactions=2, cases=14)
assert policy['checked_studies']['706']['cases'] == 2
assert policy['checked_studies']['707']['cases'] == 2
print(json.dumps(dict(ok=True, decks=11, **totals, uncertified_routes_published=0, inventory_writes=0)))
