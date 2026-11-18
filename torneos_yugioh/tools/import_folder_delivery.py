"""Import owner-approved per-deck folders, preserving native IDs and revisions.

Reads the current public snapshot and editor feed from the private output folder.
Only the two explicitly confirmed empty lists may be created through the native API.
"""
import copy
import hashlib
import html
import json
import os
import re
import shutil
from pathlib import Path

APP = Path(__file__).resolve().parents[1]
ROOT = APP.parent
PROMPTS = Path(os.environ.get('YGO_PROMPTS_ROOT', str(ROOT.parent / 'gpt-yugioh-promps-platform'))).resolve()
SOURCE = PROMPTS / '01_INFORMACION_PARA_IMPORTAR'
OUT = ROOT / os.environ.get('YGO_IMPORT_OUTPUT','outputs/import-20261116-v31')
VERSION = os.environ.get('YGO_IMPORT_VERSION','20261116-folder-delivery-v31')
TARGET = APP / 'assets/editorial' / VERSION
CONFIRMED = {'NUEVO_12': (671, 'Memento'), 'NUEVO_13': (677, 'Crystron')}
_sync_state=APP/'context/editorial-sync-state.json'
if _sync_state.exists():
    CONFIRMED.update(json.loads(_sync_state.read_text(encoding='utf-8-sig')).get('confirmed_folder_bindings',{}))
if (OUT/'bindings.json').exists():
    CONFIRMED.update(json.loads((OUT/'bindings.json').read_text(encoding='utf8')))
IMAGE_INDEX = {}


def asset_image(path, fallback):
    digest=hashlib.sha256(path.read_bytes()).hexdigest()
    dest=IMAGE_INDEX.get(digest,fallback)
    if not dest.exists():dest.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(path,dest)
    IMAGE_INDEX[digest]=dest
    return dest


def read(p):
    return json.loads(p.read_text(encoding='utf-8-sig'))


def write(p, value):
    p.parent.mkdir(parents=True, exist_ok=True)
    raw = (json.dumps(value, ensure_ascii=False, indent=2) + '\n').encode('utf8')
    p.write_bytes(raw)
    return hashlib.sha256(raw).hexdigest()


def calculator_contract(raw, data, deck_id):
    """Import declared data only; the browser validates every operation before execution."""
    found = []
    for block in re.findall(r'```json\s*\n(.*?)\n```', raw, re.S):
        try:
            value = json.loads(block)
        except ValueError:
            if 'station-x-calculator/1' in block:
                raise ValueError('Malformed calculator JSON; do not discard it silently')
            continue
        if not isinstance(value, dict) or value.get('schema') != 'station-x-calculator/1':
            continue
        assert len(block.encode('utf8')) <= 131072
        assert value.get('deck_id') == deck_id and value.get('format') == data['format']
        assert isinstance(value.get('rules'), list) and len(value['rules']) <= 100
        ids = set()
        for rule in value['rules']:
            assert rule.get('version') == 1 and rule.get('deckId') == deck_id and rule.get('format') == data['format']
            assert isinstance(rule.get('id'), str) and rule['id'] not in ids
            assert rule.get('actor', {}).get('cid') in {c['cid'] for c in data['cards']}
            assert all(isinstance(rule.get(k), list) for k in ('guards', 'select', 'costs', 'resolve', 'tags', 'assumptions'))
            ids.add(rule['id'])
        found.append(value)
    assert len(found) <= 1, 'Duplicate calculator contract'
    if found:
        data['calculator'] = found[0]
    return data


def plain(s):
    return re.sub(r'[*`]', '', s).strip()


def sections(raw, level):
    return [(m[1], m[2]) for m in re.finditer(rf'^{"#"*level} (.*?)\n(.*?)(?=^#{{1,{level}}} |\Z)', raw, re.M | re.S)]


def fields(raw):
    return {plain(m[1]).rstrip(':'): plain(m[2]) for m in re.finditer(r'^- \*\*(.*?)\*\*[:：]?\s*(.*?)(?=^- \*\*|\n\n|\Z)', raw, re.M | re.S)}


def new_inventory(file):
    raw = file.read_text(encoding='utf8')
    prefix = 'MEMD' if 'NUEVO_12' in file.name else 'CRYH'
    result = []
    # The author's tables wrap cells over multiple physical lines.
    for m in re.finditer(r'^\| '+prefix+r'-(\d{3})\s*(.*?)\|\s*\n(?=\||\n|\Z)', raw, re.M | re.S):
        cells = [' '.join(x.split()) for x in (prefix+'-'+m[1]+m[2]).split('|')]
        cid = int(re.search(r'cid=(\d+)', m[0])[1])
        if prefix == 'MEMD':
            cell = re.search(r'(.+?)\s*[·]\s*(\d+)/(\d+)/(\d+)/(\d+)', cells[1])
            assert cell, cells
            name = plain(cell[1]); quantities = list(map(int, cell.groups()[1:]))
            description = cells[2]
        else:
            name = plain(cells[1]); quantities = list(map(int, cells[2:6])); description = cells[7]
        for zone, n in zip(('main','extra','side','reserve'), quantities):
            if n:
                result.append(dict(card_name=name, card_id=0, section=zone, quantity=n, role='',
                    notes=json.dumps({'konami_cid':cid,'source_document':file.name,'source_identity':prefix+'-'+m[1]}, ensure_ascii=False),
                    cid=cid, description=description))
    assert len({r['cid'] for r in result}) == 39, (file, len(result))
    expected = (41,15,14,0) if prefix == 'MEMD' else (40,15,15,0)
    assert tuple(sum(r['quantity'] for r in result if r['section']==z) for z in ('main','extra','side','reserve')) == expected
    return result


def plans():
    result={}
    for ref,(id,name) in CONFIRMED.items():
        file=next((SOURCE/'decks').glob(f'DECK_{ref}_*/*.md'))
        result[str(id)]={'name':name,'file':str(file.relative_to(PROMPTS)), 'cards':new_inventory(file)}
    write(OUT/'inventory-plan.json',result)
    return result


def build():
    TARGET.mkdir(parents=True,exist_ok=True)
    for image in (APP/'assets/editorial/20261104-checked-study/images').rglob('*'):
        if image.is_file():IMAGE_INDEX[hashlib.sha256(image.read_bytes()).hexdigest()]=image
    live=read(OUT/'live.json'); registry=read(OUT/'before/assets/editorial/strategy-content-state.json')
    for entry in registry['checked_studies'].values():
        previous=read(APP/entry['path'].removeprefix('./'))
        for card in previous['cards']:
            for url in (card.get('image',''),card.get('full_image','')):
                image=APP/url.removeprefix('./')
                if url and image.is_file():IMAGE_INDEX[hashlib.sha256(image.read_bytes()).hexdigest()]=image
    feed=read(OUT/'editor-feed.json'); assert feed['ok'] and feed['schema']=='station-x-combo-source/1'
    overrides={(r['deck_id'],r['route_id']):r for r in feed['records']}
    classification={(r['deck_id'],r['cid']):r for r in read(SOURCE/'A06_MAPA_370_ACTUALIZADO_V28.json')['fichas']}
    official={(r['deck_id'],r['cid']):r for r in read(SOURCE/'FICHAS_A05_370_COTEJO_OFICIAL_V22.json')['fichas']}
    extra=read(SOURCE/'RUTAS_EXTRA_28_CASOS_V13.json')['rutas']
    extra_review={r['documento_ruta']:r for r in read(SOURCE/'A07_15_CONFLICTOS_EXTRA_ESTADO_V28.json')['casos']}
    report=[]; manifest={'version':VERSION,'decks':{}}; exported={};skipped=[]
    for file in sorted((SOURCE/'decks').glob('DECK_*/*.md')):
        raw=file.read_text(encoding='utf8'); ref=re.search(r'DECK_(NUEVO_\d+|\d+)_',file.name)[1]
        if ref.startswith('NUEVO_') and ref not in CONFIRMED:
            skipped.append({'reference':ref,'reason':'Destination pending owner confirmation'});continue
        id,name=CONFIRMED[ref] if ref in CONFIRMED else (int(ref),None)
        native=next(d for d in live['allDecks'] if int(d['id'])==id)
        if name: assert native['name']==name and any(f in native['format_codes'] for f in ('tcg_advanced','goat'))
        rows=[c for c in live['cards'] if int(c['deck_id'])==id]
        assert rows,(id,'native list missing')
        old_entry=registry['checked_studies'].get(str(id))
        if old_entry:
            data=read(APP/old_entry['path'].removeprefix('./'))
            assert data['name']==native['name'] and data['format'] in native['format_codes']
            assert sorted((s['card_id'],s['section'],s['quantity']) for c in data['cards'] for s in c['slots'])==sorted((int(c['id']),c['section'],int(c['quantity'])) for c in rows)
        else:
            data=copy.deepcopy(read(APP/'assets/editorial/20261114-delivery-v21/650.json'))
            for k,v in list(data.items()):
                if isinstance(v,list):data[k]=[]
                elif isinstance(v,dict):data[k]={}
            data.update(deck_id=id,name=native['name'],format=native['format_codes'][0],profile={},summary='',motor='',cards=[],routes=[])
            for optional in ('optimization','improvement','opponent_scenarios','delivery_dossier'):
                data.pop(optional,None)
            data.pop('delivery_v21',None);data.pop('a01_a07_revision',None);data.pop('delivery_progress',None)
            gallery=gallery_cards(file)
            identities={int(c['id']):native_cid(c,gallery) for c in rows}
            for cid in sorted(set(identities.values())):
                own=[c for c in rows if identities[int(c['id'])]==cid]
                photo=source_photo(file,cid,gallery)
                image=''
                if photo and photo.exists():
                    dest=asset_image(photo,TARGET/'images'/str(id)/photo.name);image='./'+dest.relative_to(APP).as_posix()
                c=own[0]
                data['cards'].append(dict(id=f'{id}:CARD:{cid}',cid=cid,name=c['card_name'],image=image,full_image=image,
                    slots=[dict(section=r['section'],quantity=int(r['quantity']),card_id=int(r['id'])) for r in own],
                    fields=[],practical=[],operational={},learning={},functional={},role='',synergy={'labels':[],'resource':'','relation':''},sources=[f'https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid={cid}&ope=2&request_locale=es']))
        cards={c['cid']:c for c in data['cards']}
        gallery=gallery_cards(file)
        for cid,c in cards.items():
            photo=source_photo(file,cid,gallery)
            if photo:
                dest=asset_image(photo,TARGET/'images'/str(id)/photo.name)
                previous_full=c.get('full_image','')
                c['image']='./'+dest.relative_to(APP).as_posix()
                c['full_image']=previous_full if previous_full and (APP/previous_full.removeprefix('./')).is_file() else c['image']
        parts=sections(raw,2)
        profile=next((b for h,b in parts if h.startswith('PERFIL BREVE')),None)
        if profile:data['profile']=fields(profile)
        # Official CID identity, never API image IDs or approximate names.
        for heading,body in sections(raw,4):
            if not re.match(r'\d+\.',heading):continue
            p=re.search(r'!\[[^\]]*\]\([^)]*/(\d+)\.\w+\)',body)
            if not p:continue
            cid=int(p[1]); assert cid in cards,(id,cid)
            c=cards[cid];c['source_name']=plain(re.sub(r'^\d+\.\s*','',heading));c['source_fields']=[{'title':k,'text':v} for k,v in fields(body).items()]
            c['source_text']=body
        for c in cards.values():
            names=[c['name'],c.get('source_name','')]
            names.extend(re.sub(r'^Salamangrande\s+|\s+(?:Purrely|Epurrely|Expurrely)$','',n,flags=re.I) for n in list(names))
            c['source_aliases']=list(dict.fromkeys(n for n in names if len(n)>3))
        for m in re.finditer(r'^-\s+([^\n:]+):\s*https://www\.db\.yugioh-card\.com/[^\n]*?cid=(\d+)',raw,re.M):
            cid=int(m[2])
            if cid in cards:cards[cid]['source_aliases'].append(plain(m[1]))
        for cid,c in cards.items():
            if (id,cid) in official:
                f=official[id,cid];c['official_review']=f
                replacements={'Efecto':f.get('descripcion_a05_previa'),'Ventanas y requisitos':f.get('ventana_previa'),'Coste':f.get('coste_previo'),'Al jugar primero':f.get('ir_primero'),'Al jugar segundo':f.get('ir_segundo'),'Ante interrupciones':f.get('intervencion')}
                for field in c['fields']:
                    if replacements.get(field['title']):field['text']=replacements[field['title']]
                c['delivery_fields'].update({k:v for k,v in replacements.items() if v})
            if (id,cid) in classification:
                row=classification[id,cid];c['current_a06']=row
                label={'SINERGIA_DE_ENGINE_DOCUMENTADA':'Sinergia directa','SINERGIA_CONDICIONAL_NO_VERIFICADA':'Sinergia condicionada'}.get(row['grado'],'Sinergia condicionada' if not row['acceso_extra_no_acreditado'] else 'Acceso por documentar')
                c['synergy']['labels']=[label]
        updated=[]
        byid={r['id']:r for r in data['routes']}
        def photos(text):
            ids=list(dict.fromkeys(int(x) for x in re.findall(r'(?:CID\s*|#|/)(\d{4,6})(?=\b|\.)',text)))
            aliases={}
            for cid,c in cards.items():
                for name in c.get('source_aliases',[c['name']]):
                    for alias in name.split(' / '):
                        if len(alias)>3:aliases.setdefault(alias.casefold(),set()).add(cid)
            for alias,bound in aliases.items():
                if len(bound)==1 and re.search(r'(?<!\w)'+re.escape(alias)+r'(?!\w)',plain(text).casefold()):
                    cid=next(iter(bound))
                    if cid not in ids:ids.append(cid)
            return [dict({k:copy.deepcopy(cards[cid][k]) for k in ('cid','name','image','full_image')},label='Carta mencionada en el paso') for cid in ids if cid in cards and cards[cid]['image']]
        def route_update(rid,title,body):
            r=byid.get(rid);new=r is None
            if new:r=dict(id=rid,name=title,title=title,family='Desarrollo de la jugada',purpose='',category='APERTURA',board=[],resources=[],hand_photos=[],result='',opening='',steps=[]);data['routes'].append(r);byid[rid]=r
            r['documentary_source']=body
            steps=[dict(number=int(m[1]),action=plain(m[2]),detail=plain(m[2]),photos=photos(m[2]),requirements=[],resources=[],board=[]) for m in re.finditer(r'^(\d+)\.\s+(.*?)(?=^\d+\. |^\| |^### |\n\n|\Z)',body,re.M|re.S)]
            if steps and [s['number'] for s in steps]==list(range(1,len(steps)+1)):
                oldsteps=r['steps'];r['steps']=steps
                for step,old in zip(steps,oldsteps):
                    if plain(old['action'])==step['action']:
                        for k in ('photos','resources','board'):step[k]=copy.deepcopy(old.get(k,step[k]))
                r['opening']=plain(body[:re.search(r'^1\. ',body,re.M).start()]);r['initial_details']=r['opening'];r['hand_photos']=photos(r['opening'])
                final=re.search(r'(?:\*\*(?:Campo final[^:]*|Final|Tablero final[^:]*|Resultado|Salida)[^\n]*)(.*?)(?=\n\n|\Z)',body,re.S)
                if final:r['result']=plain(final[0]);r.pop('endboard',None);r['board']=[];r['resources']=[]
                objective=re.search(r'^- \*\*Objetivo:\*\*\s*(.*)',body,re.M)
                if objective:r['purpose']=plain(objective[1])
                if new:r.update(editorial_status='pending',notice='Secuencia documental condicionada al estado descrito. No es una simulación de partida.')
                updated.append(rid)
            r['sources']=sorted(set(re.findall(r'https://www\.db\.yugioh-card\.com/[^\s)]+',body)))
            return r
        if ref in ('NUEVO_12','NUEVO_13'):
            for title,body in sections(raw,3)+sections(raw,4):
                if not re.search(r'^1\. ',body,re.M):continue
                match=re.match(r'(?:MEMD-)?(C\d{2}|V\d{2})\b',title)
                if match:
                    r=route_update(f'{id}:A09:{match[1]}',plain(title),body)
                    r['technical']=plain(body)
                    if match[1].startswith('V'):r['editorial_status']='conditional'
            inv=new_inventory(file)
            for c in data['cards']:
                row=next(r for r in inv if r['cid']==c['cid']);c['role']=row['description'];c['fields']=[{'title':'Qué hace en este deck','text':row['description']}];c['functional']={'purpose':row['description'],'explanation':row['description']}
            first=next((b for h,b in parts if 'APARTADO_01' in h),'')
            intro=plain(first.split('\n\n')[0])
            data['profile']={'Arquetipo':raw.splitlines()[0].lstrip('# '),'Tipo de estrategia':'Consultar el plan de juego y las condiciones de cada línea.','Estilo de juego':intro,'Cómo se juega':plain(first)}
        elif ref in CONFIRMED:
            common='\n\n'.join(body for title,body in sections(raw,3) if title.lower().startswith('variantes'))
            for title,body in sections(raw,3):
                match=re.match(r'([PSG]-C\d{2})\b',title)
                if match:
                    lesson_title=re.sub(r'^[PSG]-C\d{2}\s*[—-]\s*','',plain(title))
                    r=route_update(f'{id}:A09:{match[1]}',lesson_title,body)
                    r['technical']=plain(body)+'\n\n'+plain(common)
                    r['purpose']=next((v for k,v in fields(body).items() if re.search('Objetivo|Propósito',k,re.I)),r['title'])
            first=next((b for h,b in parts if re.match(r'A01\s*[—.-]',h)),'')
            data['profile']={'Arquetipo':native['name'],'Estilo de juego':plain(first.split('\n\n')[0]),'Cómo se juega':plain(first)}
            # Exact table-name matches only; no approximate archetype binding.
            for line in raw.splitlines():
                cells=[plain(c) for c in line.strip('|').split('|')]
                if not line.startswith('|') or len(cells)<3:continue
                label=re.sub(r'\s*[×x]\s*\d+.*$','',cells[0]).strip()
                matched=[c for c in cards.values() if label.casefold() in {n.casefold() for n in c.get('source_aliases',[c['name']])}]
                if len(matched)==1:
                    c=matched[0];c['role']=cells[1];c['fields']=[{'title':'Función en este deck','text':cells[1]},{'title':'Cómo usarla y qué vigilar','text':cells[2]}]
                    c['practical']=copy.deepcopy(c['fields']);c['functional']={'purpose':cells[1],'explanation':cells[2]}
        else:
            for title,body in sections(raw,4):
                match=re.search(r'`(\d+:A09:[^`]+)`',title)
                if match:route_update(match[1],plain(title),body)
        for x in extra:
            if x['deck_id']!=id:continue
            review=extra_review.get(x['id']);validation=review.get('validacion',{}) if review else {}
            steps=validation.get('pasos') or [p['accion']+' '+p.get('resultado','') for p in x['pasos']]
            body='Estado inicial: '+validation.get('condicion','; '.join(x['mano_inicial']))+'\n\n'+'\n'.join(f'{n}. {s}' for n,s in enumerate(steps,1))
            r=route_update(x['id'],plain(x['objetivo']),body);r['title']=plain(x['objetivo'])[:160];r['purpose']=x['objetivo'];r['extra_source']=x;r['extra_review']=review
            r['result']=validation.get('resultado',x.get('campo_final','Resultado condicionado a los requisitos de la ruta.'))
            if not isinstance(r['result'],str):r['result']=json.dumps(r['result'],ensure_ascii=False)
            r['notice']='Ruta condicional cotejada documentalmente; no ejecutada en simulador.' if review else 'Ruta documental: revisar requisitos y restricciones antes de usarla.'
            r['editorial_status']='conditional' if review else 'pending'
            r['sources']=validation.get('fuentes',[])
            r['final_constraints']=x.get('restricciones_y_ventanas',[])
            r['technical']='\n\n'.join(label+': '+(value if isinstance(value,str) else json.dumps(value,ensure_ascii=False)) for label,value in (
                ('Costes y recursos consumidos',x.get('costes_y_pagados',[])),
                ('Restricciones y ventanas',x.get('restricciones_y_ventanas',[])),
                ('Respuesta a interrupciones',x.get('respuesta_a_interrupciones','')),
                ('Variante o plan alternativo',x.get('variante_o_plan_b','')),
                ('Fuentes',x.get('cita_oficial',''))) if value)
        # Preserve each new rule check with the exact original route ID. These
        # are authored documentary conditions, never a simulated certification.
        data['documentary_reviews']=[]
        for title,body in sections(raw,4):
            check=re.match(r'((?:[PSG]-)?[CV]\d{2})\s*·\s*verificación',title,re.I)
            if not check:continue
            row={'id':check[1],'conditions':fields(body),'text':plain(body),'sources':sorted(set(re.findall(r'https://www\.db\.yugioh-card\.com/[^\s)]+',body)))}
            data['documentary_reviews'].append(row)
            r=byid.get(f'{id}:A09:{check[1]}')
            if r:
                r['documentary_review']=row
                r['technical']=r.get('technical','')+'\n\nCondiciones y límites de esta secuencia\n'+row['text']
                r['sources']=sorted(set(r.get('sources',[])+row['sources']))
        data['source_format_checks']=[]
        for line in raw.splitlines():
            if not line.startswith('|') or 'cid=' not in line:continue
            cells=[v.strip() for v in line.strip('|').split('|')]
            if len(cells)!=5 or not re.fullmatch(r'\d+/\d+/\d+/\d+',cells[2]):continue
            match=re.search(r'cid=(\d+)',cells[0])
            if match and int(match[1]) in cards:
                row={'cid':int(match[1]),'format':data['format'],'source_status':plain(cells[3]),'source_excess':plain(cells[4]),'scope':'DOCUMENTARY_SOURCE_NOT_SIMULATED'}
                data['source_format_checks'].append(row)
                if re.search(r'[1-9]',row['source_excess']):
                    c=cards[row['cid']];message=f"El estudio señala {row['source_status']}; copias en exceso según esa fuente: {row['source_excess']}. El inventario se conserva."
                    c['fields']=[f for f in c['fields'] if f['title']!='Restricción documental del formato']+[{'title':'Restricción documental del formato','text':message}]
                    c.setdefault('delivery_fields',{})['Restricción documental del formato']=message
        apply_v40(data,raw)
        for r in data['routes']:
            refresh_photos(r,cards)
            saved=overrides.get((id,r['id']))
            if saved:
                r.update(native_editor_document=saved['document'],native_editor_cards=saved['cards_snapshot'],native_editor_revision=saved['revision'],title=saved['document']['title'],purpose=saved['document']['objective'])
        # Complete authored player content is available, including tables and
        # variants not yet expressible in the native graph, without internal QA.
        player=[]
        for title,body in parts:
            if re.search(r'CONTROL INDIVIDUAL|EJECUCIÓN DOCUMENTAL|AVANCE A01|PROGRESO|TABLERO DE AVANCE|ESTADO DE LOS DIEZ|CUADRO A01|CONTROLES ANTES|FIN Y CONTROL|CIERRE DE ITERACIÓN',title,re.I):continue
            player.append((title,body))
        data['folder_delivery']={'source_sha256':hashlib.sha256(file.read_bytes()).hexdigest(),'reader':'./'+(TARGET/f'{id}-guide.html').relative_to(APP).as_posix(),'source_repository':'gpt-yugioh-promps-platform','source_file':file.relative_to(PROMPTS).as_posix()}
        page=render_page(data['name'],player,file)
        (TARGET/f'{id}-guide.html').write_text(page,encoding='utf8')
        data=calculator_contract(raw,data,id)
        digest=write(TARGET/f'{id}.json',data)
        manifest['decks'][str(id)]={'path':f'./assets/editorial/{VERSION}/{id}.json','cards':len(data['cards']),'routes':len(data['routes']),'sha256':digest,'presentation':'curated-v62'}
        report.append({'id':id,'name':data['name'],'cards':len(data['cards']),'routes':len(data['routes']),'updated_routes':len(set(updated)),'native_revisions_preserved':sum((id,r['id']) in overrides for r in data['routes'])})
        exported[id]=data
    registry.update(version=VERSION,checked_studies={**registry['checked_studies'],**manifest['decks']})
    write(TARGET/'manifest.json',manifest);write(APP/'assets/editorial/strategy-content-state.json',registry)
    write(OUT/'report.json',{'version':VERSION,'decks':report,'skipped':skipped,'native_lists_created':[],'existing_inventories_changed':False,'sporting_writes':0})
    print(json.dumps(report))


def apply_v40(data,raw):
    """Import authored V40 tables without turning draw probabilities into wins."""
    if '## CORTE V40' not in raw:return
    block=raw.split('## CORTE V40',1)[1]
    data['delivery_v40']={'opening':[],'extra_access':[],'situations':[],'scope':'Documentary analysis; no simulator execution or inventory changes.'}
    data['decisions']=[d for d in data.get('decisions',[]) if ':V40:TACTIC:' not in d['id']]
    for heading,body in sections(block,3):
        rows=[]
        for line in body.splitlines():
            if not line.startswith('|') or re.match(r'^\|[\s|:-]+$',line):continue
            rows.append([plain(c) for c in line.strip('|').split('|')])
        rows=rows[1:]
        if heading.startswith('A01'):
            for cells in rows:
                assert len(cells)==3,cells
                data['delivery_v40']['opening'].append(dict(event=cells[0],probability=cells[1],interpretation=cells[2]))
        elif heading.startswith('A03'):
            for cells in rows:
                assert len(cells)==3,cells
                cid=int(re.search(r'cid=(\d+)',cells[0])[1]);card=next(c for c in data['cards'] if c['cid']==cid)
                row=dict(cid=cid,materials=cells[1],purpose=cells[2]);data['delivery_v40']['extra_access'].append(row)
                labels={'Materiales y acceso al Extra (V40)':cells[1],'Para qu\u00e9 utilizarla (V40)':cells[2]}
                card['fields']=[f for f in card['fields'] if f['title'] not in labels]+[dict(title=k,text=v) for k,v in labels.items()]
        elif heading.startswith('A08'):
            for n,cells in enumerate(rows,1):
                assert len(cells) in (4,5),cells
                if len(cells)==4:cells.append('')
                row=dict(situation=cells[0],first=cells[1],second=cells[2],side=cells[3],risk=cells[4]);data['delivery_v40']['situations'].append(row)
                data['decisions'].append(dict(id=f"{data['deck_id']}:V40:TACTIC:{n}",title=cells[0],situation=cells[0],
                    options=[dict(id='A',text='Me toca empezar'),dict(id='B',text='Juego segundo')],
                    answer='Si empiezas: '+cells[1]+'\n\nSi vas segundo: '+cells[2]+'\n\nSide documentado: '+cells[3],error=cells[4],routes=[]))
    assert len(data['delivery_v40']['opening'])==8 and len(data['delivery_v40']['situations'])==6


def gallery_cards(file):
    gallery=file.parent/'guias/GUIA_VISUAL_A01_A10.html'
    if not gallery.exists():return []
    raw=gallery.read_text(encoding='utf8');found=[]
    for m in re.finditer(r'<article class="item">(.*?)</article>',raw,re.S):
        cid=re.search(r'cid=(\d+)',m[1]);name=re.search(r'<strong>(.*?)</strong>',m[1],re.S);image=re.search(r'<img[^>]*src="([^"]+)"',m[1])
        if cid and name and image:
            found.append({'cid':int(cid[1]),'name':html.unescape(name[1]),'image':(gallery.parent/html.unescape(image[1])).resolve()})
    return found


def native_cid(row,gallery):
    notes=json.loads(row.get('notes') or '{}')
    if notes.get('konami_cid'):return int(notes['konami_cid'])
    match={r['cid'] for r in gallery if r['name'].casefold()==row['card_name'].casefold()}
    assert len(match)==1,('Unresolved exact native identity',row['deck_id'],row['card_name'])
    return next(iter(match))


def source_photo(file,cid,gallery):
    paths=[r['image'] for r in gallery if r['cid']==cid]
    paths+=list((file.parent/'imagenes').rglob(f'{cid}.*'))
    paths=[p.resolve() for p in paths if p.is_file() and p.resolve().is_relative_to(SOURCE.resolve())]
    return paths[0] if paths else None


def refresh_photos(value,cards):
    if isinstance(value,list):
        for item in value:refresh_photos(item,cards)
    elif isinstance(value,dict):
        if 'image' in value:
            cid=value.get('cid');match=re.search(r'(\d+)\.(?:jpg|png|webp)$',str(value.get('image','')))
            if cid is None and match:cid=int(match[1])
            if cid in cards and cards[cid]['image']:
                value['image']=cards[cid]['image'];value['full_image']=cards[cid]['full_image']
        for key,item in value.items():
            if key not in ('native_editor_document','native_editor_cards'):refresh_photos(item,cards)


def render_page(name,parts,file):
    def inline(value):
        value=html.escape(value)
        def image(m):
            path=(file.parent/html.unescape(m[2])).resolve()
            if not path.is_relative_to(SOURCE.resolve()) or not path.is_file():return '<span>Imagen pendiente: '+m[1]+'</span>'
            dest=asset_image(path,TARGET/'reader-images'/hashlib.sha256(path.read_bytes()).hexdigest()[:16]/path.name)
            url=html.escape(os.path.relpath(dest,TARGET).replace('\\','/'))
            return '<a href="'+url+'"><img loading="lazy" alt="'+m[1]+'" src="'+url+'"></a>'
        value=re.sub(r'!\[([^\]]*)\]\(([^)]+)\)',image,value)
        value=re.sub(r'\[([^\]]+)\]\((https://[^)]+)\)',r'<a href="\2" target="_blank" rel="noopener">\1</a>',value)
        value=re.sub(r'\*\*(.+?)\*\*',r'<strong>\1</strong>',value)
        value=re.sub(r'`([^`]+)`',r'<code>\1</code>',value)
        return value
    def markdown(raw):
        raw=re.sub(r'(^\|[^\n]*)(?:\n(?!\||\n)([^\n]+))+',lambda m:' '.join(m[0].splitlines()),raw,flags=re.M)
        blocks=[];table=False
        for line in raw.splitlines():
            if line.startswith('|'):
                if re.match(r'^\|[\s|:-]+$',line):continue
                if not table:blocks.append('<div class="table"><table>');table=True
                blocks.append('<tr>'+''.join('<td>'+inline(c.strip())+'</td>' for c in line.strip('|').split('|'))+'</tr>');continue
            if table:blocks.append('</table></div>');table=False
            head=re.match(r'^(#{1,6}) (.*)',line)
            if head:blocks.append('<h3>'+inline(head[2])+'</h3>')
            elif line.strip():blocks.append('<p>'+inline(line)+'</p>')
        if table:blocks.append('</table></div>')
        return ''.join(blocks)
    return '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'+html.escape(name)+'</title><style>body{background:#081526;color:#e6efff;font:17px/1.6 system-ui;margin:auto;max-width:1100px;padding:20px}a{color:#54e1ff}summary{cursor:pointer;font-weight:700;padding:16px}details{border:1px solid #31536b;border-radius:12px;margin:16px 0;padding:10px}img{max-width:180px;height:auto;vertical-align:middle}td{padding:12px;border:1px solid #31536b;min-width:140px}.table{overflow:auto}table{border-collapse:collapse}p{overflow-wrap:anywhere}h1{font-size:2rem}code{white-space:normal}</style><h1>'+html.escape(name)+'</h1><p>Guía del deck. Las jugadas conservan sus condiciones y límites documentados.</p>'+''.join('<details><summary>'+html.escape(title)+'</summary>'+markdown(body)+'</details>' for title,body in parts)+'</html>'


if __name__=='__main__':
    import sys
    if '--plan' in sys.argv:plans();print('Inventory plan: 2 confirmed decks, 78 identities.')
    else:build()
