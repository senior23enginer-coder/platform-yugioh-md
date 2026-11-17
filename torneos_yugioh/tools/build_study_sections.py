"""Map the seven documentary chapters without changing source or inventory.

A06 resource maps are documentary relationships, never A08 candidate routes.
Identity, zone and photograph correspondence are checked separately from rules.
"""
from pathlib import Path
import hashlib,json,re,shutil,argparse
from build_checked_study import APP,SOURCE,sections

OUT=APP/'assets/editorial/20261104-study-seven'
PREVIOUS=APP/'assets/editorial/20261104-checked-study'
TITLES=['Empieza aquí','Identidad y versión','Lista y cantidades','Fuentes y zonas','Cartas y funciones','Motores y sinergias','Práctica y reglas']

def digest(raw):return hashlib.sha256(raw).hexdigest()

def apply_prompts_to_context(live):
    destinations=['Empieza aquí: objetivo y competencias','Identidad y versión: registro vivo y procedencia','Lista y cantidades: inventario conservado','Fuentes y zonas: disponibilidad y Skill separada','Cartas y funciones: fichas individuales','Motores y sinergias: relaciones y conflictos documentados','Práctica y reglas: mecánicas del formato y requisitos']
    for file in sorted((SOURCE/'decks').glob('DECK_*.md')):
        deck_id=int(file.name.split('_')[1]);deck=next(d for d in live['allDecks']if int(d['id'])==deck_id)
        text=file.read_bytes().decode('utf-8');ending='\r\n'if'\r\n'in text else'\n'
        def update(match):
            n=int(match[1])
            if n>7 or '###'+'# Aplicación en Station X' in match[2]:return match[0]
            note=f'#### Aplicación en Station X{ending}{ending}- **Aplicación autorizada 2026-11-04:** prompt {n:02d} aplicado a los resultados documentados de este mismo deck. Destino: {destinations[n-1]}. ID {deck_id}; formato {deck["format_codes"][0]}; equipo consultado: {deck.get("team")or"No registrado"}. Inventario cotejado sin cambiar cartas ni cantidades. La documentación editorial no acredita secuencias de combo.{ending}{ending}'
            return match[0].rstrip()+ending+ending+note
        text=re.sub(r'^### APARTADO_(\d+)[^\n]*\n(.*?)(?=^### APARTADO_|\Z)',update,text,flags=re.M|re.S)
        file.write_bytes(text.encode())

def build(live):
    OUT.mkdir(parents=True,exist_ok=True)
    manifest={'version':'20261104-study-seven-138','scope':'documentary_sections_01_to_07','decks':{}}
    maps=0
    for file in sorted((SOURCE/'decks').glob('DECK_*.md')):
        deck_id=int(file.name.split('_')[1]);original=file.read_bytes();parts=sections(original.decode('utf-8'))
        record=json.loads((PREVIOUS/f'{deck_id}.json').read_text(encoding='utf-8'))
        record['source_sha256']=digest(original)
        deck=next(d for d in live['allDecks'] if int(d['id'])==deck_id)
        assert deck['name']==record['name'] and record['format'] in deck['format_codes']
        native=[c for c in live['cards'] if int(c['deck_id'])==deck_id]
        signature=lambda rows:sorted((int(c.get('card_id',c.get('id'))),c['section'],int(c['quantity']),c.get('name',c.get('card_name')))for c in rows)
        assert signature(native)==signature(record['inventory']),'Live inventory changed: '+str(deck_id)
        record['sections']=[]
        for number in range(1,8):
            assert 'COMPLETA' in parts[number][:600],(deck_id,number,'documentary chapter not complete')
            text=parts[number].strip()
            record['sections'].append({'number':number,'title':TITLES[number-1],'source_text':text,'source_sha256':digest(text.encode()),'status':'documented_editorially','rules_certified':False})
        record['identity']={'deck_id':deck_id,'name':deck['name'],'format':record['format'],'team':deck.get('team'),
            'source_date':'2026-11-03','inventory_verified_on':'2026-11-04','inventory_sha256':digest(json.dumps(record['inventory'],sort_keys=True,ensure_ascii=False).encode()),'source_contract':'4'}
        record['goal']={m[1]:m[2].strip() for m in re.finditer(r'^- \*\*(Objetivo central|Competencias a desarrollar|Criterio de demostración):\*\* (.*)$',parts[1],re.M)}
        assert 'Objetivo central' in record['goal']
        photos={Path(c['image']).name:{'image':c['image'],'name':c['name'],'cid':c['cid'],'slots':c['slots'],'kind':'card'}for c in record['cards']}
        record['skills']=[]
        for photo in sorted(set(re.findall(r'!\[[^\]]*\]\((\.\./imagenes/skills/[^)]+)\)',parts[5]+parts[6]))):
            image=(file.parent/photo).resolve();assert image.is_file() and image.is_relative_to(SOURCE.resolve())
            assert image.name.startswith(str(deck_id)+'_'),(deck_id,'foreign skill')
            target=OUT/'skills'/image.name;target.parent.mkdir(exist_ok=True);shutil.copyfile(image,target)
            skilltext=re.search(r'^#### Skill independiente[^\n]*\n(.*?)(?=^#### |^### |\Z)',parts[5],re.M|re.S)
            assert skilltext,(deck_id,'missing separate skill description')
            title=re.search(r'^#### Skill independiente\s*[—–-]\s*(.*)',parts[5],re.M)[1].strip()
            skill={'name':title,'image':'./assets/editorial/20261104-study-seven/skills/'+image.name,'text':skilltext[1].strip(),'kind':'skill','not_in_draw_pool':True,'rules_certified':False}
            photos[image.name]=skill;record['skills'].append(skill)
        assert len(record['skills'])==(1 if record['format']=='rush_duel' else 0)
        record['engine_maps']=[]
        for line in parts[6].splitlines():
            cells=[c.strip()for c in line.strip('|').split('|')]
            if len(cells)!=4 or '![' not in cells[-1]:continue
            references=re.findall(r'!\[[^\]]*\]\((\.\./imagenes/[^)]+)\)',cells[-1]);assert references
            for photo in references:assert Path(photo).name in photos,(deck_id,'unbound A06 photo',photo)
            images=[photos[Path(p).name]for p in references]
            assert all(images) and all(cells[:3])
            record['engine_maps'].append({'id':f'{deck_id}:A06:map:{len(record["engine_maps"])+1:02}',
                'title':cells[0],'result':cells[1],'requirements':cells[2],'photos':images,
                'source_section':6,'identity_links_checked':True,'rules_certified':False,'is_combo':False})
        assert record['engine_maps'],(deck_id,'no documentary maps')
        maps+=len(record['engine_maps'])
        # Keep the full A06 prose, except the photo table which has its own view.
        record['engine_notes']='\n'.join(line for line in parts[6].splitlines()if not line.startswith(('|','#### Mapa'))).strip()
        record['scope']='Documentación editorial 01–07 con correspondencia de inventario e imágenes. Sin importar rutas de A08 ni certificar combos.'
        raw=(json.dumps(record,ensure_ascii=False,indent=2)+'\n').encode();(OUT/f'{deck_id}.json').write_bytes(raw)
        manifest['decks'][str(deck_id)]={'path':f'./assets/editorial/20261104-study-seven/{deck_id}.json','cards':len(record['cards']),'sections':7,'engine_maps':len(record['engine_maps']),'sha256':digest(raw)}
    assert len(manifest['decks'])==11
    (OUT/'manifest.json').write_bytes((json.dumps(manifest,ensure_ascii=False,indent=2)+'\n').encode())
    print(json.dumps({'decks':11,'sections':77,'card_studies':370,'documentary_engine_maps':maps,'skills_separate':4,'combos_imported':0,'inventory_writes':0}))
    return manifest

if __name__=='__main__':
    parser=argparse.ArgumentParser();parser.add_argument('--live-snapshot',required=True);parser.add_argument('--update-context',action='store_true');args=parser.parse_args()
    live=json.loads(Path(args.live_snapshot).read_text(encoding='utf-8-sig'))
    if args.update_context:apply_prompts_to_context(live)
    build(live)
