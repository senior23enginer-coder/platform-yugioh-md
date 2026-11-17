import hashlib
import json
from pathlib import Path
import sys

sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'tools'))
import import_a01_a07_v16 as task

task.build()
before={p.name:hashlib.sha256(p.read_bytes()).hexdigest() for p in task.TARGET.glob('*.json')}
task.build()
assert before=={p.name:hashlib.sha256(p.read_bytes()).hexdigest() for p in task.TARGET.glob('*.json')}
total={'cards':0,'routes':0,'reviewed':0,'pending':0,'relations':0,'controls':0}
for key in task.read(task.TARGET/'manifest.json')['decks']:
    current=task.read(task.TARGET/f'{key}.json')
    old=task.read(task.BASE/f'{key}.json')
    for field in ('skills','practice','side_plans','matchup_plans','profile'):
        assert current[field]==old[field],(key,field)
    assert [r['id'] for r in current['routes']]==[r['id'] for r in old['routes']]
    for c,previous in zip(current['cards'],old['cards']):
        assert (c['id'],c['cid'],c['slots'])==(previous['id'],previous['cid'],previous['slots'])
        assert c['source_revision']==task.VERSION
        if c['a05_status']!='REVISADA_EDITORIALMENTE':
            assert len(c['fields'])==1 and not c['functional']['explanation']
    total['cards']+=len(current['cards']);total['routes']+=len(current['routes'])
    for field in ('reviewed','pending','relations','controls'):total[field]+=current['a01_a07_revision'][field]
assert total==dict(cards=370,routes=127,reviewed=74,pending=296,relations=71,controls=76),total
print(json.dumps({'ok':True,'idempotent':True,'preservedInventories':True,'counts':total}))
