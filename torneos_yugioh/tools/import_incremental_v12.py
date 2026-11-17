"""Import the cumulative A01-A10 delivery into native, editable study records.

Builds a new immutable library from the last active 108-route snapshot. Never
writes inventories, results or player creations. Editor revisions are applied
last. --apply switches the existing reader registry only after all decks pass.
"""
import argparse
import copy
import hashlib
import json
import re
from collections import Counter
from pathlib import Path

from combo_source_sync import apply_overrides

APP = Path(__file__).resolve().parents[1]
SOURCE = APP / 'context/gpt/01_INFORMACION_A_IMPORTAR'
BASE = APP / 'assets/editorial/20261113-endboards'
TARGET = '20261114-incremental-v12'
IDS = (650, 653, 656, 665, 670, 684, 687, 704, 705, 706, 707)
# Final identities explicitly named in each supplied route. These are photo
# bindings, not invented columns, materials or simulated transitions.
FINAL_PHOTOS = {
    '650:A09:V11:REGINA:FORTUNA:RHAPSODIA': [(22523, 'MONSTER'), (22118, 'MONSTER'), (22173, 'SPELL')],
    '650:A09:V11:HARMONIA:MEDIUS:DESTORBIM': [(22335, 'MONSTER')],
    '653:A09:V11:DRASTEA:ADRASTEIA:DIACTORUS': [(21812, 'MONSTER'), (21840, 'SPELL')],
    '656:A09:V11:GOODS:TRIVIKARMA:SCREAM:REINO': [(17417, 'MONSTER'), (17813, 'SPELL')],
    '656:A09:V11:REINO:SCHEIREN:REDOER': [(14356, 'MONSTER')],
    '665:A09:V11:TEARKASH:OGRE:BIRTH:SHANGRI': [(17800, 'MONSTER'), (17815, 'SPELL')],
    '670:A09:V11:OGIER:DURENDAL:TURPIN:DEMPSEY': [(18926, 'MONSTER')],
    '684:A09:V11:CORE:WURM:REVSYSTEM:INFINITY:OVERFLOW': [(11765, 'MONSTER')],
    '687:A09:V11:TROOPER:EJECTION:SHELL:INFERNO': [(18916, 'MONSTER')],
    '704:A09:V11:HYPER:CHEMICALIZED:PIERCE': [(17868, 'MONSTER')],
    '705:A09:V11:PHASER:REVIVE:DEATHDOOM': [(16331, 'MONSTER')],
    '706:A09:V11:TRIPLE_NORMAL:HELIOSPHERE': [(21501, 'MONSTER')],
    '707:A09:V11:EXECUTOR:RENAME:DARK_MAGIC_ATTACK': [(19773, 'MONSTER')],
    '653:A09:V12:ELARA:DRASTEA:DRASTRIUS:SINGLE': [(21811, 'MONSTER'), (21840, 'SPELL')],
    '656:A09:V12:SUPERPOLY:OPP_LUBELLION_ALBAZ:DRAGOSTAPELIA': [(12870, 'MONSTER')],
    '656:A09:V12:SUPERPOLY:OPP_ALBAZ_ALUBER:MUDRAGON': [(13454, 'MONSTER')],
    '650:A09:ASH:RESPUESTA_DECK16': [(12950, 'GY')],
    '650:A09:GHOST_BELLE:RESPUESTA_GY17': [(13587, 'GY')],
    '650:A09:LUCINA_MEDIUS:SP18': [(19188, 'MONSTER')],
}


def read(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))


def encoded(value):
    return (json.dumps(value, ensure_ascii=False, indent=2) + '\n').encode('utf-8')


def digest(raw):
    return hashlib.sha256(raw).hexdigest()


def prose(value):
    if isinstance(value, list):
        return '\n'.join(prose(x) for x in value)
    if isinstance(value, dict):
        return '\n'.join(f'{k}: {prose(v)}' for k, v in value.items())
    return re.sub(r'\*\*|`', '', str(value or '')).strip()


def build(live, source=SOURCE):
    consumed = set()
    assets = {}

    def load(name):
        consumed.add(name)
        return read(source / name)

    visual = {r['id']: r for r in load('GUIAS_Y_DECISIONES_V16.json')['rutas']}
    assert len(visual) == 108
    deltas = load('LOTE_JUGABLE_11_DECKS_A01_A10_V11.json')['rutas'] + load('COMBOS_COTEJADOS_A09_A10_V12.json')['rutas']
    assert len(deltas) == len({r['id'] for r in deltas}) == 16
    assert not set(visual).intersection(r['id'] for r in deltas)
    # Earlier cumulative deltas were not embedded into the 108-route catalog.
    # Keep their two situational responses and the corrected Lucina/Medius
    # opening as separate native records; the retracted Tinia proposal stays out.
    for name in ('DELTA_ELFNOTE_LOTE_01.json', 'DELTA_ELFNOTE_APERTURA_SP_ITERACION_04.json'):
        delta = load(name)
        names = {c['cid']: c['name'] for c in read(BASE/f"{delta['deck_id']}.json")['cards']}
        def state_text(values):
            labels = {'own_field':'Campo propio', 'own_graveyard_additions':'Añadidas al Cementerio propio', 'opponent_search':'Búsqueda rival',
                'remaining_resources':'Recursos restantes', 'opponent_cards':'Cartas rivales', 'opponent_revival':'Recuperación rival',
                'after_own_main_phase':'Tras la Main Phase propia', 'graveyard':'Cementerio', 'normal_summon':'Invocación Normal',
                'immediate_banish':'Destierro al Invocar', 'opponent_turn_interaction':'Respuesta en turno rival',
                'from':'Origen', 'to':'Destino', 'chain':'Cadena', 'cost':'Coste', 'target':'Objetivo', 'after':'Resultado'}
            return '\n'.join(f"{labels[k]}: "+(', '.join(names[cid] for cid in v) if isinstance(v,list) else prose(v)) for k,v in values.items() if k in labels)
        for row in delta['routes_added']:
            opening = row['opening']
            actor_cids = [s['card_cid'] for s in row['steps'] if s.get('card_cid')]
            refs = list(dict.fromkeys(actor_cids + opening['own_hand']))
            deltas.append(dict(id=row['id'], deck_id=delta['deck_id'], titulo=row['title'], objetivo=row['purpose'],
                clasificacion=row['type'], mano_inicial=opening['own_hand'], campo_inicial=opening.get('assumptions','')+'\n'+opening.get('opponent_action',''), cementerio_inicial='Sin cartas iniciales indicadas.',
                requisitos_deck=opening.get('own_deck_required', []), requisitos_extra=opening.get('own_extra_required', []),
                pasos=[dict(numero=s['number'], accion=s['action'], resultado=state_text(s),
                            cids=[s['card_cid']] if s.get('card_cid') else refs[:1], photo_label='Respuesta disponible' if s.get('actor')=='opponent' else '') for s in row['steps']],
                costes=[], restricciones=row['requirements'], campo_final=state_text(row['endboard']), recursos_utilizados=state_text(row['endboard']),
                variantes=[prose(v) for v in row.get('branches', [])], ante_interrupciones=[prose(v) for v in row.get('interruptions', row.get('branches', []))],
                evidencia_oficial=row['sources'], source_extension='cumulative-delta'))
    modals = load('FICHAS_MODALES_370_A05_A06_V10.json')['cards']
    priority = load('FICHAS_VERIFICADAS_PRIORITARIAS_A05_A06_V12.json')['fichas']
    dossiers = load('ESTRATEGIAS_DECISIONES_A01_A10_11_DECKS_V11.json')['decks']
    matchups = load('MATCHUPS_Y_PLANES_SIDE_JUGABLES_11_DECKS_V11.json')['planes']
    practice = load('EJERCICIOS_PRACTICA_108_RUTAS.json')['items']
    interruptions = load('PRACTICA_DECISIONES_REALISTAS_11_DECKS_V12.json')['ejercicios']
    decision_exercises = load('EJERCICIOS_DECISION_45.json')['items']
    improvements = load('PRUEBAS_MEJORA_11_DECKS.json')['items']
    sides = load('PLANES_SIDE_TACTICOS_16_V10.json')['items']
    formats = load('REGLAS_CONTEXTUALES_A07_11_V08.json')['items']
    panels = {p['route_id']: p for p in load('PANELES_FINALES_DOCUMENTALES_108.json')['items']}
    assert len(modals) == 370 and len(priority) == len(dossiers) == 11
    assert len(practice) == 108 and len(decision_exercises) == 45 and len(sides) == 16
    records, report = {}, []
    for deck_id in IDS:
        data = copy.deepcopy(read(BASE / f'{deck_id}.json'))
        native = next(d for d in live['allDecks'] if int(d['id']) == deck_id)
        assert native['name'] == data['name'] and data['format'] in native['format_codes'], (deck_id, 'identity')
        cards = {c['cid']: c for c in data['cards']}
        native_rows = [r for r in live['cards'] if int(r['deck_id']) == deck_id]
        expected = sorted((s['card_id'], s['section'], s['quantity']) for c in cards.values() for s in c['slots'])
        actual = sorted((int(r['id']), r['section'], int(r['quantity'])) for r in native_rows)
        assert expected == actual, (deck_id, 'inventory changed; resolve before importing')

        def photo(cid, label='', **extra):
            cid = int(cid)
            assert cid in cards, (deck_id, 'unknown card', cid)
            c = cards[cid]
            assert (APP / c['image'].removeprefix('./')).is_file(), (deck_id, 'missing photo', cid)
            return dict(cid=cid, name=c['name'], image=c['image'], full_image=c.get('full_image', c['image']), label=label, **extra)

        def picture(p):
            if '/skills/' in p['imagen']:
                return copy.deepcopy(next(s for s in data['skills'] if Path(s['image']).name == Path(p['imagen']).name))
            return photo(int(Path(p['imagen']).stem), prose(p.get('accion', '')))

        for modal in [m for m in modals if int(m['deck_id']) == deck_id]:
            card = cards[int(modal['catalog_cid'])]
            f = modal['functions']
            labels = {'effect_summary': 'Efecto', 'activation_map': 'Activación', 'windows': 'Ventana',
                      'costs': 'Coste', 'restrictions': 'Restricciones', 'application': 'Uso en este deck',
                      'go_first': 'Al jugar primero', 'go_second': 'Al jugar segundo',
                      'mid_late': 'Juego medio y recuperación', 'synergy_routes': 'Relaciones', 'risks': 'Precauciones'}
            card['source_modal_id'] = modal['modal_id']
            card['delivery_fields'] = {labels[k]: prose(f[k]) for k in labels if f.get(k)}
            # Replace the old visible heuristic explanation only where this
            # delivery supplies an explicit official-text correction.
            if modal.get('official_text_review'):
                card['fields'] = [dict(title=k, text=v) for k, v in card['delivery_fields'].items()]
                card['practical'] = copy.deepcopy(card['fields'])
                card['operational'] = {'Acción': prose(f['effect_summary']), 'Coste': prose(f['costs']), 'Restricciones': prose(f['restrictions'])}
                card['learning'] = {'Cuándo jugarla primero': prose(f['go_first']), 'Cuándo jugarla segundo': prose(f['go_second'])}
                card['official_review'] = modal['official_text_review']
                card['sources'] = [modal['official_text_review']['url']]
        for fix in [f for f in priority if int(f['deck_id']) == deck_id]:
            card = cards[fix['cid']]
            keys = {'que_hace': 'Efecto', 'coste': 'Coste', 'restricciones': 'Restricciones', 'ir_primero': 'Al jugar primero',
                    'ir_segundo': 'Al jugar segundo', 'interrupcion': 'Ante interrupciones', 'sinergia_verificada': 'Relación concreta'}
            fields = {label: prose(fix[k]) for k, label in keys.items()}
            card.update(fields=[dict(title=k, text=v) for k, v in fields.items()], practical=[dict(title=k, text=v) for k, v in fields.items()],
                        delivery_fields=fields, operational={'Acción': fix['que_hace'], 'Coste': fix['coste'], 'Restricciones': fix['restricciones']},
                        learning={'Cuándo jugarla primero': fix['ir_primero'], 'Cuándo jugarla segundo': fix['ir_segundo'], 'Limitación a respetar': fix['restricciones']},
                        sources=[fix['fuente']], source_revision='V12')
            # Display alias only; native card name, ID, quantity and slots stay.
            if fix['cid'] == 16331:
                card['name'] = fix['nombre']

        original = {r['id'] for r in data['routes']}
        assert original == {r['id'] for r in visual.values() if int(r['deckId']) == deck_id}
        for route in data['routes']:
            v = visual[route['id']]
            assert len(v['pasos']) == len(route['steps']), route['id']
            route.update(title=v['titulo'], purpose=v['proposito'], opening=v['estadoInicial'], result=v['campoFinal'])
            for step, incoming in zip(route['steps'], v['pasos']):
                assert step['number'] == incoming['numero']
                step.update(action=incoming['accion'], compact_action=incoming['accion'], detail=incoming['detalle'],
                            visual_detail=incoming['detalle'], photos=[picture(p) for p in incoming['cartas']])
            panel = panels.get(route['id'])
            if panel:
                route['documentary_final'] = panel['final_state_text']
            filename = route['id'].replace(':', '_') + '.webp'
            file = source / 'infografias_a10_v09' / filename
            assert file.is_file(), ('missing infographic', route['id'])
            dest = f'assets/editorial/{TARGET}/infographics/{filename}'
            assets[dest] = file.read_bytes()
            route['infographic'] = './' + dest
        for incoming in [r for r in deltas if int(r['deck_id']) == deck_id]:
            hand = incoming['mano_inicial']
            assert all(any(s['section'] == 'main' for s in cards[cid]['slots']) for cid in hand), incoming['id']
            assert all(n <= sum(s['quantity'] for s in cards[cid]['slots'] if s['section'] == 'main') for cid, n in Counter(hand).items())
            for key, zone in [('requisitos_deck', 'main'), ('requisitos_extra', 'extra')]:
                assert all(any(s['section'] == zone for s in cards[cid]['slots']) for cid in incoming.get(key, [])), (incoming['id'], key)
            steps = [dict(number=p['numero'], action=p['accion'], compact_action=p['accion'], detail=prose(p.get('resultado')),
                          visual_detail=prose(p.get('resultado')), photos=[photo(cid,p.get('photo_label','')) for cid in p['cids']], requirements=[], resources=[], board=[])
                     for p in incoming['pasos']]
            assert [s['number'] for s in steps] == list(range(1, len(steps)+1))
            opening = '\n'.join(f'{label}: {prose(incoming[k])}' for k, label in [('campo_inicial', 'Campo propio'), ('cementerio_inicial', 'Cementerio propio'),
                ('estado_rival', 'Estado rival'), ('turno_y_fase', 'Momento')] if incoming.get(k))
            result = incoming.get('campo_final', incoming.get('campo_final_turno_propio', ''))
            resources = [dict(zone=label, text=prose(incoming[k])) for k, label in [('mano_final', 'Mano'), ('cementerio_final', 'Cementerio'),
                ('extra_final', 'Extra'), ('recursos_utilizados', 'Recursos utilizados'), ('campo_final_turno_rival', 'Turno rival')] if incoming.get(k)]
            sources = incoming.get('evidencia_oficial', [f'https://www.db.yugioh-card.com/yugiohdb/card_search.action?ope=2&cid={cid}&request_locale=en' for cid in incoming.get('fuentes_oficiales_cid', [])])
            route = dict(id=incoming['id'], name=incoming['titulo'], title=incoming['titulo'], purpose=incoming['objetivo'],
                family='Respuesta' if 'SUPERPOLY' in incoming['id'] else 'Desarrollo', category=prose(incoming.get('clasificacion', incoming.get('categoria', ''))).replace('_', ' ').capitalize(),
                opening=opening, initial_label='Mano inicial', hand_photos=[photo(cid, 'En mano') for cid in hand],
                initial_details='\n'.join(incoming.get('costes', []) + incoming.get('restricciones', [])), steps=steps, result=result,
                resources=resources, board=[], visual=True, historical=False, editorial_status='conditional',
                final_constraints=incoming.get('restricciones', []), sources=sources, turn_order='second' if 'SUPERPOLY' in incoming['id'] else 'any',
                variants=incoming.get('variantes', []), interruptions=incoming.get('ante_interrupciones', []),
                plans={'first': incoming.get('ir_primero', ''), 'second': incoming.get('ir_segundo', '')},
                source_extension=incoming.get('source_extension', 'V12' if ':V12:' in incoming['id'] else 'V11'),
                technical='\n\n'.join(prose(incoming.get(k)) for k in ['variantes', 'ante_interrupciones', 'ir_primero', 'ir_segundo'] if incoming.get(k)))
            for cid, zone in FINAL_PHOTOS.get(incoming['id'], []):
                route['board'].append(photo(cid, 'Resultado descrito · posición sin determinar', zone=zone))
            # No invented spatial replay: keep the supplied final text and the
            # explicit partial board, usable by both reader and native editor.
            data['routes'].append(route)

        route_ids = {r['id'] for r in data['routes']}
        assert original <= route_ids and len(route_ids) == len(data['routes'])
        dossier = next(d for d in dossiers if int(d['deck_id']) == deck_id)
        data['delivery_dossier'] = {k: v for k, v in dossier.items() if k.startswith('A')}
        data['side_plans'] = copy.deepcopy([s for s in sides if int(s['deck_id']) == deck_id])
        for plan in data['side_plans']:
            assert any(s['section'] == 'side' and s['quantity'] >= plan['copies'] for s in cards[plan['cid']]['slots'])
            plan['photo'] = photo(plan['cid'])
            if plan.get('leaving_cid'):
                plan['leaving_photo'] = photo(plan['leaving_cid'])
            if plan.get('editorial_outgoing_proposal'):
                plan['outgoing_candidate'] = copy.deepcopy(plan['editorial_outgoing_proposal'])
            assert plan['permitted_inventory_changes'] is False
        data['matchup_plans'] = next(p['casos'] for p in matchups if int(p['deck_id']) == deck_id)
        data['format_context'] = next(p for p in formats if int(p['deck_id']) == deck_id)
        data['improvement_experiments'] = next(p['proposals'] for p in improvements if int(p['deck_id']) == deck_id)
        data['decision_exercises'] = [p for p in decision_exercises if int(p['deck_id']) == deck_id]
        # Replace exercises by stable ID; retain the earlier 33 authored hands.
        exercises = {p['id']: p for p in data['practice']}
        for p in [p for p in practice if int(p['deck_id']) == deck_id]:
            assert p['route_id'] in route_ids
            exercises[p['training_id']] = dict(id=p['training_id'], title=p['title'], challenge=p['challenge']['initial_state']+'\n'+p['challenge']['question'],
                answer=p['challenge']['expected_final'], explanation='\n'.join(p.get('check_before_play', [])), routes=[p['route_id']], branches=p.get('branches', []))
        for p in [p for p in interruptions if int(p['deck_id']) == deck_id]:
            assert p['ruta'] in route_ids
            key = f'{deck_id}:PRACTICE:V12'
            exercises[key] = dict(id=key, title=p['titulo'], challenge=p['estado']+'\n'+p['pregunta'], answer=p['respuesta'],
                explanation='\n'.join(p[k] for k in ['recurso_irrecuperable', 'alternativa', 'prueba']), routes=[p['ruta']])
        data['practice'] = list(exercises.values())
        for card in cards.values():
            related = [r['id'] for r in data['routes'] if any(p.get('cid') == card['cid'] or p['image'] == card['image'] for s in r['steps'] for p in s['photos'])]
            card['related_routes'] = list(dict.fromkeys(card.get('related_routes', []) + related))
            card['route_links'] = [dict(id=r['id'], step=next(i for i, s in enumerate(r['steps']) if any(p.get('cid') == card['cid'] or p['image'] == card['image'] for p in s['photos'])))
                                   for r in data['routes'] if r['id'] in related]
        apply_overrides(data, source)
        records[deck_id] = data
        report.append(dict(deck_id=deck_id, name=data['name'], cards=len(cards), routes=len(route_ids),
            steps=sum(len(r['steps']) for r in data['routes']), side_plans=len(data['side_plans']), practice=len(data['practice']), skills=len(data['skills'])))
    assert sum(len(d['routes']) for d in records.values()) == 127
    manifest = {'version': TARGET, 'decks': {}}
    for deck_id, data in records.items():
        raw = encoded(data)
        name = f'assets/editorial/{TARGET}/{deck_id}.json'
        assets[name] = raw
        manifest['decks'][str(deck_id)] = dict(path='./'+name, cards=len(data['cards']), routes=len(data['routes']), sha256=digest(raw), presentation='curated-v62')
    assets[f'assets/editorial/{TARGET}/manifest.json'] = encoded(manifest)
    return assets, manifest, dict(decks=report, totals={k: sum(d[k] for d in report) for k in ['cards', 'routes', 'steps', 'side_plans', 'practice', 'skills']},
        scope='A01-A10', inventory_writes=0, sporting_writes=0, certified_replays=0,
        original_routes=108, new_v11=13, new_v12=1, formalized_v12=2, cumulative_deltas=3,
        source_hashes={name: digest((source/name).read_bytes()) for name in sorted(consumed)})


def main():
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('--live-snapshot', type=Path, required=True)
    p.add_argument('--report', type=Path, required=True)
    p.add_argument('--apply', action='store_true')
    args = p.parse_args()
    assets, manifest, report = build(read(args.live_snapshot))
    report['applied'] = args.apply
    report['asset_hashes'] = {k: digest(v) for k, v in assets.items()}
    if args.apply:
        for name, raw in assets.items():
            path = APP/name
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_bytes(raw)
        policy_path = APP/'assets/editorial/strategy-content-state.json'
        policy = read(policy_path)
        policy.update(version=TARGET, checked_studies=manifest['decks'])
        temp = policy_path.with_suffix('.json.tmp')
        temp.write_bytes(encoded(policy))
        temp.replace(policy_path)
    args.report.parent.mkdir(parents=True, exist_ok=True)
    args.report.write_bytes(encoded(report))
    print(json.dumps({'applied': args.apply, **report['totals']}))


if __name__ == '__main__':
    main()
