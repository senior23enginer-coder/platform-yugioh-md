"""Scope, source fidelity, inventory and photograph checks for the 01–11 import."""
import hashlib
import json
import re
from pathlib import Path
import sys

APP = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(APP / 'tools'))
from build_checked_study import SOURCE, sections

folder = APP / 'assets/editorial/20261107-study-eleven'
manifest = json.loads((folder / 'manifest.json').read_text(encoding='utf-8'))
assert len(manifest['decks']) == 11
cards = routes = steps = 0
for deck_id, entry in manifest['decks'].items():
    raw = (APP / entry['path']).read_bytes()
    assert hashlib.sha256(raw).hexdigest() == entry['sha256']
    data = json.loads(raw)
    previous = json.loads((APP / f'assets/editorial/20261104-study-seven/{deck_id}.json').read_text(encoding='utf-8'))
    assert data['inventory'] == previous['inventory']
    original = SOURCE / 'decks' / data['source_file']
    assert data['source_sha256'] == hashlib.sha256(original.read_bytes()).hexdigest()
    parts = sections(original.read_text(encoding='utf-8'))
    assert [s['number'] for s in data['sections']] == list(range(1, 12))
    for section in data['sections']:
        assert section['source_text'] == parts[section['number']].strip()
    assert data['sections'][0]['visibility'] == 'internal'
    assert data['sections'][2]['visibility'] == 'support'
    paths = {c['image'] for c in data['cards'] + data['skills']}
    for image in paths:
        assert (APP / image).is_file(), image
    for route in data['routes']:
        assert not {'branches', 'tactical_rows', 'tactical_scenarios'}.intersection(route)
        assert route['id'].startswith(deck_id + ':A09:')
        assert route['rules_certified'] is False
        assert all(line in parts[9] for line in route['source_full'].splitlines() if line.strip())
        for step in route['steps']:
            assert step['photos'] and set(step['photos']) <= paths
            assert step['zone_trace'] and len(step['zone_trace']) == 8
            for field in ['monsters', 'spells']:
                assert all(c['photo'] in paths or (not c['photo'] and 'no especificada' in c['status']) for c in (step.get('zone_board') or {}).get(field, []))
        steps += len(route['steps'])
    cards += len(data['cards'])
    routes += len(data['routes'])
assert (cards, routes, steps) == (370, 78, 373)
print(json.dumps(dict(ok=True, decks=11, sections=121, cards=cards, routes=routes, steps=steps,
                      inventory_unchanged=True, later_sections_excluded=True)))
