"""Compile the three requested Kashtira guides without replacing inventories."""
import copy
import json
from pathlib import Path

NAME='guias_a10/KASHTIRA_COMBOS_SOLICITADOS.json'


def apply_requested_kashtira(data, source):
    if data['deck_id'] != 665:
        return []
    file=source/NAME
    if not file.exists():
        return []
    incoming=json.loads(file.read_text(encoding='utf-8'))
    assert incoming['deck_id']==665
    cards={c['cid']:c for c in data['cards']}
    def photo(cid,label=''):
        c=cards[cid]
        return dict(name=c['name'],image=c['image'],full_image=c.get('full_image',c['image']),label=label)
    def item(value,zone,index):
        value={'cid':value} if isinstance(value,int) else copy.deepcopy(value)
        cid=value['cid'];p={**photo(cid),**value,'quantity':1}
        if zone in ['monster','spell']:
            p.update(slot=value.get('slot',index+1),position=value.get('position','Boca arriba'))
        if zone=='monster':p['row']='monster'
        if 'materials' in value:
            p['materials']=[{**photo(cid),'cid':cid,'quantity':1} for cid in value['materials']]
            p['material_count']=len(p['materials'])
        return p
    compiled=[]
    for spec in incoming['routes']:
        steps=[dict(number=i+1,action=s['action'],detail=s['action'],compact_action=s['action'],
                    visual_detail=s['action'],photos=[photo(cid,label) for cid,label in s['cards']],
                    requirements=[],resources=[],board=[],chains=['CL1','CL2'] if 'CL2' in s['action'] else [],
                    phases=[s.get('phase','Main Phase propia')]) for i,s in enumerate(spec['steps'])]
        variant={z:[item(p,z,i) for i,p in enumerate(spec['board'].get(z,[]))]
                 for z in ['monster','spell','field','hand','graveyard','banished','deck','extra']}
        variant.update(label=spec['final_label'],columns=5,opponent_objects=[],skill=[],zone_notes={},
                       notes=spec['final_notes'],opponent='Consulta la precondición y los pasos: no se simulan respuestas rivales.',
                       layout_note='Casillas ilustrativas. Los materiales y momentos indicados pertenecen a esta secuencia.')
        r=dict(id=spec['id'],name=spec['title'],title=spec['title'],purpose=spec['purpose'],family=spec['family'],
               category=spec['category'],opening=spec['opening'],hand_photos=[photo(cid,'En mano') for cid in spec['hand']],
               turn_order='second' if spec['category']=='RESPUESTA CONDICIONAL' else 'first',
               result=spec['result'],steps=steps,board=[{**p,'zone':'MONSTER'} for p in variant['monster']],
               resources=[],visual=True,historical=False,editorial_status='conditional',initial_label='Mano inicial',
               initial_details=spec['conditions'],final_constraints=spec['final_notes'],next_phase='',spatial_detail=True,
               technical='Si el rival responde: '+spec['interruptions'],
               sources=[f'https://www.db.yugioh-card.com/yugiohdb/card_search.action?cid={cid}&ope=2&request_locale=en' for cid in spec['sources']],
               endboard={'variants':[variant],'historical':False},source_extension=NAME)
        if 'MIRROR' in r['id']:
            r['sources'].append('https://www.db.yugioh-card.com/yugiohdb/faq_search.action?cid=18191&ope=4&request_locale=ja')
        data['routes']=[old for old in data['routes'] if old['id']!=r['id']]+[r]
        compiled.append(r)
        for c in data['cards']:
            used=[i for i,s in enumerate(spec['steps']) if any(cid==c['cid'] for cid,_ in s['cards'])]
            if used:
                c.setdefault('route_links',[])
                c['route_links']=[link for link in c['route_links'] if link['id']!=r['id']]+[{'id':r['id'],'step':used[0]}]
    baronne=cards[16386]
    baronne['documented_access']={'route_id':compiled[2]['id'],'purpose':'Sincro temprana con Unicornio de Nivel 7 y Ash Blossom, Cantante de Nivel 3.',
        'explanation':'Forma Baronne antes de resolver Teosis o aplicar restricciones a Xyz. Ofrece una negación disponible antes de la quinta Invocación; si la gastas, no se repone mientras siga boca arriba. Su uso exige que el formato permita esta carta. También puede desterrarse por el coste de Pot, pero esa no es su única función en esta lista.'}
    return compiled


def update_source_index(source, routes):
    file=source/'GUIAS_Y_DECISIONES_V16.json'
    raw=json.loads(file.read_text(encoding='utf-8'))
    ids={r['id'] for r in routes}
    raw['rutas']=[r for r in raw['rutas'] if r['id'] not in ids]
    for r in routes:
        raw['rutas'].append(dict(id=r['id'],deckId='665',deck='Kashtira Pure',titulo=r['title'],proposito=r['purpose'],
          tipo=r['category'],grupo='combo_solicitado',source_extension=NAME,estadoInicial=r['opening'],
          cartasEntrada=[{'nombre':p['name'],'imagen':p['image']} for p in r['hand_photos']],
          pasos=[{'numero':s['number'],'accion':s['action'],'cartas':[{'nombre':p['name'],'imagen':p['image'],'accion':p['label']} for p in s['photos']],'detalle':s['detail']} for s in r['steps']],
          campoFinal=r['result'],condiciones=r['initial_details']))
    raw['totales']['rutas']=len(raw['rutas'])
    file.write_bytes((json.dumps(raw,ensure_ascii=False,indent=2)+'\n').encode())
