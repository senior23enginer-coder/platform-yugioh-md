"""Import V19 card content and V20 identity/rule layers over the saved library."""
import copy
import hashlib
from pathlib import Path
from import_a01_a07_v16 import APP, SOURCE, read, write
from combo_source_sync import apply_overrides

VERSION='20261114-delivery-v20'
BASE=APP/'assets/editorial/20261114-a01-a07-v16'
TARGET=APP/'assets/editorial'/VERSION

def build():
    a05=read(SOURCE/'FICHAS_A05_370_COMPLETAS_V19.json')['fichas']
    a06=read(SOURCE/'SINERGIAS_A06_370_CLASIFICADAS_V19.json')
    a04=read(SOURCE/'PROCEDENCIA_A04_370_AUDITADA_V20.json')['identidades']
    a07=read(SOURCE/'REGLAS_A07_370_CONTRATOS_Y_98_CASOS_V20.json')
    progress=read(SOURCE.parent/'03_COMPROBACIONES_Y_NO_IMPORTAR/INFORME_PROGRESO_A01_A10_V20.json')['puntos']
    ix=lambda rows:{(x['deck_id'],x['cid']):x for x in rows}
    cards,classes,identities,contracts=map(ix,(a05,a06['clasificaciones'],a04,a07['fichas']))
    assert len(cards)==len(classes)==len(identities)==len(contracts)==370
    manifest=copy.deepcopy(read(BASE/'manifest.json'));manifest['version']=VERSION
    report=[]
    labels={'descripcion_precisa':'Efecto','condicion_ventana':'Ventanas y requisitos','coste':'Coste','restricciones':'Restricciones','proposito_en_deck':'Propósito','ir_primero':'Al jugar primero','ir_segundo':'Al jugar segundo','interrupcion':'Ante interrupciones'}
    for key,entry in manifest['decks'].items():
        deck=int(key);data=read(BASE/f'{deck}.json');apply_overrides(data,SOURCE)
        original=copy.deepcopy(data)
        rels=[r for r in a06['relaciones'] if r['deck_id']==deck]
        names={c['cid']:c['name'] for c in data['cards']}
        for c in data['cards']:
            pair=(deck,c['cid']);f=cards[pair];cl=classes[pair];identity=identities[pair];rules=contracts[pair]
            zones={'Main':'main','Extra':'extra','Side':'side','Reserva':'reserve'}
            assert sorted((zones[s['zona']],s['copias']) for s in identity['zonas'])==sorted((s['section'],s['quantity']) for s in c['slots'])
            assert identity['modal_id']==f"{deck}:CARD:{c['cid']}"
            assert hashlib.sha256((SOURCE/identity['foto']['archivo']).read_bytes()).hexdigest()==identity['foto']['sha256']
            assert (SOURCE/identity['foto']['archivo']).read_bytes()==(APP/c['image'].removeprefix('./')).read_bytes()
            fields={label:f[source] for source,label in labels.items()}
            assert all(isinstance(v,str) and v.strip() for v in fields.values())
            c['fields']=[{'title':k,'text':v} for k,v in fields.items()]
            c['practical']=copy.deepcopy(c['fields'])
            c['operational']={'Acción':fields['Efecto'],**{k:v for k,v in fields.items() if k in ('Ventanas y requisitos','Coste','Restricciones')}}
            c['learning']={'Cuándo jugarla primero':fields['Al jugar primero'],'Cuándo jugarla segundo':fields['Al jugar segundo'],'Limitación a respetar':fields['Restricciones']}
            c['role']=fields['Propósito'].split('. ')[0].rstrip('.')+'.';c['source_revision']=VERSION;c['a05_status']=f['estado']
            c['editorial_label']='Revisada editorialmente' if f['estado']=='REVISADA_EDITORIALMENTE' else 'Extracción estructural; revisar requisitos exactos'
            c['delivery_fields']={'Estado de la ficha':c['editorial_label'],**fields}
            c['functional']={'purpose':fields['Propósito'],'explanation':cl['razon']}
            links=[r for r in rels if c['cid'] in (r['desde'],r['hacia'])]
            c['synergy']={'labels':[cl['tipo'].replace('_',' ').capitalize()], 'function':cl['razon'],'resource':'','relation':'\n'.join(r['relacion'] for r in links),'risk':'','decision':''}
            c['a06_classification']=cl
            c['identity']={'cid':c['cid'],'name':identity['nombre_inventario'],'official_name':identity['nombre_oficial'],'format':identity['formato'],'source':identity['fuente_oficial'],'photo_sha256':identity['foto']['sha256'],'visual_checked':False}
            c['sources']=[identity['fuente_oficial']]
            c['rule_contract']=rules
        data['engine_maps']=[{'title':f"{names[r['desde']]} → {names[r['hacia']]}",'photos':[{'name':names[cid],'image':next(c['image'] for c in data['cards'] if c['cid']==cid)} for cid in (r['desde'],r['hacia'])], 'result':r['relacion'],'requirements':r['clase'].replace('_',' ')} for r in rels]
        controls=[x for x in a07['controles_especificos'] if x['deck_id']==deck]
        scenarios=[x for x in a07['escenarios_adicionales'] if x['deck_id']==deck]
        data['rule_scenarios']=scenarios;data['rule_controls']=controls
        data['general_rules']=a07['reglas_generales']
        data['format_context']['deckSpecificControl']='\n'.join(x['criterio'] for x in controls)
        data['a01_a07_revision']={'version':VERSION,'reviewed':sum(c['a05_status']=='REVISADA_EDITORIALMENTE' for c in data['cards']),'pending':sum(c['a05_status']!='REVISADA_EDITORIALMENTE' for c in data['cards']),'relations':len(rels),'controls':len(controls),'scenarios':len(scenarios),'rule_cards':len(data['cards']),'classified':len(data['cards'])}
        data['delivery_progress']=[{k:p[k] for k in ('punto','nombre','porcentaje_cobertura_documental','fraccion','porcentaje_validacion_observada','fraccion_validacion','criterio_validacion')} for p in progress]
        for field in ('routes','profile','skills','practice','side_plans','matchup_plans'):
            assert data[field]==original[field],(deck,field)
        entry.update(path=f'./assets/editorial/{VERSION}/{deck}.json',sha256=write(TARGET/f'{deck}.json',data))
        report.append({'deck_id':deck,'cards':len(data['cards']),'routes':len(data['routes']),**data['a01_a07_revision']})
    write(TARGET/'manifest.json',manifest)
    registry=read(APP/'assets/editorial/strategy-content-state.json');registry.update(version=VERSION,checked_studies=manifest['decks'])
    write(APP/'assets/editorial/strategy-content-state.json',registry)
    write(APP.parent/'outputs/import-v20/report.json',{'ok':True,'version':VERSION,'decks':report,'inventory_writes':0,'sporting_writes':0})
    return report

if __name__=='__main__':
    import json
    print(json.dumps(build()))
