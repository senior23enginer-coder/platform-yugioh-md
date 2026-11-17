"""Reconstruct documented end states; do not execute effects or change lists."""
import copy
import json
import re
import unicodedata
from collections import Counter
from pathlib import Path

APP = Path(__file__).resolve().parents[1]
ZONE_KEYS = ['monster','spell','field','hand','graveyard','banished','deck','extra']
FIELD_CIDS = {18204,18844}
LINK_CIDS = {13500,14676,19188,13841,18835,13758,13502,20787,14710,18448,
             13601,15741,13600,13346,14338,14133,19507}


def fold(value):
    value = ''.join(c for c in unicodedata.normalize('NFKD',value).casefold() if not unicodedata.combining(c))
    return re.sub(r'[^a-z0-9]+',' ',value).strip()


def apply_endboards(data):
    bindings=json.loads((APP/'assets/editorial/endboard-bindings.json').read_text(encoding='utf-8'))
    deck_id=data['deck_id'];cards={c['cid']:c for c in data['cards']}
    aliases={fold(name):cid for name,cid in bindings['aliases'].get(str(deck_id),{}).items()}
    for c in cards.values():
        for name in [c['name'],re.split(r'\[|\(',c['name'])[0]]:
            aliases.setdefault(fold(name),c['cid'])
    # Photo aliases are already bound by CID. Only unique non-generic names
    # can identify an unnumbered reference in an A10 resource table.
    offered={}
    source=APP/'context/gpt/01_INFORMACION_A_IMPORTAR/guias_a10'
    for file in source.glob('*.md'):
        if not file.name.startswith(f'DECK_{deck_id}_') and '665_670_687' not in file.name and '684_687' not in file.name:continue
        raw=file.read_text(encoding='utf-8')
        for name,cid in re.findall(r'!\[([^\]]+)\]\([^)]*/(\d+)\.(?:jpg|png)\)',raw):
            cid=int(cid);name=fold(name)
            if cid in cards and len(name)>3 and not re.search(r'carta|contexto|material|destino',name):offered.setdefault(name,set()).add(cid)
    for name,ids in offered.items():
        if len(ids)==1:aliases.setdefault(name,next(iter(ids)))

    def refs(raw):
        found=[]
        for m in re.finditer(r'#(\d+)',raw):
            cid=int(m[1]);assert cid in cards,(deck_id,'unbound CID',cid,raw)
            prefix=re.split(r'[,;]',raw[:m.start()])[-1]
            multiple=re.search(r'(\d+)\s*[×x]',prefix)
            found.extend([cid]*(int(multiple[1]) if multiple else 1))
        seen=set(found);text=fold(raw);occupied=[]
        # Numbered table rows enumerate identities; a name in a subsequent
        # effect/statistic explanation is not an additional card in that zone.
        for name,cid in ([] if found else sorted(aliases.items(),key=lambda p:-len(p[0]))):
            for m in re.finditer(r'(?<!\w)'+re.escape(name)+r'(?!\w)',text):
                if any(m.start()<b and m.end()>a for a,b in occupied):continue
                occupied.append((m.start(),m.end()))
                if cid not in seen:found.append(cid);seen.add(cid)
        result=[]
        for cid,count in Counter(found).items():
            if len(found)==1:
                q=re.match(r'\s*(\d+)\s*[×x]',raw)
                if q:count=int(q[1])
            result.append({'cid':cid,'quantity':count})
        return result

    def text_clean(value):
        value=re.sub(r'#\d+','',value)
        value=re.sub(r'\b(?:CID|ID)\s*\d+','',value)
        return re.sub(r'\s{2,}',' ',value).strip(' ;,')

    def documented_position(cid,route,allow_steps):
        names=[name for name,value in aliases.items() if value==cid and len(name)>3]
        sources=[(route['result'],True)]
        if allow_steps:sources.extend((s['action'],False) for s in reversed(route['steps']))
        for raw,final in sources:
            raw=''.join(c for c in unicodedata.normalize('NFKD',raw).casefold() if not unicodedata.combining(c))
            for name in sorted(names,key=len,reverse=True):
                pattern=r'(?<!\w)'+r'\W+'.join(map(re.escape,name.split()))+r'(?!\w)'
                for m in re.finditer(pattern,raw):
                    tail=re.split(r'[.;\n+→]',raw[m.end():m.end()+110])[0]
                    pos=re.search(r'\b(?:en\s+)?(?:posicion\s+(?:de\s+)?)?(ataque|defensa)\b',tail)
                    if not pos:continue
                    before=tail[:pos.start()]
                    if any(other!=cid and re.search(r'(?<!\w)'+r'\W+'.join(map(re.escape,alias.split()))+r'(?!\w)',before)
                           for alias,other in aliases.items() if len(alias)>3):continue
                    prefix=raw[max(0,m.start()-90):m.start()]
                    if not final and (not re.search(r'invoc|summon|reviv|permane|entra|queda|regresa',prefix) or re.search(r'no invoc|puede|podria',prefix)):continue
                    return 'Ataque' if pos[1]=='ataque' else 'Defensa'
        return None

    def entry(spec):
        spec={'cid':spec} if isinstance(spec,int) else copy.deepcopy(spec)
        if 'cid' in spec:
            assert spec['cid'] in cards,(deck_id,spec)
            c=cards[spec['cid']]
            spec.update({k:c[k] for k in ['name','image','full_image'] if k in c})
            spec['name']=re.sub(r'\s*\[(?:[^\]]*Konami[^\]]*|[^\]]*texto fotografiado[^\]]*)\]|\s*\(Konami ID \d+\)','',spec['name'])
        else:
            spec['name']=spec.pop('unknown')
        spec.setdefault('quantity',1)
        if 'materials' in spec:spec['materials']=[entry(m) for m in spec['materials']]
        return spec

    reports=[]
    for route in data['routes']:
        case=bindings['cases'].get(route['id'],{})
        if case:
            proof=source/case['source'].split('#',1)[0]
            assert proof.is_file() and route['id'] in proof.read_text(encoding='utf-8'),(route['id'],'missing case source',proof.name)
        state={key:[] for key in ZONE_KEYS};state['zone_notes']={};state['notes']=[]
        state['opponent']='Cartas rivales no especificadas; no se presupone su campo vacío.'
        state['skill']=copy.deepcopy(data['skills'])
        material_text=''
        for row in route['resources']:
            title=fold(row['zone']);raw=row['text'];key=None
            if title=='mano' or title=='mano propia':key='hand'
            elif title in ['monstruos','monstruos propios','campo propio monstruos']:key='monster'
            elif title in ['magicas y trampas','campo propio m t']:key='spell'
            elif title=='cementerio':key='graveyard'
            elif title=='destierro propio':key='banished'
            elif title in ['campo y estado rival','campo rival','monstruos rivales']:state['opponent']=text_clean(raw)
            elif title.startswith('materiales xyz'):material_text=raw
            if key is None:continue
            state['zone_notes'][key]=text_clean(raw) if raw else 'No se describen cartas adicionales en esta zona.'
            if not raw or re.search(r'^∅|^vac[ií][ao]',raw,re.I):continue
            # A semicolon often separates a current placement from a return
            # to Deck or a future conditional action. Do not put both in GY.
            main=raw.split(';')[0]
            objects=refs(main)
            if re.search(r'ya sali[oó]|ya regres|retornad|regresaron|devueltos|al fondo del Deck',main,re.I):objects=[]
            for obj in objects:
                cid=obj['cid'];label=next((p.get('label','') for p in route['board'] if Path(p['image']).stem==str(cid)), '') if key in ['monster','spell','field'] else ''
                description=label or main
                details=[]
                for m in re.finditer(r'#'+str(cid)+r'\s*(\([^)]*\))?',main):
                    details.append(m[1] or '')
                if details:description+=' '+' '.join(details)
                if re.search(r'centro|central',description,re.I):obj['fixed_slot']=3 if data['format']=='tcg' else 2
                if re.search(r'\bDefensa\b',description,re.I):obj['position']='Defensa'
                elif re.search(r'\bAtaque\b',description,re.I):obj['position']='Ataque'
                elif re.search(r'Colocad[ao]|boca abajo',description,re.I):obj['position']='Colocada' if key=='spell' else 'Boca abajo'
                elif re.search(r'Equipo',description,re.I):obj['position']='Equipo boca arriba'
                elif re.search(r'boca arriba|Continua',description,re.I):obj['position']='Boca arriba'
                stats=re.findall(r'(?:\d+\s*(?:ATK|DEF)|(?:ATK|DEF)\s*\d+)',description,re.I)
                if stats:obj['statistic']=' · '.join(dict.fromkeys(stats))
                if re.search(r'solo si|si sobreviv|solo despu[eé]s',main,re.I):obj.update(conditional=True,note=text_clean(main))
                state['field' if key=='spell' and cid in FIELD_CIDS else key].append(obj)
            if re.search(r'desconocid|no identificad|identidad variable',raw,re.I):
                amount=re.search(r'(\d+) cartas?',raw)
                state[key].append({'unknown':text_clean(raw),'quantity':int(amount[1]) if amount else None,
                    'position':'Boca abajo' if 'boca abajo' in raw else '',
                    'conditional':bool(re.search(r'posible|0 o 1',raw))})

        # Existing full A10 board pictures supply unnumbered field references.
        for p in route['board']:
            cid=int(Path(p['image']).stem)
            zone={'MONSTER':'monster','SPELL':'spell','SPELL_TRAP':'spell','FIELD':'field','TRAP':'spell'}.get(p.get('zone'))
            if not zone:continue
            if zone=='spell' and cid in FIELD_CIDS:zone='field'
            if not any(obj.get('cid')==cid for obj in state[zone]):state[zone].append({'cid':cid})
            obj=next(obj for obj in state[zone] if obj.get('cid')==cid)
            label=p.get('label','')
            for attr in ['position','statistic']:
                if p.get(attr):obj[attr]=p[attr]
            if p.get('slot'):obj['fixed_slot']=p['slot']
            if p.get('materials'):obj['materials']=[o for name in p['materials'] for o in refs(name)]
            if not obj.get('position'):
                for needle,position in [('Colocada','Colocada'),('Defensa','Defensa'),('Ataque','Ataque'),('Equipo','Equipo boca arriba'),('boca arriba','Boca arriba'),('Continua','Boca arriba')]:
                    if needle.casefold() in label.casefold():obj['position']=position;break

        # Xyz materials remain attached. Fusion/Synchro/Link resources are
        # represented by their actual destinations, not an attached stack.
        if material_text and not re.match(r'(?:Ninguno|Sin |Contacto|Fusi[oó]n|Sacrificio|Tributo|cero)',material_text,re.I):
            clauses=material_text.split(';')
            for clause in clauses:
                if ':' not in clause:continue
                owner,attached=clause.split(':',1);owners=refs(owner)
                targets=[o for o in state['monster'] if any(o.get('cid')==x['cid'] for x in owners)]
                if not targets and len(state['monster'])==1:targets=state['monster']
                if len(targets)==1:targets[0]['materials']=refs(attached)

        def overlay(base,changes):
            result=copy.deepcopy(base)
            for key in ZONE_KEYS:
                if key in changes:result[key]=copy.deepcopy(changes[key]);result['zone_notes'][key]='Estado de la rama documentada.'
            for key in ['opponent','note']:
                if key in changes:result[key]=changes[key]
            return result

        state=overlay(state,case)
        # Explicit empty field still needs known hand/GY rather than vanishing.
        variants=[dict(state,label='Resultado base documentado')]
        variants.extend(dict(overlay(state,v),label=v['label']) for v in case.get('variants',[]))
        for variant in variants:
            for key in ZONE_KEYS:variant[key]=[entry(x) for x in variant[key]]
            for key in ['spell','field']:
                for obj in variant[key]:
                    if obj.get('cid') in bindings.get('active_face_up_cards',[]) and not obj.get('position'):obj['position']='Boca arriba'
            for key in ['monster','spell','field']:
                expanded=[]
                for obj in variant[key]:
                    for number in range(obj['quantity'] or 1):
                        placed=copy.deepcopy(obj);placed['quantity']=1
                        if (obj['quantity'] or 1)>1:placed['copy_label']=f"Ejemplar {number+1} de {obj['quantity']}"
                        expanded.append(placed)
                variant[key]=expanded
            occurrences=Counter(o['cid'] for key in ZONE_KEYS for o in variant[key] if o.get('cid'))
            for obj in variant['monster']:
                if obj.get('cid') and not obj.get('position'):
                    position=documented_position(obj['cid'],route,occurrences[obj['cid']]==1)
                    if position:obj['position']=position
            columns=3 if data['format']=='rush_duel' else 5
            occupied=set();extra_occupied=set()
            # Center-dependent monsters are placed first. Other numbered slots
            # are a visual distribution, not evidence of a mandatory column.
            for obj in sorted(variant['monster'],key=lambda x:not bool(x.get('fixed_slot'))):
                if obj.get('cid') in LINK_CIDS:
                    assert 1 not in extra_occupied,(route['id'],'second Link needs a documented linked zone')
                    obj.setdefault('position','Ataque')
                    obj['row']='extra_monster';obj['slot']=1;extra_occupied.add(1)
                else:
                    obj['row']='monster'
                    available=[i for i in ([3,2,4,1,5] if columns==5 else [2,1,3]) if i not in occupied]
                    obj['slot']=obj.get('fixed_slot',available[0] if available else 0)
                    assert obj['slot'] not in occupied and 1<=obj['slot']<=columns,(route['id'],obj)
                    occupied.add(obj['slot'])
                if obj.get('materials') is not None:obj['material_count']=sum(m['quantity'] for m in obj['materials'])
            for i,obj in enumerate(variant['spell']):obj['slot']=obj.get('fixed_slot',i+1)
            if len(variant['monster'])==1 and variant['monster'][0].get('cid'):
                for obj in variant['spell']:
                    if 'Equipo' in obj.get('position',''):obj['equipped_to']=variant['monster'][0]['name']
            assert len(variant['spell'])<=columns and len(extra_occupied)<=1,route['id']
            variant['columns']=columns
            variant['opponent_objects']=[{'name':'Ficha Bomba rival','quantity':1,'position':'Posición no indicada','token':True}] if re.search(r'Ficha Bomba rival permanece',variant['opponent']) else []
            variant['layout_note']='El centro u otra zona exigida por la guía se conserva. Las demás casillas son una distribución ilustrativa; no afirman columnas obligatorias.'
            # No guessed zeroes: state descriptions distinguish a known empty
            # zone from one whose remaining resources were not specified.
            totals=Counter()
            for key in ZONE_KEYS:
                for obj in variant[key]:
                    if obj.get('conditional'):continue
                    if obj.get('cid'):totals[obj['cid']]+=obj['quantity']
                    for m in obj.get('materials',[]):
                        if m.get('cid'):totals[m['cid']]+=m['quantity']
            for cid,count in totals.items():
                available=sum(s['quantity'] for s in cards[cid]['slots'] if s['section'] in ['main','extra'])
                assert count<=available,(route['id'],'copy duplication',cid,count,available)
        route['endboard']={'variants':variants,'historical':route['historical']}
        route['result']=re.split(r'Validación acotada:|Consulta técnica:|Guía detallada:|Guía individual con|Guía:',route['result'])[0].strip()
        if not route['result']:
            route['result']=' · '.join(label+': '+', '.join(o['name'] for o in variants[0][key])
                for key,label in [('monster','Monstruos'),('spell','Magias y trampas'),('hand','Mano'),('graveyard','Cementerio')]
                if variants[0][key])
        reports.append({'id':route['id'],'variants':len(variants),'source':case.get('source','A10 Campo final documentado / resource table'),
                        'conditional_cards':sum(bool(o.get('conditional')) for v in variants for key in ZONE_KEYS for o in v[key])})
    return reports
