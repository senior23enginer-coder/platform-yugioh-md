from pathlib import Path
import sys
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'tools'))
import import_delivery_v20 as task

task.build()
first={p.name:p.read_bytes() for p in task.TARGET.glob('*.json')}
task.build()
assert first=={p.name:p.read_bytes() for p in task.TARGET.glob('*.json')}
totals=dict(cards=0,reviewed=0,pending=0,relations=0,controls=0,scenarios=0,routes=0)
source={(r['deck_id'],r['cid']):r for r in task.read(task.SOURCE/'FICHAS_A05_370_COMPLETAS_V19.json')['fichas']}
for key in task.read(task.TARGET/'manifest.json')['decks']:
    data=task.read(task.TARGET/f'{key}.json');old=task.read(task.BASE/f'{key}.json')
    for field in ('routes','profile','skills','practice','side_plans','matchup_plans'):
        assert data[field]==old[field],(key,field)
    for c,o in zip(data['cards'],old['cards']):
        assert (c['id'],c['cid'],c['slots'])==(o['id'],o['cid'],o['slots'])
        assert c['operational']['Acción']==source[data['deck_id'],c['cid']]['descripcion_precisa']
        assert all(f['text'] for f in c['fields']) and len(c['fields'])==8
        assert len(c['rule_contract']['verificaciones_minimas'])==6
        assert c['rule_contract']['replay_en_motor']=='NO_EJECUTADO'
    totals['cards']+=len(data['cards']);totals['routes']+=len(data['routes'])
    for k in ('reviewed','pending','relations','controls','scenarios'):totals[k]+=data['a01_a07_revision'][k]
assert totals==dict(cards=370,reviewed=153,pending=217,relations=157,controls=76,scenarios=22,routes=127),totals
print({'ok':True,'idempotent':True,'unchangedInventories':True,'totals':totals})
