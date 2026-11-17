"""Project only R10 folder 01 into the existing static deck delivery contract."""
from pathlib import Path
import argparse, collections, hashlib, json, re, shutil, unicodedata
import xml.etree.ElementTree as ET

APP=Path(__file__).resolve().parents[1]
SOURCE=APP/'context/gpt/Station_X_11_DECKS_PLANES_ESTILO_ESTRATEGIA_R10_2026-11-02/01_INFORMACION_PARA_IMPORTAR'
DEST=APP/'assets/editorial/20261102-r10'
IDS={'kashtira_pure':665,'infernoble':670,'elfnote':650,'cyber_dragon_pure':684,'volcanic':687,'doomz':653,'tearlaments_hibrido':656,'hero_elemental_rush':706,'quimicalizada_rush':704,'mago_oscuro_gaia_rush':707,'royal_rebels_kheiron_rush':705}
sha=lambda b:hashlib.sha256(b).hexdigest()
dump=lambda d:(json.dumps(d,ensure_ascii=False,indent=2)+'\n').encode()
def norm(s):
    s=re.sub(r'\s*\[[^\]]*\]\s*$','',str(s)).strip()
    return ''.join(c for c in unicodedata.normalize('NFD',s) if not unicodedata.combining(c)).casefold()
def aliases(s):return {norm(s),*(norm(v) for v in re.split(r'\s+/\s+',s))}
def inventory(rows):
    return sorted((str(c.get('section') or 'main'),norm(c['card_name']),int(c['quantity']))for c in rows)
def build(saved):
    manifest=json.loads((SOURCE/'manifest.json').read_text(encoding='utf-8-sig'))
    native={int(d['id']):d for d in saved['allDecks']}
    output={};report=[];totals=collections.Counter();states=collections.Counter();source_hashes={}
    allowed={SOURCE/'manifest.json'}
    for item in manifest['decks']:
        path=(SOURCE/item['archivo_de_resultado']).resolve();assert path.is_relative_to(SOURCE.resolve())
        allowed.add(path);d=json.loads(path.read_text(encoding='utf-8-sig'));slug=d['identificador_paquete'];did=IDS[slug]
        assert d.get('id_deck_station_x_si_conocido') in [None,did]
        format_='tcg_advanced' if d['formato']=='TCG' else 'rush_duel'
        assert format_ in native[did]['format_codes'],(did,'format')
        rows=[c for c in saved['cards'] if int(c['deck_id'])==did]
        expected=[]
        for zone,cards in d['inventario'].items():
            if cards is not None:
                expected.extend({'section':{'reserva':'reserve'}.get(zone,zone),'card_name':c['nombre'],'quantity':c['copias']}for c in cards)
        differences=[]
        for zone in ['main','extra','side','reserve']:
            source_rows=[c for c in expected if c['section']==zone];saved_rows=[c for c in rows if c['section']==zone]
            for c in source_rows:
                matches=[x for x in saved_rows if aliases(c['card_name'])&aliases(x['card_name'])]
                if len(matches)!=1 or int(matches[0]['quantity'])!=int(c['quantity']):differences.append({'zone':zone,'source':c,'matches':matches})
            if len(source_rows)!=len(saved_rows):differences.append({'zone':zone,'source_rows':len(source_rows),'saved_rows':len(saved_rows)})
        assert not differences,(did,'Inventory difference: preserve saved list; review report',differences)
        combos={c['id']:c for c in d['combos']};assert len(combos)==len(d['combos'])
        r10=d['perfil_y_plan_estrategico_R10'];scenarios=r10['escenarios_interactivos'];assert len(scenarios)==6
        for scenario in scenarios:
            for option in scenario['opciones_basadas_en_rutas_existentes']:
                c=combos[option['id_combo_existente']];assert option['estado_de_ruta']==c['estado']
                totals['scenario_links']+=1
        cards=[];metadata={}
        for c in d['cartas']:
            zone={'reserva':'reserve'}.get(c['zona'].lower(),c['zona'].lower())
            matches=[r for r in rows if r['section']==zone and aliases(r['card_name'])&aliases(c['nombre'])]
            image_id=int(matches[0].get('ygoprodeck_id') or 0) if len(matches)==1 else 0
            cards.append({'id':image_id,'name':c['nombre'],'quantity':c['copias'],'section':zone,'role':c.get('funcion_en_el_deck',''),'official_source':c.get('fuente_oficial'),'konami_cid':c.get('konami_cid'),'detail':c})
            if image_id:metadata[str(image_id)]={'id':image_id,'name':c['nombre']}
        routes=[]
        for c in d['combos']:
            states[c['estado']]+=1
            diagram=(SOURCE/c['diagrama_svg']).resolve();assert diagram.is_relative_to(SOURCE.resolve()) and diagram.is_file();allowed.add(diagram)
            tree=ET.fromstring(diagram.read_bytes())
            assert all(n.tag.split('}')[-1] not in ['script','foreignObject'] and all(not k.lower().startswith('on') and not ('href' in k and not v.startswith('#'))for k,v in n.attrib.items())for n in tree.iter())
            detail={'result':c.get('resultado_si_se_cumple','')}
            for k,v in c.items():
                if k.startswith('tactical_full_prompt_detail'):detail[k]=v
            routes.append({'id':c['id'],'title':c['titulo'],'status':c['estado'],'conditions':c.get('para_empezar',''),'steps':[{'text':s,'cards':[]}for s in c['pasos']],'startCards':[],'endCards':[],'warning':c.get('si_falla_o_te_interrumpen',''),'executable':c['estado']!='NO_EJECUTABLE','detail':detail,'diagram_url':'./assets/editorial/20261102-r10/'+c['diagrama_svg']})
        skills=[]
        if format_=='rush_duel':
            assert len(d['skills'])==1
            skill=d['skills'][0];image=(SOURCE/skill['source_image']).resolve();assert image.is_relative_to(SOURCE.resolve()) and image.is_file();allowed.add(image)
            skills=[{**skill,'image_url':'./assets/editorial/20261102-r10/'+skill['source_image']}]
            assert all(not (aliases(c['card_name'])&aliases(skill['nombre'])) for c in rows)
        binding=sha(dump(inventory(rows)))
        packet={'deckId':did,'name':d['nombre'],'format':format_,'replacement':True,'curated_r10':True,'source_sha256':sha(path.read_bytes()),'curated':d,'original_record':d,'cards':cards,'card_metadata':metadata,'combos':routes,'matchups':[{'name':m['rival'],'plan':m['plan']}for m in d.get('enfrentamientos',[])],'skills':skills,'hands':d['manos_para_practicar'],'probability':d.get('dato_de_apertura'),'topics':[],'profile':{'play_style':r10['estilo_de_juego']['clasificacion_razonada']},'list_binding_sha256':binding}
        output[f'deck-{did}.json']=dump(packet)
        for k,v in [('decks',1),('cards',len(cards)),('routes',len(routes)),('hands',len(d['manos_para_practicar'])),('chapters',len(d['apartados_del_prompt_maestro'])),('skills',len(skills)),('scenarios',len(scenarios)),('matchups',len(d.get('enfrentamientos',[]))),('ratings',len(d['evaluacion_competitiva_justificada']['dimensiones']))]:totals[k]+=v
        report.append({'deck_id':did,'platform_name':native[did]['name'],'source_name':d['nombre'],'format':format_,'inventory_unchanged':True,'list_sha256':binding,'counts':d['resumen'],'routes':len(routes),'scenarios':6})
    for path in sorted(allowed,key=str):
        rel=path.relative_to(SOURCE.resolve()) if path.is_absolute() else path.relative_to(SOURCE)
        source_hashes[rel.as_posix()]=sha(path.read_bytes())
        if rel.parts[0]=='assets':output[rel.as_posix()]=path.read_bytes()
    assert dict(totals)=={'scenario_links':150,'decks':11,'cards':390,'routes':126,'hands':110,'chapters':396,'skills':4,'scenarios':66,'matchups':42,'ratings':55},totals
    assert sum(n.endswith('.svg')for n in output)==126
    digest=sha(dump(source_hashes))
    output['manifest.json']=dump({'version':'20261102-r10-128','source_sha256':digest,'source_files':source_hashes,'counts':dict(totals),'route_states':dict(states),'certified_routes':0,'decks':report})
    index={'version':'20261102-r10-128','source_sha256':digest,'replace_not_merge':True,'decks':{str(r['deck_id']):{'url':f"./assets/editorial/20261102-r10/deck-{r['deck_id']}.json?v={sha(output[f'deck-{r["deck_id"]}.json'])[:16]}",'name':r['source_name'],'source_key':r['deck_id']}for r in report}}
    return output,index

if __name__=='__main__':
    parser=argparse.ArgumentParser();parser.add_argument('--inventory',required=True);parser.add_argument('--check',action='store_true');args=parser.parse_args()
    files,index=build(json.loads(Path(args.inventory).read_text(encoding='utf-8-sig')))
    for name,body in files.items():
        path=DEST/name
        if args.check:assert path.read_bytes()==body,name
        else:path.parent.mkdir(parents=True,exist_ok=True);path.write_bytes(body)
    path=APP/'assets/editorial/deck-deliveries.json'
    if args.check:assert path.read_bytes()==dump(index)
    else:path.write_bytes(dump(index))
    print(json.dumps({'ok':True,'check':args.check,'files':len(files),'counts':json.loads(files['manifest.json'])['counts']}))
