"""Bind V16 presentation to native cards and preserved documentary routes."""
import json
import re
from pathlib import Path
from curated_update import prose


def apply_visual_delivery(source, deck_id, cards, routes, decisions, photos, clean, proposed):
    raw = json.loads((source/'GUIAS_Y_DECISIONES_V16.json').read_text(encoding='utf-8'))
    base_routes=[r for r in raw['rutas'] if not r.get('source_extension')]
    assert len(base_routes) == 105 and len(raw['decisiones']) == 45
    incoming = {r['id']:r for r in base_routes if int(r['deckId']) == deck_id}
    assert set(incoming) == {r['id'] for r in routes}
    by_cid = {c['cid']:c for c in cards}

    def display(value):
        value = prose(value, clean)
        value = re.sub(r'\s*\((?:CID\s*)?\d{4,6}(?:,\s*[^)]*)?\)', '', value)
        return value

    def initial_or_result(value):
        value = display(value)
        # Some legacy paragraphs name a card followed by an unparenthesized
        # CID. These are identity references, not game statistics.
        for cid in by_cid:
            value = re.sub(rf'\b{cid}\b', '', value)
        return re.sub(r'\s{2,}', ' ', value).strip()

    def picture(item):
        if '/skills/' in item['imagen']:
            assert Path(item['imagen']).name.startswith(str(deck_id)+'_')
            return dict(photos(f"![{item['nombre']}]({item['imagen']})")[0], label='Skill externa')
        cid = int(Path(item['imagen']).stem)
        assert cid in by_cid, (deck_id, 'foreign presentation photograph', cid)
        photo = dict(photos(f"![{item['nombre']}]({item['imagen']})")[0])
        verb = display(item.get('accion', ''))
        if re.search(r'CONTEXTO|YA EN CAMPO|ESTADO', verb, re.I):
            verb = 'Referencia del paso'
        photo['label'] = verb
        return photo

    for r in routes:
        v = incoming[r['id']]
        assert len(v['pasos']) == len(r['steps']), r['id']
        r['title'], r['purpose'] = display(v['titulo']), display(v['proposito'])
        r['category'] = display(v['tipo'])
        r['visual'] = True
        r['historical'] = v['grupo'] == 'referencia_historica'
        assert r['historical'] == bool(r.get('restriction')), r['id']
        r['opening'] = initial_or_result(v['estadoInicial'])
        # Only an explicit opening hand is illustrated as such. Photographs
        # from a mixed initial-state paragraph are not silently put in hand.
        previous_initial = r['hand_photos'] if not r.get('technical') else []
        r['hand_photos'] = [picture(p) for p in v['cartasEntrada'] if '/skills/' not in p['imagen']] or previous_initial
        r['initial_label'] = 'Mano inicial' if v['cartasEntrada'] else 'Recursos iniciales documentados'
        for p in r['hand_photos']:p['label'] = 'En mano' if v['cartasEntrada'] else 'Zona indicada en la descripción'
        r['initial_details'] = display(r.get('initial_details',''))
        r['result'] = initial_or_result(v['campoFinal'])
        for step, vs in zip(r['steps'], v['pasos']):
            assert step['number'] == vs['numero']
            step['compact_action'] = display(vs['accion'])
            step['visual_detail'] = display(vs['detalle'])
            illustrations = [picture(p) for p in vs['cartas']]
            # Keep original action bindings if the V16 source supplies a
            # generic context card in place of a movement illustration.
            step['photos'] = illustrations or step['photos']
            step['chains'] = list(dict.fromkeys(re.findall(r'\bCL\d+\b', vs['detalle'])))
            step['phases'] = list(dict.fromkeys(re.findall(r'Main Phase(?: 2)?|Battle Phase|End Phase|Standby Phase', vs['detalle'], re.I)))
        if v.get('tableroFinal'):
            board = v['tableroFinal']
            r['board'] = []
            for key, zone in [('monstruos','MONSTER'), ('magiasTrampas','SPELL')]:
                for entry in board[key]:
                    p = picture(entry)
                    p.update(zone=zone, slot=entry['zona'], position=entry.get('posicion',entry.get('estado','')),
                             materials=entry.get('materiales',[]), statistic=entry.get('estadistica',''))
                    r['board'].append(p)
            r['final_constraints'] = [display(s) for s in board['restricciones']]
            r['next_phase'] = display(board['otroTurno'])
        # Else keep only explicit placements from the full A10 guide. Missing
        # position/material data is displayed as partial, never synthesized.
        r['spatial_detail'] = bool(v.get('tableroFinal'))
        for field in ['technical']:
            if r.get(field):r[field] = display(r[field])

    incoming_decisions = [d for d in raw['decisiones'] if int(d['deckId'])==deck_id]
    assert len(incoming_decisions) == len(decisions)
    for d, v in zip(decisions, incoming_decisions):
        assert v['titulo'].split(' — ')[0] == d['id'], (deck_id,v['titulo'],d['id'])
        d['situation'] = display(v['situacion'])
        d['answer'] = display(v['decision'])
        d['error'] = display(v['error'])
        assert set(v['referencias']) <= set(incoming)
        d['routes'] = v['referencias']
    optimization = next(o for o in raw['optimizaciones'] if int(o['deckId'])==deck_id)
    assert optimization['cambiosAplicados'] is False
    profile = optimization['perfil']
    balance = display(profile['balance'])
    if deck_id == 665:
        cuts = sum(max(0,p['current']-p['proposed']) for p in proposed['proposals'] if p['zone']=='main')
        balance = re.sub(r'reducir \d+ copias', f'reducir {cuts} copias', balance)
    scenarios = []
    for s in profile['escenarios']:
        assert s['ruta'] in incoming
        scenarios.append(dict(opponent=display(s['oponente']),situation=display(s['situacion']),
            plan=display(s['plan']),tradeoff=display(s['tradeoff']),routes=[s['ruta']]))
    return dict(objective=display(optimization['objetivo']),
        strengths=[display(s) for s in profile['fortalezas']],blind_spots=[display(s) for s in profile['puntosCiegos']],
        balance=balance), scenarios
