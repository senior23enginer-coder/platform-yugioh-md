"""Project exact local identity bindings and existing card metadata for offline planning.
No names are guessed, no inventory is written and no network is needed.
"""
import json
from pathlib import Path
APP=Path(__file__).resolve().parents[1]
identities=json.loads((APP/'assets/cards/originals/identities.json').read_text(encoding='utf-8'))['cards']
metadata={}
for p in (APP/'assets/editorial/20261101-11-decks').glob('deck-*.json'):
    d=json.loads(p.read_text(encoding='utf-8'))
    for key,value in d.get('card_metadata',{}).items():
        if key in metadata:assert metadata[key]['name']==value['name']
        metadata[key]=value
facts={};bindings={};names={};image_ids={}
for key,value in identities.items():
    if 'yugiohdb-' not in value['file']:continue
    cid=int(Path(value['file']).stem.split('-')[-1]);m=metadata.get(str(value.get('image_id')))
    if not m:continue
    source=f'https://www.db.yugioh-card.com/yugiohdb/card_search.action?ope=2&cid={cid}&request_locale=en'
    kind=m.get('type','');desc=m.get('description','')
    fact=dict(cid=cid,name=m['name'],source=source,kind='monster' if 'Monster' in kind else ('trap' if 'Trap' in kind else 'spell'),
        species=kind,level=m.get('level') if 'Xyz' not in kind and 'Link' not in kind else None,
        rank=m.get('level') if 'Xyz' in kind else None,attribute=m.get('attribute'),atk=m.get('atk'),defense=m.get('def'),
        normalAllowed='Monster' in kind and not any(t in kind for t in ['Fusion','Synchro','Xyz','Link','Ritual']) and 'cannot be normal summoned' not in desc.lower(),
        setcode='Kashtira' if 'Kashtira' in m['name'] else '',provenance='Existing exact image-id / Konami CID binding and registered metadata')
    if str(cid) in facts:assert facts[str(cid)]==fact
    facts[str(cid)]=fact;bindings[key]=cid;names[key]=value['card_name'];image_ids[key]=value['image_id']
out=APP/'assets/modules/play-calculator/card-facts.json'
out.write_bytes((json.dumps(dict(version=1,facts=facts,bindings=bindings,names=names,imageIds=image_ids),ensure_ascii=False,indent=2)+'\n').encode('utf-8'))
print(json.dumps(dict(facts=len(facts),bindings=len(bindings))))
