"""Cross-check the current navigation against its exact inventory and sources."""
import hashlib
import json
from pathlib import Path

APP = Path(__file__).resolve().parents[1]
OUT = APP/'assets/editorial/20261113-endboards'
manifest = json.loads((OUT/'manifest.json').read_text(encoding='utf-8'))
totals = dict(cards=0, routes=0, steps=0, extra=0, decisions=0, practice=0, skills=0)
for deck_id, entry in manifest['decks'].items():
    raw = (APP/entry['path'].removeprefix('./')).read_bytes()
    assert hashlib.sha256(raw).hexdigest() == entry['sha256']
    data = json.loads(raw)
    previous = json.loads((APP/f'assets/editorial/20261108-complete/{deck_id}.json').read_text(encoding='utf-8'))
    identity = lambda rows: [(c['cid'], c['slots'], c['image']) for c in rows]
    assert identity(data['cards']) == identity(previous['cards'])
    historical = {r['id']: r for r in previous['routes']}
    for route in data['routes']:
        assert all(s.get('compact_action') and s['photos'] for s in route['steps'])
        if route['id'] in historical:
            prior = historical[route['id']]
            assert len(prior['steps']) == len(route['steps'])
    totals['cards'] += len(data['cards'])
    totals['routes'] += len(data['routes'])
    totals['steps'] += sum(len(r['steps']) for r in data['routes'])
    totals['extra'] += sum(any(s['section'] == 'extra' for s in c['slots']) for c in data['cards'])
    for field in ['decisions', 'practice', 'skills']:
        totals[field] += len(data[field])
    for card in data['cards']:
        assert card['learning']['Aplicación concreta registrada']
        assert len(card['operational']) == 4
        assert card['construction']
        assert '[V14]' not in str(card['operational'])
        if card.get('extra_navigation'):
            assert any(s['section'] == 'extra' for s in card['slots'])
    if deck_id == '687':
        assert not next(c for c in data['cards'] if c['cid'] == 14144).get('extra_navigation')
        assert not next(c for c in data['cards'] if c['cid'] == 15756).get('extra_navigation')
    assert sum(r['proposed']-r['current'] for r in data['optimization']['proposals'] if r['zone']=='main') == 0
    if deck_id=='665':
        donner=next(c for c in data['cards'] if c['cid']==17804)
        assert 'propia' in donner['extra_explanation'] and any('GODDESS12' in r for r in donner['related_routes'])
    if deck_id=='707':
        for cid in (19995,18298,19323):
            c=next(c for c in data['cards'] if c['cid']==cid)
            assert 'Curse' in c['extra_explanation'] or 'maldici' in c['extra_explanation'].lower()
all_data=[json.loads((APP/entry['path'].removeprefix('./')).read_text(encoding='utf-8')) for entry in manifest['decks'].values()]
assert sum(bool(r.get('historical')) for d in all_data for r in d['routes'])==14
assert sum(d['optimization']['direct'] for d in all_data)==40
assert sum(d['optimization']['indirect'] for d in all_data)==131
assert sum(d['optimization']['main_identities'] for d in all_data)==216
assert totals == dict(cards=370, routes=105, steps=511, extra=98, decisions=45, practice=33, skills=4), totals
print(json.dumps(dict(ok=True, **totals)))
