"""Add V21 causal links, rule variants and situational decisions to V20."""
import copy
import json
from import_a01_a07_v16 import APP,SOURCE,read,write
from combo_source_sync import apply_overrides

VERSION='20261114-delivery-v21'
BASE=APP/'assets/editorial/20261114-delivery-v20'
TARGET=APP/'assets/editorial'/VERSION

def build():
    causal=read(SOURCE/'SINERGIAS_A06_MAPA_CAUSAL_370_V21.json')
    rules=read(SOURCE/'REGLAS_A07_MATRIZ_294_VARIANTES_V21.json')
    strategy=read(SOURCE/'ESTRATEGIAS_A08_77_DECISIONES_SITUACIONALES_V21.json')
    progress=read(SOURCE.parent/'03_COMPROBACIONES_Y_NO_IMPORTAR/INFORME_PROGRESO_A01_A10_V21.json')['puntos']
    index={(r['deck_id'],r['cid']):r for r in causal['fichas']}
    assert len(index)==370 and len(causal['relaciones'])==157
    manifest=copy.deepcopy(read(BASE/'manifest.json'));manifest['version']=VERSION
    report=[]
    for key,entry in manifest['decks'].items():
        deck=int(key);data=read(BASE/f'{deck}.json');apply_overrides(data,SOURCE)
        original=copy.deepcopy(data);cards={c['cid']:c for c in data['cards']}
        def photo(cid):
            c=cards[cid]
            return {k:copy.deepcopy(c[k]) for k in ('cid','name','image','full_image','slots')}
        for c in data['cards']:
            row=index[deck,c['cid']]
            assert row['modal_id']==f"{deck}:CARD:{c['cid']}"
            c['causal_profile']=row
            c['causal_review_label']='Revisión editorial pendiente' if row['calificacion_de_evidencia']=='PATRON_OFICIAL_NO_COTEJADO_EDITORIALMENTE' else 'Relación o función documentada; sin replay'
        data['causal_relations']=[];quantity_notes=[]
        for row in causal['relaciones']:
            if row['deck_id']!=deck:continue
            r=copy.deepcopy(row);r['photos']=[photo(r['origen']['cid']),photo(r['destino']['cid'])]
            for node in ('origen','destino'):
                c=cards[r[node]['cid']];zone={'main':'Main','extra':'Extra','side':'Side','reserve':'Reserva'}
                assert any(zone[s['section']]==r[node]['zona'] for s in c['slots'])
                if not any(zone[s['section']]==r[node]['zona'] and s['quantity']==r[node]['copias'] for s in c['slots']):
                    assert sum(s['quantity'] for s in c['slots'])==r[node]['copias']
                    quantity_notes.append({'relation':r['id'],'cid':c['cid'],'source':r[node],'displayed_slots':c['slots']})
            data['causal_relations'].append(r)
        data['causal_skills']=[r for r in causal['skills_externas'] if r['deck_id']==deck]
        data['rule_variants']=[r for r in rules['matriz_prueba'] if r['deck_id']==deck]
        groups={r['caso_original'] for r in data['rule_variants']}
        assert all(sum(r['caso_original']==g for r in data['rule_variants'])==3 for g in groups)
        data['situational_decisions']=[]
        for row in strategy['casos']:
            if row['deck_id']!=deck:continue
            r=copy.deepcopy(row);r['photos']=[photo(c['cid']) for c in r['cartas_relevantes']]
            data['situational_decisions'].append(r)
        assert len(data['situational_decisions'])==7
        data['delivery_progress']=[{k:p[k] for k in ('punto','nombre','porcentaje_cobertura_documental','fraccion','porcentaje_validacion_observada','fraccion_validacion','criterio_validacion')} for p in progress]
        data['delivery_v21']={'version':VERSION,'causal_cards':len(cards),'relations':len(data['causal_relations']),'rule_cases':len(groups),'rule_variants':len(data['rule_variants']),'decisions':7}
        # All earlier inventory, text, Skills, combos, practice and native edits survive.
        for field in ('routes','profile','skills','practice','side_plans','matchup_plans','decisions'):
            assert data[field]==original[field],(deck,field)
        for c,old in zip(data['cards'],original['cards']):
            for field in ('slots','fields','identity','rule_contract','a05_status'):
                assert c[field]==old[field],(deck,c['cid'],field)
        entry.update(path=f'./assets/editorial/{VERSION}/{deck}.json',sha256=write(TARGET/f'{deck}.json',data))
        report.append({'deck_id':deck,'cards':len(cards),'routes':len(data['routes']),'source_quantity_notes':quantity_notes,**data['delivery_v21']})
    write(TARGET/'manifest.json',manifest)
    registry=read(APP/'assets/editorial/strategy-content-state.json');registry.update(version=VERSION,checked_studies=manifest['decks'])
    write(APP/'assets/editorial/strategy-content-state.json',registry)
    write(APP.parent/'outputs/import-v21/report.json',{'ok':True,'version':VERSION,'decks':report,'inventory_writes':0,'sporting_writes':0})
    return report

if __name__=='__main__':print(json.dumps(build()))
