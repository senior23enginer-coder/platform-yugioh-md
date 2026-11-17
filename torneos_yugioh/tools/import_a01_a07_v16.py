"""Project the supplied A01-A07 revision without changing inventories or combos."""
import copy
import hashlib
import json
from pathlib import Path
from combo_source_sync import apply_overrides

APP = Path(__file__).resolve().parents[1]
SOURCE = APP / 'context/gpt/01_INFORMACION_A_IMPORTAR'
BASE = APP / 'assets/editorial/20261114-incremental-v12'
VERSION = '20261114-a01-a07-v16'
TARGET = APP / 'assets/editorial' / VERSION

def read(p):
    return json.loads(p.read_text(encoding='utf-8-sig'))

def write(p, value):
    p.parent.mkdir(parents=True, exist_ok=True)
    raw = (json.dumps(value, ensure_ascii=False, indent=2)+'\n').encode('utf-8')
    p.write_bytes(raw)
    return hashlib.sha256(raw).hexdigest()

def build():
    catalog = read(SOURCE/'CATALOGO_PROCEDENCIA_370_A04_V15.json')
    fichas = read(SOURCE/'FICHAS_A05_REVISADAS_Y_PENDIENTES_370_V16.json')['fichas']
    relations = read(SOURCE/'MOTORES_Y_RELACIONES_A06_V16.json')
    rules = read(SOURCE/'REGLAS_A07_V16_SIN_EVALUAR_LISTAS.json')['decks']
    index = {(x['deck_id'], x['cid']): x for x in fichas}
    assert len(index) == len(catalog['cards']) == 370
    manifest = copy.deepcopy(read(BASE/'manifest.json'))
    manifest['version'] = VERSION
    report = []
    preserved = []
    labels = [('descripcion_precisa','Efecto'), ('condicion_ventana','Ventanas y requisitos'),
              ('coste','Coste'), ('restricciones','Restricciones'), ('proposito_en_deck','Propósito'),
              ('primero','Al jugar primero'), ('ir_primero','Al jugar primero'),
              ('segundo','Al jugar segundo'), ('ir_segundo','Al jugar segundo'), ('interrupcion','Ante interrupciones')]
    for key, entry in manifest['decks'].items():
        deck = int(key)
        data = read(BASE/f'{deck}.json')
        apply_overrides(data, SOURCE)
        before = copy.deepcopy(data)
        own = [x for x in catalog['cards'] if x['deck_id'] == deck]
        assert {x['cid'] for x in own} == {c['cid'] for c in data['cards']}
        names = {c['cid']:c['name'] for c in data['cards']}
        rels = [x for x in relations['relaciones'] if x['deck_id'] == deck]
        for c in data['cards']:
            f = index[deck,c['cid']]
            origin = next(x for x in own if x['cid'] == c['cid'])
            zones = {'Main':'main','Extra':'extra','Side':'side','Reserva':'reserve'}
            assert sorted((zones[z['zona']],z['copias']) for z in origin['zonas']) == sorted((z['section'],z['quantity']) for z in c['slots'])
            reviewed = f['estado'] == 'REVISADA_EDITORIALMENTE'
            fields = {}
            for source,label in labels:
                if f.get(source): fields[label] = f[source]
            if not reviewed:
                fields = {'Efecto':'Ficha pendiente de cotejo del efecto. Consulta su texto oficial antes de usarla.'}
            c['fields'] = [{'title':k,'text':v} for k,v in fields.items()]
            c['practical'] = copy.deepcopy(c['fields'])
            c['operational'] = {'Acción':fields.get('Efecto',''), **{k:v for k,v in fields.items() if k in ('Ventanas y requisitos','Coste','Restricciones')}}
            c['learning'] = {'Cuándo jugarla primero':fields.get('Al jugar primero','Sin explicación cotejada para esta situación.'),'Cuándo jugarla segundo':fields.get('Al jugar segundo','Sin explicación cotejada para esta situación.')}
            c.pop('extra_explanation',None)
            c['functional'] = {'explanation':'','purpose':''}
            c['delivery_fields'] = {**fields,'Estado de la ficha':'Revisada editorialmente; sin simulación de duelo.' if reviewed else 'Pendiente de cotejo semántico.'}
            links = [r for r in rels if c['cid'] in (r['desde'],r['hacia'])]
            descriptions = [f"{names[r['desde']]} → {names[r['hacia']]}: {r['relacion']}" for r in links]
            c['synergy'] = {'labels':list(dict.fromkeys(r['clase'].replace('_',' ').capitalize() for r in links)) or ['Relación pendiente de clasificar'], 'resource':'','relation':'\n'.join(descriptions),'function':'Relaciones documentadas','reason':'','risk':'','decision':''}
            c['role'] = fields.get('Propósito') or ('Ficha revisada editorialmente' if reviewed else 'Efecto pendiente de cotejo')
            c['source_revision'] = VERSION
            c['sources'] = [f['fuente']]
            c['a05_status'] = f['estado']
            image = SOURCE/origin['foto']
            assert image.exists() and image.stat().st_size > 1000
            # Reuse the already published photograph only after byte identity
            # with this delivery is proven; never guess an image by name.
            existing = APP/c['image'].removeprefix('./')
            assert existing.read_bytes() == image.read_bytes()
            c['full_image'] = c['image']
        data['engine_maps'] = [{'title':f"{names[r['desde']]} → {names[r['hacia']]}", 'photos':[next(c for c in data['cards'] if c['cid']==cid) for cid in (r['desde'],r['hacia'])], 'result':r['relacion'], 'requirements':r['clase'].replace('_',' ')} for r in rels]
        rule = next(x for x in rules if x['deck_id'] == deck)
        data['format_context'] = {'format_and_mechanics':rule['formato'],'deckSpecificControl':'\n'.join(x['verificacion'] for x in rule['controles']), 'criticalScenarios':'Controles documentados; pendientes de prueba en motor de duelo.'}
        data['a01_a07_revision'] = {'version':VERSION,'reviewed':sum(c['a05_status']=='REVISADA_EDITORIALMENTE' for c in data['cards']), 'pending':sum(c['a05_status']!='REVISADA_EDITORIALMENTE' for c in data['cards']), 'relations':len(rels),'controls':len(rule['controles'])}
        # Prior dossiers contain superseded automatic A05/A06 prose.
        data['delivery_dossier'] = {k:v for k,v in data.get('delivery_dossier',{}).items() if k not in ('A02_cartas_proposito','A03_costes_limites','A05_main','A06_sinergias','A07_interrupciones')}
        for k in ('routes','skills','practice','side_plans','matchup_plans'):
            assert data[k] == before[k], (deck,k)
        preserved.extend({'id':r['id'],'deck_id':deck,'titulo':r['title']} for r in data['routes'])
        entry.update(path=f'./assets/editorial/{VERSION}/{deck}.json',sha256=write(TARGET/f'{deck}.json',data))
        report.append({'deck_id':deck,'cards':len(data['cards']), 'routes_preserved':len(data['routes']), **data['a01_a07_revision']})
    write(TARGET/'manifest.json',manifest)
    # The owner removed the old A09/A10 package; keep an explicit source index
    # for existing editable combos instead of recreating removed old inputs.
    archive = SOURCE/'COMBOS_EXISTENTES_INDICE_EDITOR.json'
    if not archive.exists():write(archive,{'schema':'station-x-preserved-route-index/1','rutas':preserved})
    registry = read(APP/'assets/editorial/strategy-content-state.json')
    registry.update(version=VERSION,checked_studies=manifest['decks'])
    write(APP/'assets/editorial/strategy-content-state.json',registry)
    write(APP.parent/'outputs/import-a01-a07-v16/report.json',{'version':VERSION,'decks':report,'inventory_writes':0,'sporting_writes':0})
    return report

if __name__ == '__main__':
    print(json.dumps(build(),ensure_ascii=True))
