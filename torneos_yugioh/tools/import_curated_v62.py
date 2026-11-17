"""Import current teaching material through A10, without editorial states.

The 22 deck/guide Markdown files, practical exercises and their photographs
supply public content. Functional labels and competitive purposes remain
documentary descriptions; they do not establish verified synergy percentages.
Inventories are read for identity binding; no database writes are performed.
"""
import argparse
import hashlib
import json
import re
import shutil
from pathlib import Path
from curated_links import additional, enrich
from curated_update import expanded, recommendations
from curated_v16 import apply_visual_delivery
from curated_endboards import apply_endboards
from combo_source_sync import apply_overrides
from requested_kashtira import apply_requested_kashtira

APP = Path(__file__).resolve().parents[1]
SOURCE = APP / 'context/gpt/01_INFORMACION_A_IMPORTAR'
OUT = APP / 'assets/editorial/20261113-endboards'
VERSION = '20261113-endboards-161'
IDS = {650, 653, 656, 665, 670, 684, 687, 704, 705, 706, 707}
REVIEW = re.compile(r'pendiente|borrador|certific|cotej|auditor|verific|sin.iniciar|porcentaje|confianza|interpretación táctica editorial', re.I)
PHOTOS = r'!\[([^\]]*)\]\((\.\./imagenes/[^)]+)\)'


def sha(raw):
    return hashlib.sha256(raw).hexdigest()


def clean(text):
    text = re.sub(r'\[(?:X|~| )\].*', '', text, flags=re.S)
    text = re.sub(r'\*\*|`', '', text).strip()
    text = re.sub(r'\bA(?:0[1-9]|1[0-3])(?:\.\d+)?\b', '', text)
    text = re.sub(r'\s*\(\s*\)', '', text)
    text = text.replace('Ogier Ogier', 'Ogier').replace('no certifica otro monstruo', 'no aporta por sí sola otro monstruo')
    text = re.sub(r'\s{2,}', ' ', text)
    return text.strip(' :;·—')


def useful(text):
    # Omit uncertain claims rather than turning a review note into game advice.
    text = clean(text)
    return bool(text) and not REVIEW.search(text) and not re.search(
        r'sin coste independiente identificado|operaciones detectadas|se debe[n]? |comprobar |genéric|nombre histórico|no altera las cantidades|síntesis no redundante|sin procedimiento demostrado|aún debe enlazarse', text, re.I)


def subsections(text, level):
    return [(m[1], m[2]) for m in re.finditer(
        rf'^{"#" * level} (.*?)\n(.*?)(?=^#{{1,{level}}} |\Z)', text, re.M | re.S)]


def fields(text):
    return {clean(m[1]): m[2].strip() for m in re.finditer(
        r'^- \*\*(.*?)\*\*[:：]?\s*(.*?)(?=^- \*\*|\Z)', text, re.M | re.S)}


def table(text):
    rows = []
    for line in text.splitlines():
        if not line.startswith('|') or re.match(r'^\|[\s|:-]+$', line):
            continue
        cells = [clean(x) for x in line.strip('|').split('|')]
        if cells[0] in ('Zona/recurso', 'Arista por CID', 'Tipo / motor'):
            continue
        rows.append(cells)
    return rows


def build(live):
    OUT.mkdir(parents=True, exist_ok=True)
    report = {'scope': [1, 10], 'decks': [], 'excluded_routes': [], 'inventory_writes': 0}
    manifest = {'version': VERSION, 'decks': {}}
    extra_file = SOURCE/'ESTRATEGIAS_DECISIONES_Y_PRACTICA_11_DECKS.md'
    extra = extra_file.read_text(encoding='utf-8')
    purpose_index = {m[1]: {'title': clean(m[2]), 'family': clean(m[3])} for m in re.finditer(
        r'^\| `(\d+:A09:[^`]+)` \| (.*?) \| (.*?) \|\s*$', extra, re.M)}
    assert len(purpose_index) == 78
    seen_route_ids = set()
    supplemental = {int(h.split(' ')[0]): body for h,body in subsections(extra,2) if re.match(r'^\d+ ',h)}
    assert set(supplemental) == IDS
    original_images = json.loads((APP/'assets/cards/originals/identities.json').read_text(encoding='utf-8'))['cards']
    files = sorted((SOURCE/'decks').glob('*_CONTENIDO_CURADO.md'))
    assert {int(p.name.split('_')[1]) for p in files} == IDS
    for file in files:
        deck_id = int(file.name.split('_')[1])
        text = file.read_text(encoding='utf-8')
        # The updated source joins the first card heading to its section title.
        # Normalize only the parsing view; preserve the delivered source bytes.
        text = re.sub(r'(?<=[^\n])(#### \d+\.)', r'\n\n\1', text)
        old = json.loads((APP/f'assets/editorial/20261107-study-ten/{deck_id}.json').read_text(encoding='utf-8'))
        deck = next(d for d in live['allDecks'] if int(d['id']) == deck_id)
        assert deck['name'] == old['name'] and old['format'] in deck['format_codes']
        assert f'`deck_id`: **{deck_id}**' in text and f'**{old["format"]}**' in text
        native = [c for c in live['cards'] if int(c['deck_id']) == deck_id]
        sig = lambda rows: sorted((int(c.get('card_id', c.get('id'))), c['section'], int(c['quantity'])) for c in rows)
        assert sig(native) == sig(old['inventory']), f'Inventory mismatch {deck_id}'
        chunks = dict(subsections(text, 2))
        cards_part = next(v for k, v in chunks.items() if k.startswith('CARTAS:'))
        profile = {k: clean(v) for k,v in fields(chunks['PERFIL BREVE — CONOCE TU DECK']).items()}
        engine_part = chunks['SINERGIAS Y MOTORES'] + '\n' + '\n'.join(
            value for title, value in chunks.items() if title.startswith('RESUMEN FUNCIONAL DE SINERGIAS'))
        cards, photo_index, aliases = [], {}, {}

        def photo(relative, name=None):
            source = (file.parent/relative).resolve()
            assert source.is_relative_to((SOURCE/'imagenes').resolve()) and source.is_file()
            if '/skills/' in relative:
                assert source.name.startswith(str(deck_id)+'_'), 'Foreign skill'
                destination = OUT/'skills'/source.name
            else:
                destination = APP/'assets/editorial/20261104-checked-study/images'/source.parent.name/source.name
                # Reuse an existing authentic photograph only after byte comparison.
                if destination.is_file():
                    assert source.read_bytes() == destination.read_bytes(), f'Photograph changed: {source.name}'
                else:
                    destination = OUT/'images'/source.parent.name/source.name
            if not destination.is_file():
                destination.parent.mkdir(parents=True, exist_ok=True)
                shutil.copyfile(source, destination)
            return {'name': name or source.stem, 'image': './'+destination.relative_to(APP).as_posix()}

        allowed_fields = ['Función principal y secundaria', 'Efecto operativo (paráfrasis analítica)',
            'Ventanas de activación identificadas', 'Costes o recursos potenciales', 'Restricciones y requisitos',
            'Riesgo / brick / interrupciones', 'Aplicación concreta dentro de este deck (A06)',
            'Prioridad al ir primero', 'Prioridad al ir segundo', 'Medio/tardío y recuperación',
            'Disponibilidad / papel', 'Efecto y requisito', 'Primero / segundo / tardío', 'Sinergias verificables', 'Riesgo / recurso',
            'Rol funcional', 'Requisito específico', 'Efecto / resultado concreto', 'Ir primero, segundo y juego tardío', 'Riesgos y dependencias']
        allowed_fields = [clean(k) for k in allowed_fields]
        for heading, body in subsections(cards_part, 4):
            if not re.match(r'\d+\.', heading):
                continue
            image = re.search(PHOTOS, body)
            assert image, (deck_id, heading, 'missing card photograph')
            cid = int(Path(image[2]).stem)
            aliases[cid] = re.sub(r'^\d+\.\s*', '', heading).strip()
            bound = next(c for c in old['cards'] if c['cid'] == cid)
            props = fields(body)
            practical_match = re.search(r'\*\*USO PRÁCTICO Y SINERGIA[^\n]*\n(.*)', body, re.S)
            assert practical_match, (deck_id, heading, 'missing practical explanation')
            practical = fields(re.split(r'\*\*CÓMO LLEGAR', practical_match[1], maxsplit=1)[0])
            item = dict(id=bound['id'], cid=cid, name=bound['name'], slots=bound['slots'], **{'image': photo(image[2], bound['name'])['image']})
            for slot in bound['slots']:
                original = original_images.get(f'{deck_id}:{slot["card_id"]}', {})
                original_path = original.get('file', '')
                if original_path and Path(original_path).stem == f'yugiohdb-{cid}' and (APP/original_path).is_file():
                    item['full_image'] = original_path
                    break
            item['fields'] = [{'title': clean(k).replace(' (paráfrasis analítica)', '').replace('Sinergias verificables', 'Sinergias'), 'text': clean(props[k])}
                              for k in allowed_fields if k in props and useful(props[k])]
            item['sources'] = sorted(set(re.findall(r'https://www\.db\.yugioh-card\.com/[^\s*]+', props.get('Referencia oficial y trazabilidad', '')+props.get('Fuente / confianza', '')+props.get('Confianza', ''))))
            item['role'] = clean(props.get('Función principal y secundaria') or props.get('Rol funcional') or props.get('Disponibilidad / papel', ''))
            item['synergy'] = {
                'function': clean(props.get('Estado de sinergia y función', '')).split('. Es una clasificación')[0],
                'resource': clean(props.get('Papel como recurso, descarte o coste', '')),
            }
            label = clean(practical['Label funcional']).split('. Clasificación')[0]
            allowed_labels = ['Sinergia directa', 'Sinergia indirecta', 'Sinergia condicional',
                'Función independiente', 'Sin sinergia demostrada', 'Conflicto funcional']
            item['synergy']['labels'] = [value for value in allowed_labels if value.casefold() in label.casefold()]
            if not item['synergy']['labels']:
                assert any(s['section'] == 'extra' for s in item['slots']), (deck_id, heading, label)
                item['synergy']['labels'] = ['Destino Extra condicionado']
            assert item['synergy']['labels'], (deck_id, heading, label)
            practical_keys = ['Qué hace', 'Cómo aprovecharla en este deck', 'Al jugar primero',
                'Al jugar segundo', 'Uso y consumo de recursos', 'Cuándo tener precaución']
            item['practical'] = [{'title': key, 'text': clean(practical[key])} for key in practical_keys if practical.get(key)]
            assert item['practical'], (deck_id, heading)
            assert item['fields'], (deck_id, heading, 'no usable explanation')
            cards.append(item)
            photo_index[Path(image[2]).name] = {k:item[k] for k in ['name','image','full_image'] if k in item}
        assert len(cards) == len(old['cards']) and len({c['cid'] for c in cards}) == len(cards)
        compatibility = re.search(r'^#### A06\.2[^\n]*\n(.*?)(?=^#### |\Z)',engine_part,re.M|re.S)
        assert compatibility
        found = set()
        for row in table(compatibility[1]):
            cid_match = re.search(r'CID (\d+)',row[0])
            if len(row) not in (6,7) or not cid_match:
                continue
            card = next(c for c in cards if c['cid']==int(cid_match[1]))
            keys = ['function','reason','relation','resource','risk','decision'] if len(row)==7 else ['function','reason','relation','risk','decision']
            card['synergy'].update(dict(zip(keys,row[1:])))
            found.add(card['cid'])
        assert len(found)==len(cards),(deck_id,'missing per-card relationships',len(found))
        # Skill identity/photo remains separate from cards and the draw pool.
        skills = []
        for relative in sorted(set(re.findall(r'!\[[^\]]*\]\((\.\./imagenes/skills/[^)]+)\)', cards_part+engine_part))):
            p = photo(relative, old['skills'][0]['name'])
            skills.append(p)
            photo_index[Path(relative).name] = p
        assert len(skills) == (1 if old['format'] == 'rush_duel' else 0)
        summary = []
        quick = re.search(r'^#### A06\.0[^\n]*\n(.*?)(?=^#### |\Z)', engine_part, re.M | re.S)
        if quick:
            summary = [{'title': k, 'text': clean(v)} for k,v in fields(quick[1]).items() if useful(v)]
        maps = []
        interaction_part = next(v for k,v in chunks.items() if k.startswith('INTERACCIONES'))
        categories = {row[2]: row[1] for row in table(interaction_part) if len(row)==6 and re.fullmatch(rf'{deck_id}:A08:\d+', row[0])}
        for line in engine_part.splitlines():
            if not line.startswith('|') or '![' not in line:
                continue
            cells = line.strip('|').split('|')
            if len(cells) != 4 or not all(useful(s) for s in cells[:3]):
                continue
            linked = [photo_index[Path(p).name] for _,p in re.findall(PHOTOS,cells[3]) if Path(p).name in photo_index]
            if linked and not any('/skills/' in p['image'] for p in linked):
                maps.append(dict(title=clean(cells[0]), category=categories.get(clean(cells[0]), 'INTERACCIÓN'),
                    result=clean(cells[1]), requirements=clean(cells[2]), photos=linked))
        guide_file = SOURCE/'guias_a10'/f'DECK_{deck_id}_GUIAS_A10.md'
        guide = guide_file.read_text(encoding='utf-8')
        routes = []

        def photos(body):
            result = []
            for m in re.finditer(PHOTOS+r'(?:\s*\*\*([^*]+)\*\*)?', body):
                key = Path(m[2]).name
                assert key in photo_index, (deck_id, 'unbound route photograph', key)
                result.append(dict(photo_index[key], label=clean(m[3] or '')))
            return result

        def board(body):
            items = []
            for line in body.splitlines():
                zone = re.search(r'zona declarada:\s*`([^`]+)`', line)
                if zone:
                    for p in photos(line):
                        items.append(dict(p, zone=zone[1]))
            return items

        for heading, body in subsections(guide, 2):
            match = re.search(r'`(\d+:A09:[^`]+)`', heading)
            assert match and int(match[1].split(':')[0]) == deck_id
            route_id = match[1]
            assert route_id in purpose_index
            assert route_id not in seen_route_ids
            seen_route_ids.add(route_id)
            category = re.search(r'^\*\*Categoría:\*\*\s*(.*)', body,re.M)[1].split('·')[0]
            opening = re.search(r'^### Mano y estado inicial\n(.*?)(?=^### |\Z)',body,re.M|re.S)[1]
            final = re.search(r'^### Campo final documentado\n(.*?)(?=^### |\Z)',body,re.M|re.S)[1]
            purpose = re.search(r'\*\*Propósito y resultado documental:\*\*\s*(.*?)(?=^### )',body,re.M|re.S)[1]
            competitive = re.search(r'^\*\*Propósito competitivo principal \(lectura documental\):\*\*\s*(.*)',body,re.M)
            assert competitive, route_id
            competitive = clean(competitive[1].split('. El objetivo')[0])
            steps = []
            for title, stepbody in subsections(body, 4):
                n = re.match(r'Paso (\d+) [—–-] (.*)', title)
                if not n:
                    continue
                action = re.search(r'\*\*Acción concreta:\*\*\s*(.*)', stepbody)[1]
                detail = re.search(r'\*\*Detalle técnico documentado:\*\*\s*(.*)',stepbody)[1]
                illustration = stepbody.split('**Acción concreta:**')[0]
                linked = photos(illustration)
                for p in linked:
                    if p['image'].endswith('/18220.jpg') and 'desterrando Big Bang' in action:
                        p['label'] = 'DESTERRAR · COSTE'
                steps.append(dict(number=int(n[1]), action=clean(action), detail=clean(detail), photos=linked,
                    requirements=[clean(s) for s in re.split(r'(?<=[.;])\s+', detail)
                                  if re.search(r'restring|restric|bloque|como coste|requiere|no puede|no permite|solo puede|solamente', s, re.I)],
                    resources=[dict(zone=row[0], text=row[1]) for row in table(stepbody) if len(row)==2], board=board(stepbody)))
            assert steps and [s['number'] for s in steps] == list(range(steps[0]['number'], steps[0]['number']+len(steps))), (route_id, [s['number'] for s in steps])
            # A11-A13 branches do not enter an import explicitly cut at A10.
            routes.append(dict(id=route_id, name=clean(heading.split('· `')[0]).replace(' · ', ' → '),
                title=purpose_index[route_id]['title'], family=purpose_index[route_id]['family'],
                purpose=competitive,
                category=clean(category), opening=clean(re.sub(PHOTOS+r'(?:\s*\*\*[^*]+\*\*)?', '',opening)),
                hand_photos=[dict(p,label='') for p in photos(opening)],
                result=clean(purpose), steps=steps, board=board(final),
                resources=[dict(zone=row[0], text=row[1]) for row in table(final) if len(row)==2]))
            if old['format'] == 'rush_duel':
                routes[-1]['notice'] = 'Estudio documental con Skill local. Requiere que la liga admita esa Skill y que se cumplan las condiciones indicadas. La secuencia no acredita por sí sola su legalidad ni se ejecuta mediante un motor de reglas.'
        assert len({r['id'] for r in routes}) == len(routes)
        routes.extend(additional(SOURCE, deck_id, photos, clean, subsections, table))
        routes.extend(expanded(SOURCE, deck_id, routes, photos, clean, subsections))
        decisions, practice = [], []
        for heading,body in subsections(supplemental[deck_id],3):
            props = fields(body)
            refs = re.findall(r'`(\d+:A09:[^`]+)`', body)
            refs = [r for r in refs if r in {r['id'] for r in routes}]
            if heading.startswith('Decisión '):
                opts = re.findall(r'\*\*([AB])\)\*\*\s*(.*?)(?=\*\*[AB]\)\*\*|\Z)', props.get('Opciones',''), re.S)
                if not opts:
                    opts = [(m[1],clean(m[2])+': '+clean(m[3])) for m in re.finditer(r'^- \*\*Opción ([A-Z]) [—–-] (.*?)\*\*[:]?\s*(.*)',body,re.M)]
                assert len(opts)>=2
                decisions.append(dict(id=heading.split(' — ')[0].split()[-1],title=heading.split(' — ',1)[1],
                    situation=clean(props.get('Situación',props.get('Mano común',''))),options=[dict(id=k,text=clean(v).strip('; ')) for k,v in opts],
                    answer=clean(props['Decisión']),error=clean(props.get('Error evitado','')),routes=refs))
            elif heading.startswith('Práctica '):
                practice.append(dict(id=heading.split(' — ')[0].split()[-1],title=heading.split(' — ',1)[1],
                    challenge=clean(props['Desafío']),answer=clean(props['Solución revelable']),
                    explanation=clean(props.get('Explicación','')),routes=refs))
        assert len(decisions)==(5 if deck_id==670 else 4) and len(practice)==3
        motor, coverage = enrich(SOURCE, deck_id, cards, routes, decisions, practice, aliases,
                                 photos, clean, subsections, fields, table)
        optimization = recommendations(SOURCE, deck_id, cards, routes, aliases, clean, subsections)
        improvement, scenarios = apply_visual_delivery(SOURCE, deck_id, cards, routes, decisions, photos, clean, optimization)
        data = dict(schema='station-x-curated-v62', deck_id=deck_id, name=old['name'], format=old['format'],
            cards=cards, skills=skills, profile=profile, summary=summary, engine_maps=maps, routes=routes,
            decisions=decisions, practice=practice, motor=motor, optimization=optimization,
            improvement=improvement, opponent_scenarios=scenarios)
        board_report=apply_endboards(data)
        coverage['endboards']=board_report
        added_routes=apply_requested_kashtira(data, SOURCE)
        coverage['endboards'].extend({'id':r['id'],'variants':len(r['endboard']['variants']),
            'source':r['source_extension'],'conditional_cards':0} for r in added_routes)
        apply_overrides(data, SOURCE)
        raw = (json.dumps(data,ensure_ascii=False,indent=2)+'\n').encode()
        (OUT/f'{deck_id}.json').write_bytes(raw)
        manifest['decks'][str(deck_id)] = dict(path='./'+(OUT/f'{deck_id}.json').relative_to(APP).as_posix(),
            cards=len(cards), routes=len(routes), sha256=sha(raw), presentation='curated-v62')
        report['decks'].append(dict(id=deck_id, cards=len(cards), routes=len(routes), steps=sum(len(r['steps']) for r in routes),
            decisions=len(decisions), practice=len(practice),
            navigation=coverage,
            card_source_sha256=sha(file.read_bytes()), guide_source_sha256=sha(guide_file.read_bytes())))
    assert seen_route_ids == set(purpose_index)
    report.update(purpose_titles=len(purpose_index), practical_cards=370,
        documentary_routes=sum(d['navigation']['compact_routes'] for d in report['decks']),
        documentary_historical_steps=sum(d['navigation']['compact_steps'] for d in report['decks']),
        new_routes=[r['id'] for deck_id in sorted(IDS) for r in json.loads((OUT/f'{deck_id}.json').read_text(encoding='utf-8'))['routes'] if r['id'] not in purpose_index],
        documentary_only_routes=[r['id'] for deck_id in sorted(IDS) if deck_id>=704
            for r in json.loads((OUT/f'{deck_id}.json').read_text(encoding='utf-8'))['routes']],
        input_hashes={str(p.relative_to(SOURCE)):sha(p.read_bytes()) for p in sorted(SOURCE.rglob('*')) if p.is_file()},
        endboard_bindings_sha256=sha((APP/'assets/editorial/endboard-bindings.json').read_bytes()),
        synergy_metrics='Documentary label coverage only; not certified synergy, opening probability or win rate',
        context_source_sha256=sha((SOURCE/'CONTEXTO_SINERGIAS_Y_PROPOSITOS.md').read_bytes()),
        supplemental_source_sha256=sha(extra_file.read_bytes()))
    (OUT/'manifest.json').write_bytes((json.dumps(manifest,indent=2)+'\n').encode())
    policy_path = APP/'assets/editorial/strategy-content-state.json'
    policy = json.loads(policy_path.read_text(encoding='utf-8'))
    policy.update(version=VERSION, checked_studies=manifest['decks'])
    policy.pop('active_scope', None)
    policy_path.write_bytes((json.dumps(policy,ensure_ascii=False,indent=2)+'\n').encode())
    return report


if __name__ == '__main__':
    p = argparse.ArgumentParser()
    p.add_argument('--live-snapshot', required=True)
    p.add_argument('--report', required=True)
    args = p.parse_args()
    report = build(json.loads(Path(args.live_snapshot).read_text(encoding='utf-8')))
    Path(args.report).write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8')
    print(json.dumps(report))
