"""State reconstruction regressions: identity, copies, zones, alternatives."""
import hashlib
import json
from collections import Counter
from pathlib import Path

APP=Path(__file__).resolve().parents[1]
OUT=APP/'assets/editorial/20261113-endboards'
manifest=json.loads((OUT/'manifest.json').read_text(encoding='utf-8'))
routes={};total_variants=0
for deck_id,entry in manifest['decks'].items():
    raw=(APP/entry['path'].removeprefix('./')).read_bytes()
    assert hashlib.sha256(raw).hexdigest()==entry['sha256']
    data=json.loads(raw)
    prior=json.loads((APP/f'assets/editorial/20261113-v16/{deck_id}.json').read_text(encoding='utf-8'))
    assert data['cards']==prior['cards'] and len(data['skills'])==len(prior['skills'])
    assert {r['id']for r in data['routes']}=={r['id']for r in prior['routes']}
    cards={c['cid']:c for c in data['cards']}
    for r in data['routes']:
        assert r['endboard']['variants'];routes[r['id']]=r
        for v in r['endboard']['variants']:
            total_variants+=1;count=Counter();slots=set()
            assert v['columns']==(3 if data['format']=='rush_duel' else 5)
            assert len(v['skill'])==(1 if data['format']=='rush_duel' else 0)
            for key in ['monster','spell','field','hand','graveyard','banished','deck','extra']:
                assert isinstance(v[key],list)
                for p in v[key]:
                    if p.get('image'):assert (APP/p['image'].removeprefix('./')).is_file()
                    if p.get('cid'):
                        assert p['cid']in cards
                        if not p.get('conditional'):count[p['cid']]+=p['quantity']
                    if key in ['monster','spell','field']:assert p['quantity']==1
                    if key=='monster' and not p.get('conditional'):
                        place=(p['row'],p['slot']);assert place not in slots;slots.add(place)
                        assert 1<=p['slot']<=(2 if p['row']=='extra_monster' else v['columns'])
                        assert v['columns']==5 or p['row']!='extra_monster'
                    if 'materials'in p:
                        assert p['material_count']==sum(m['quantity']for m in p['materials'])
                        for m in p['materials']:
                            assert m['cid']in cards;count[m['cid']]+=m['quantity']
            for cid,n in count.items():assert n<=sum(s['quantity']for s in cards[cid]['slots']if s['section']in ['main','extra']),(r['id'],cid,n)

def base(rid):return routes[rid]['endboard']['variants'][0]
def ids(v,z):return [p['cid']for p in v[z]if p.get('cid')]
def material_ids(v):return [m['cid']for p in v['monster']for m in p.get('materials',[])]

v=base('665:A09:FENRIR:BIRTH:SHANGRI05')
assert ids(v,'monster')==[17800,17769] and material_ids(v)==[17768,17770]
assert ids(v,'graveyard')==[18203] and 17831 in ids(v,'spell')
assert next(p for p in v['spell']if p['cid']==17831)['position']=='Colocada'
for v in routes['665:A09:FENRIR:BIRTH:DARKARMED10']['endboard']['variants']:
    assert len(material_ids(v))==1 and set(material_ids(v)).isdisjoint(ids(v,'banished'))
    assert set(material_ids(v)+ids(v,'banished'))=={17768,17769}
v=base('684:A09:OVERLOAD:CYBERLOAD:DOUBLE_RAMPAGE01')
assert ids(v,'monster')==[11764,11764] and len({p['slot']for p in v['monster']})==2
v=base('684:A09:CYBER:CORE:AZALEA15');assert not v['monster'] and 18448 in ids(v,'graveyard')
v=base('684:A09:JORMUNGARDR:VIMANA16');assert material_ids(v)==[21163,6390]
v=base('653:A09:RAIDERS:ELARA:DRASTRIUS_BATTLE_ZEUS08');assert material_ids(v)==[21811,21774,21824]
v=base('653:A09:MEDIUS:JUPREDO:JUPITER04');assert ids(v,'extra')==[22521]and 22521 not in ids(v,'graveyard')
assert next(p for p in v['monster']if p['cid']==21810)['material_count']==0
v=base('656:A09:REINO:MERRLI:SCHEIREN_HAND01');assert set(ids(v,'deck'))=={17417,17414,17415}and not v['graveyard']
v=base('704:A09:ALCHEMY:ARCHER01');assert 18583 in ids(v,'deck')and 18583 not in ids(v,'graveyard')
v=base('656:A09:SUPERPOLY:GARURA10');assert 7445 in ids(v,'graveyard')and 7445 not in ids(v,'hand')
v=base('687:A09:DROPLET:RELOAD:SCATTERSHOT01');assert next(p for p in v['graveyard']if p['cid']==7083)['quantity']==3
assert len(routes)==105 and total_variants==114
assert sum(r['historical']for r in routes.values())==14
print(json.dumps({'ok':True,'routes':len(routes),'variants':total_variants,'nativeInventoriesUnchanged':True,'documentedSkillsExternal':True}))
