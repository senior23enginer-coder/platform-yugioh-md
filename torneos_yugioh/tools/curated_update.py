"""Project the current A01–A10 delivery; never mutate native inventories."""
import re
from curated_links import key


def compact_routes(source, deck_id, sections):
    raw = (source/'GUIAS_VISUALES_COMPACTAS_11_DECKS.md').read_text(encoding='utf-8')
    return [(re.search(r'`(\d+:A09:[^`]+)`', title)[1], title, body)
            for title, body in sections(raw, 3)
            if re.search(rf'`{deck_id}:A09:[^`]+`', title)]


def prose(raw, clean):
    raw = re.sub(r'!\[([^\]]*)\]\([^)]+\)', r'\1', raw)
    raw = re.sub(r'\[([^\]]+)\]\([^)]+\)', r'\1', raw)
    raw = re.sub(r'`\d+:A09:[^`]+`', 'la ruta relacionada', raw)
    raw = re.sub(r'\[V\d+\]', '', raw)
    raw = re.sub(r'NO_EJECUTABLE_TCG', 'no realizable en TCG Advanced', raw)
    raw = re.sub(r'^#{1,6}\s*', '', raw, flags=re.M)
    raw = re.sub(r'(?:guias_a10/)?\b[A-Z0-9_]+\.md\b', 'la guía relacionada', raw)
    return clean(raw)


def expanded(source, deck_id, routes, photos, clean, sections):
    """Read all additional stable IDs from the compact library and full guides.

    Numbered compact lessons remain the player-facing sequence. The original
    source is retained separately for costs, branches and exact initial state;
    alternatives sharing a source are explicitly alternatives, never merged.
    """
    active = {r['id'] for r in routes}
    guides = []
    multiple = ('TRES_SINCRONIAS', 'KNIGHT_Y_BARRICADEBORG', 'GUIA_V12', 'GUIA_V13')
    for file in sorted((source/'guias_a10').glob('*.md')):
        if file.name.endswith('_GUIAS_A10.md') or 'REFERENCIAS' in file.name:
            continue
        raw = file.read_text(encoding='utf-8')
        ids = list(dict.fromkeys(re.findall(r'`(\d+:A09:[^`]+)`', raw)))
        declared = ids if any(part in file.name for part in multiple) else ids[:1]
        guides.append((file, raw, declared))
    result = []
    for rid, title, body in compact_routes(source, deck_id, sections):
        if rid in active:
            continue
        found = [(p, raw) for p, raw, declared in guides if rid in declared]
        assert len(found) == 1, (rid, 'full guide not uniquely declared', [p.name for p, _ in found])
        file, full = found[0]
        # A multi-deck source has one H2 per route. Do not import another deck's
        # pictures or restrictions into this route's details.
        explicit = [(h, b) for h,b in sections(full, 2) if f'`{rid}`' in h]
        if explicit:
            full = explicit[0][1]
        raw_steps = [(int(m[1]), m[2]) for m in re.finditer(
            r'^\*\*Paso (\d+) [—–-] .*?\*\*\s*\n(.*?)(?=^\*\*Paso |^\*\*Campo final:|\Z)', body, re.M|re.S)]
        if not raw_steps:
            raw_steps = [(int(m[1]), m[2]) for m in re.finditer(
                r'^(\d+)\. (.*?)(?=^\d+\. |\n\n|\Z)', body, re.M|re.S)]
        if not raw_steps:
            for heading, part in sections(full, 3):
                m = re.match(r'(?:Paso )?(\d+)[. —–-]+(.*)', heading)
                if m:
                    raw_steps.append((int(m[1]), f'**{m[2]}**\n{part}'))
        assert raw_steps and [n for n,_ in raw_steps] == list(range(1, len(raw_steps)+1)), rid
        def pics(raw):
            return photos(raw.replace('(imagenes/', '(../imagenes/'))
        full_photos = pics(full)
        assert full_photos, rid
        first_number = re.search(r'^1\. |^\*\*Paso 1', body, re.M)
        opening = body[:first_number.start()] if first_number else full.split('\n### ')[0].split('\n## ')[0]
        if not prose(opening, clean).strip():
            opening = full.split('\n## ')[0]
        # Complete requirements from the full source remain visible in the
        # initial-state disclosure even when the compact sequence starts at 1.
        initial = full.split('\n### ')[0].split('\n## ')[0]
        steps = []
        previous = []
        for n, raw in raw_steps:
            linked = pics(raw)
            if linked:
                previous = linked
            else:
                # A rule/check/result step may have no new movement. Reuse an
                # identified illustration, explicitly as context, not a summon.
                linked = [dict(p, label='Referencia de la línea · sin movimiento adicional') for p in (previous[-1:] or full_photos[:1])]
            action = prose(raw, clean)
            steps.append(dict(number=n, action=action, compact_action=action, detail=action,
                photos=linked, requirements=[], board=[], resources=[dict(zone='Estado documentado', text=action)]))
        final = next((b for h,b in sections(full, 2) if h.startswith('Campo final')), raw_steps[-1][1])
        name = re.sub(r'`\d+:A09:[^`]+`', '', title).strip(' ·—–-')
        if 'Guía adicional' in name:
            name = re.sub(r'^# ', '', full.splitlines()[0])
        # State stays partial unless an actual zone was explicitly specified.
        result.append(dict(id=rid, name=clean(name), title=clean(name), family='Alternativas y respuestas',
            purpose='Opciones condicionadas', category='LÍNEA CONDICIONADA', opening=prose(opening, clean),
            initial_details=prose(initial, clean), hand_photos=pics(opening), result=prose(final, clean),
            steps=steps, board=[], resources=[dict(zone='Resultado documentado', text=prose(final, clean)),
                dict(zone='Zonas no especificadas', text='Estado parcial: no se presuponen otras cartas, posiciones ni materiales.')],
            technical=prose('\n'.join(line for line in full.splitlines()
                if not re.match(r'^\*\*(?:Estado|Validación|Cotejo|Formato de importación)', line)), clean),
            sources=sorted(set(re.findall(r'https://www\.db\.yugioh-card\.com/[^\s)]+', full)))))
    return result


def recommendations(source, deck_id, cards, routes, aliases, clean, sections):
    raw = (source/'OPTIMIZACION_SINERGIA_Y_COPIAS_11_DECKS.md').read_text(encoding='utf-8')
    body = next(b for h,b in sections(raw, 3) if re.match(rf'^{deck_id} [—–-]', h))
    rows = []
    zone = 'main'
    matrix = False
    for line in body.splitlines():
        if line.startswith('| Carta del Extra'):
            zone = 'extra'
        if line.startswith('| Zona y cartas'):
            matrix = True
        if not line.startswith('|') or re.match(r'^\|[\s|:-]+$', line):
            continue
        cells = [c.strip().replace('**', '') for c in line.strip('|').split('|')]
        if matrix:
            if len(cells) == 5 and re.match(r'^(Main|Extra|Side|Reserva): ', cells[0]):
                section, name = cells[0].split(': ',1)
                matches = [c for c in cards if key(aliases[c['cid']]) == key(name)]
                assert len(matches) == 1, (deck_id, 'recommendation matrix identity', name)
                c = matches[0]
                c.setdefault('construction', []).append(dict(zone=section, copies=cells[1],
                    label=prose(cells[2], clean), proposal=prose(cells[3], clean), reason=prose(cells[4], clean)))
            continue
        if len(cells) != 4 or not cells[1].isdigit() or not cells[2].isdigit():
            continue
        name, current, proposed, reason = cells
        current, proposed = int(current), int(proposed)
        name = re.sub(r'\s*\((?:obtener|mover)[^)]*\)', '', name)
        linked = [c for c in cards if key(aliases[c['cid']]) == key(name) or key(c['name']) == key(name)]
        assert len(linked) <= 1, (deck_id, name)
        card = linked[0] if linked else None
        # A suggested outside card is a proposal only, never a playable resource.
        group = ('Retirar' if proposed == 0 else 'Promover de Reserva' if 'Reserva' in reason and proposed > current
                 else 'Aumentar copias' if proposed > current and current else 'Alternativa' if not current
                 else 'Reducir' if proposed < current else 'Conservar')
        rows.append(dict(name=name, card_id=card['id'] if card else None, zone=zone,
            current=current, proposed=proposed, group=group, reason=prose(reason, clean),
            routes=card['related_routes'] if card else []))
    assert all(c.get('construction') for c in cards), (deck_id, 'incomplete construction matrix')
    assert sum(r['proposed']-r['current'] for r in rows if r['zone']=='main') == 0, deck_id
    main = [c for c in cards if any(s['section']=='main' for s in c['slots'])]
    direct = sum('Sinergia directa' in c['synergy']['labels'] for c in main)
    indirect = sum('Sinergia indirecta' in c['synergy']['labels'] for c in main)
    extra_advice = []
    special = {665:'DECK_665_EXTRA_USO_REAL_ALTERNATIVAS.md',707:'DECK_707_EXTRA_GAIA_SIN_MATERIALES.md'}
    if deck_id in special:
        raw = (source/special[deck_id]).read_text(encoding='utf-8')
        for title, part in sections(raw, 2):
            if 'Fuentes' in title or 'fuentes' in title:
                continue
            paragraphs = []
            for paragraph in re.split(r'\n\s*\n', part):
                paragraph = paragraph.strip()
                if paragraph.startswith('|'):
                    for line in paragraph.splitlines():
                        if re.match(r'^\|[\s|:-]+$', line):continue
                        cells = [prose(c.strip(), clean) for c in line.strip('|').split('|')]
                        paragraphs.append(' · '.join(cells))
                        if deck_id == 707:
                            cid = re.search(r'CID (\d+)', line)
                            if cid:
                                c = next(c for c in cards if c['cid']==int(cid[1]))
                                c['extra_explanation'] = 'Materiales necesarios: '+cells[2]+'. '+cells[4]
                else:
                    paragraphs.append(prose(paragraph, clean))
            extra_advice.append(dict(title=clean(title), paragraphs=paragraphs))
    return dict(main_identities=len(main), direct=direct, indirect=indirect,
        labelled=sum(any(v in c['synergy']['labels'] for v in ['Sinergia directa','Sinergia indirecta']) for c in main),
        proposals=rows, extra_advice=extra_advice, context=prose(body.split('\n| Carta')[0], clean),
        precautions=prose('\n'.join(line for line in body.splitlines()
            if line.startswith('**Extra / Side / Reserva') or line.startswith('**Balance')), clean))
