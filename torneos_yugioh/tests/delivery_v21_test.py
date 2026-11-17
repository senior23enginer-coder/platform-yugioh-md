import sys
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'tools'))
import import_delivery_v21 as task

task.build();first={p.name:p.read_bytes() for p in task.TARGET.glob('*.json')}
task.build();assert first=={p.name:p.read_bytes() for p in task.TARGET.glob('*.json')}
totals=dict(cards=0,relations=0,cases=0,variants=0,decisions=0,routes=0)
for key in task.read(task.TARGET/'manifest.json')['decks']:
    data=task.read(task.TARGET/f'{key}.json');old=task.read(task.BASE/f'{key}.json')
    for field in ('routes','profile','skills','practice','side_plans','matchup_plans','decisions'):
        assert data[field]==old[field],(key,field)
    for c,previous in zip(data['cards'],old['cards']):
        assert all(c[k]==v for k,v in previous.items()),(key,c['cid'])
        assert c['causal_profile']['deck_id']==int(key)
    cids={c['cid'] for c in data['cards']}
    for r in data['causal_relations']:
        assert r['origen']['cid'] in cids and r['destino']['cid'] in cids
        for p in r['photos']:assert p['slots']==next(c['slots'] for c in data['cards'] if c['cid']==p['cid'])
    for d in data['situational_decisions']:
        assert all(p['cid'] in cids for p in d['photos'])
    assert len(data['situational_decisions'])==7
    totals['cards']+=len(data['cards']);totals['relations']+=len(data['causal_relations']);totals['cases']+=data['delivery_v21']['rule_cases'];totals['variants']+=len(data['rule_variants']);totals['decisions']+=len(data['situational_decisions']);totals['routes']+=len(data['routes'])
assert totals==dict(cards=370,relations=157,cases=98,variants=294,decisions=77,routes=127),totals
print({'ok':True,'idempotent':True,'inventories_and_previous_content_preserved':True,'totals':totals})
