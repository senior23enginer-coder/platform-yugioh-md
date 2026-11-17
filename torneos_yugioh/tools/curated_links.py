"""Exact card navigation and compact lessons from the owner's current delivery."""
import re
import unicodedata
from pathlib import Path


def key(value):
    return re.sub(r'\s+', ' ', unicodedata.normalize('NFC', value)).strip().casefold()


def enrich(source, deck_id, cards, routes, decisions, practice, aliases, photos, clean, sections, fields, table):
    def deck_section(filename):
        text = (source/filename).read_text(encoding='utf-8')
        return next(body for title, body in sections(text, 2) if re.match(rf'^{deck_id} [—–-]', title))

    lookup = {}
    for card in cards:
        for name in [card['name'], aliases[card['cid']]]:
            lookup.setdefault(key(name), []).append(card)

    def resolve(name):
        found = {c['cid']: c for c in lookup.get(key(name), [])}
        assert len(found) == 1, (deck_id, 'ambiguous/unbound identity', name)
        return next(iter(found.values()))

    refs = lambda body: list(dict.fromkeys(re.findall(r'`(\d+:A09:[^`]+)`', body)))
    active = {r['id']: r for r in routes}
    linked_count = 0
    for title, body in sections(deck_section('VINCULACION_TOTAL_370_CARTAS_11_DECKS.md'), 3):
        if title.startswith('Skill externa'):
            continue
        card = resolve(title)
        assert 'learning' not in card
        props = fields(body)
        from curated_update import prose
        operational = {
            'Acción operativa identificada [V14]': 'Acción',
            'Uso dentro de este deck [V14]': 'Uso en este deck',
            'Condición o ventana de la acción [V14]': 'Condición y ventana',
            'Coste, descarte o material [V14]': 'Coste y materiales',
        }
        card['operational'] = {title: prose(props[k], clean) for k,title in operational.items() if props.get(k)}
        assert len(card['operational']) == 4, (deck_id, card['cid'], 'incomplete operational explanation')
        card['learning'] = {k: clean(props.get(k, '')) for k in [
            'Para qué sirve', 'Aplicación concreta registrada', 'Limitación a respetar',
            'Cuándo jugarla primero', 'Cuándo jugarla segundo']}
        card['related_routes'] = [rid for rid in refs(body) if rid in active]
        card['related_decisions'] = [d['id'] for d in decisions if re.search(r'Decisión '+re.escape(d['id'])+r'\b', body)]
        card['related_practice'] = [p['id'] for p in practice if re.search(r'Práctica '+re.escape(p['id'])+r'\b', body)]
        linked_count += 1
    assert linked_count == len(cards), (deck_id, linked_count, len(cards))

    extra_count, extra_mismatches = 0, []
    for title, body in sections(deck_section('CONEXIONES_EXTRA_A_JUGADAS_11_DECKS.md'), 3):
        card = resolve(title)
        is_extra = any(s['section'] == 'extra' for s in card['slots'])
        if not is_extra:
            extra_mismatches.append(dict(card=card['name'], cid=card['cid'], native_slots=card['slots']))
        card['extra_navigation'] = is_extra
        card['extra_explanation'] = prose(body, clean)
        card['related_routes'] = list(dict.fromkeys(card['related_routes']+[rid for rid in refs(body) if rid in active]))
        extra_count += 1

    compact_count = 0
    compact_ids = []
    from curated_update import compact_routes
    for rid, title, body in compact_routes(source, deck_id, sections):
        ids = refs(title)
        assert len(ids) == 1, (deck_id, title)
        rid = ids[0]
        compact_ids.append(rid)
        assert rid in active, (rid, 'missing published documentary route')
        alert = re.search(r'\*\*ALERTA REGLAMENTARIA[^\n]*utiliza[^\n]*', body)
        if alert or 'Grupo de biblioteca:** Referencia histórica' in body or (rid == '650:A09:LUCINA:PATRON:DAWN_DRAGSTER09'):
            active[rid]['restriction'] = 'No disponible como jugada TCG Advanced.'
        steps = list(re.finditer(r'^\*\*Paso (\d+) [—–-] (.*?)\*\*\s*\n(.*?)(?=^\*\*Paso |^\*\*Campo final:|\Z)', body, re.M|re.S))
        compact_count += len(steps)
        if not steps:
            continue
        route = active[rid]
        assert len(steps) == len(route['steps']), (rid, 'compact step mismatch')
        for original, match in zip(route['steps'], steps):
            assert original['number'] == int(match[1]), (rid, original['number'], match[1])
            original['compact_action'] = clean(match[2])
            illustrations = photos(match[3].replace('(imagenes/', '(../imagenes/'))
            assert illustrations, (rid, match[1], 'no compact photographs')
            # Compact labels are illustrative, not a second source of zone state.
            # Keep labels from the full structured step when that identity occurs.
            previous = {p['image']: p.get('label', '') for p in original['photos']}
            for p in illustrations:
                p['label'] = previous.get(p['image'], 'Carta relacionada')
            original['photos'] = illustrations

    motor = deck_section('MOTORES_APLICADOS_Y_DECISIONES_11_DECKS.md')
    introduction = motor.split('\n- ')[0].strip()
    choices = []
    for match in re.finditer(r'^- (.*?)(?=^- |^\*\*Decisión crítica:|\Z)', motor, re.M|re.S):
        raw = match[1].strip()
        if refs(raw) and not any(rid in active for rid in refs(raw)):
            continue
        choices.append({'text': clean(re.sub(r'`\d+:A09:[^`]+`', 'la ruta enlazada', raw)),
                        'routes': [rid for rid in refs(raw) if rid in active]})
    critical = re.search(r'^\*\*Decisión crítica:\*\*\s*(.*)', motor, re.M|re.S)
    engine = dict(introduction=clean(introduction), choices=choices, precaution=clean(critical[1]) if critical else '')

    # All photographs identify references, not necessarily a summon. Exact
    # supplemental IDs extend navigation from the explicitly named source cards.
    for card in cards:
        card['related_routes'] = list(dict.fromkeys(card['related_routes']+[
            r['id'] for r in routes if any(p['image'] == card['image'] for s in r['steps'] for p in s['photos'])]))
        card['route_links'] = [{'id': rid, 'step': next((i for i,s in enumerate(active[rid]['steps'])
            if any(p['image'] == card['image'] for p in s['photos'])), 0)} for rid in card['related_routes']]
        for link in card['route_links']:
            if card['cid'] == 13974 and link['id'] == '665:A09:FENRIR:BIRTH:DARKARMED10':
                link['step'] = 3
            if card['cid'] == 17746 and link['id'] == '656:A09:SUPERPOLY:GARURA10':
                link['step'] = 2
    return engine, dict(cards=linked_count, extra_index_entries=extra_count, extra_zone_mismatches=extra_mismatches,
                        historical_routes=sum(bool(r.get('restriction')) for r in routes),
                        native_extra=sum(any(s['section'] == 'extra' for s in c['slots']) for c in cards),
                        compact_steps=compact_count, compact_routes=len(compact_ids))


def additional(source, deck_id, photos, clean, sections, table):
    patterns = {665: ('DECK_665_DARK_ARMED_GUIA_ADICIONAL.md', 'Fenrir + Birth → Dark Armed', 13974),
                656: ('DECK_656_GARURA_SUPERPOLY_GUIA_ADICIONAL.md', 'Súper Polimerización → Garura', 17746)}
    if deck_id not in patterns:
        return []
    filename, title, target = patterns[deck_id]
    raw = (source/'guias_a10'/filename).read_text(encoding='utf-8')
    rid = re.search(r'`(\d+:A09:[^`]+)`', raw)[1]
    header = raw.split('\n## Paso ')[0]
    opening = '\n'.join(line.removeprefix('- ') for line in header.splitlines() if re.match(
        r'^- \*\*(?:Mano mínima|Deck|Estado inicial|Extra propio|Campo rival requerido|Ventana|Cuándo usar)', line))
    target_photo = photos(f'![Destino](../imagenes/tcg/{target}.jpg)')[0]
    final = next(body for heading, body in sections(raw, 2) if heading.startswith('Campo final'))
    final_rows = [dict(zone=row[0], text=row[1]) for row in table(final) if len(row) == 2 and row[0] != 'Zona']
    resource_zones = ['Mano', 'Monstruos propios', 'Magias y trampas propias', 'Cementerio', 'Destierro', 'Deck', 'Extra Deck', 'Campo rival']
    steps = []
    for heading, body in sections(raw, 2):
        m = re.match(r'Paso (\d+) [—–-] (.*)', heading)
        if not m:
            continue
        linked = photos(body) or [dict(target_photo)]
        action_labels = {
            665: [['Invoca Especialmente'], ['Activa', 'Añade a mano'], ['Activa', 'Invoca Normalmente'],
                  ['Material Xyz', 'Material Xyz', 'Invoca Xyz'], ['Desacopla · destruye · destierra del GY']],
            656: [['Comprueba materiales'], ['Activa · descarta como coste', 'Destino de Fusión'], ['Invoca por Fusión']]
        }[deck_id][int(m[1])-1]
        assert len(action_labels) == len(linked)
        for p, label in zip(linked, action_labels):
            p['label'] = label
        detail = clean(re.sub(r'!\[[^\]]*\]\([^)]+\)', '', body))
        restrictions = [sentence for sentence in re.split(r'(?<=[.;])\s+', detail)
                        if re.search(r'no puede|no podrá|no es|como coste|si falla|sin |no uses|nombres diferentes', sentence, re.I)]
        steps.append(dict(number=int(m[1]), action=detail, compact_action=m[2], detail=detail,
                          photos=linked, requirements=restrictions, resources=[dict(zone=z, text='Estado parcial: las cartas no especificadas en este paso no se presuponen disponibles.') for z in resource_zones], board=[]))
    board = [dict(target_photo, zone='MONSTER')]
    if deck_id == 665:
        board.append(dict(photos('![Birth](../imagenes/tcg/17815.jpg)')[0], zone='SPELL'))
    return [dict(id=rid, name=title, title=title, family='Romper campo', purpose='Romper campo',
                 category='RESPUESTA CONDICIONAL', opening=clean(opening), hand_photos=photos(
                     '![Fenrir](../imagenes/tcg/17768.jpg) ![Birth](../imagenes/tcg/17815.jpg)' if deck_id == 665
                     else '![Súper Polimerización](../imagenes/tcg/7445.jpg)'),
                 result=' '.join(r['text'] for r in final_rows), steps=steps, board=board,
                 resources=final_rows+[dict(zone='Información no especificada', text='Otras cartas, posiciones y recursos no definidos por esta ruta permanecen desconocidos.')])]
