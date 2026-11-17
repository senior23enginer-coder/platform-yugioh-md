"""Owner-requested 01-10 completeness and exclusion of the eleventh section."""
import hashlib
import json
from pathlib import Path
import sys

APP = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(APP/'tools'))
from build_checked_study import SOURCE, sections

policy = json.loads((APP/'assets/editorial/strategy-content-state.json').read_text(encoding='utf-8'))
assert len(policy['checked_studies']) == 11
totals = dict(cards=0, routes=0, steps=0, skills=0)
for deck_id, entry in policy['checked_studies'].items():
    raw = (APP/entry['path']).read_bytes()
    assert hashlib.sha256(raw).hexdigest() == entry['sha256']
    data = json.loads(raw)
    source = SOURCE/'decks'/data['source_file']
    parts = sections(source.read_text(encoding='utf-8'))
    assert data['source_sha256'] == hashlib.sha256(source.read_bytes()).hexdigest()
    assert [s['number'] for s in data['sections']] == list(range(1, 11))
    for section in data['sections']:
        assert section['source_text'] == parts[section['number']].strip()
    assert 'checked_cases' not in data and 'checked_interactions' not in data
    old = json.loads((APP/f'assets/editorial/20261107-study-eleven/{deck_id}.json').read_text(encoding='utf-8'))
    assert data['inventory'] == old['inventory'] and data['routes'] == old['routes']
    assert all(r['rules_certified'] is False for r in data['routes'])
    for card in data['cards']:
        assert (APP/card['image']).is_file()
    totals['cards'] += len(data['cards'])
    totals['routes'] += len(data['routes'])
    totals['steps'] += sum(len(r['steps']) for r in data['routes'])
    totals['skills'] += len(data['skills'])
assert totals == dict(cards=370, routes=78, steps=373, skills=4), totals
print(json.dumps(dict(ok=True, decks=11, sections=110, **totals, section_11_imported=False, inventory_writes=0)))
