"""Local, lossless editorial snapshots and incremental review. No network or DB writes."""
import argparse
import copy
import hashlib
import json
from pathlib import Path
import re
import tempfile
import unicodedata

SECTIONS = {'identity', 'plan', 'cards', 'combos', 'matchups', 'interruptions',
            'turn_order', 'construction', 'consistency', 'practice', 'evaluation', 'reference'}

def digest(raw):
    return hashlib.sha256(raw).hexdigest()

def read_json(path):
    return json.loads(Path(path).read_text(encoding='utf-8-sig'))

def dump(path, data):
    Path(path).write_text(json.dumps(data, ensure_ascii=False, indent=2)+'\n', encoding='utf-8')

def norm(text):
    return unicodedata.normalize('NFKD', text).encode('ascii', 'ignore').decode().casefold().strip()

def section_for(title):
    t = norm(title)
    for section, pattern in [
        ('combos', r'combo|rotaci|secuencia|linea de juego'),
        ('matchups', r'matriz|rival|matchup|enfrentamiento'),
        ('consistency', r'matem|probabili|consistencia teor'),
        ('interruptions', r'choke|interrup|ruling|contingencia'),
        ('construction', r'main deck|extra deck|reserva|side|slots|sinerg|optimiz|intercambio'),
        ('practice', r'prueba|entrenamiento|validaci|pendiente'),
        ('evaluation', r'evalua|editorial|indicador'),
        ('plan', r'plan|apertura|motor|recurso|victoria|pilotaje|primero|segundo')]:
        if re.search(pattern, t):
            return section
    return 'reference'

def split_source(raw, catalog, associations):
    text = raw.decode('utf-8-sig')
    lookup = {(d['format'], norm(d['source_name'])): d['deck_id'] for d in catalog}
    pattern = r'^(?:\d{2}\. [^\r\n]+|ANEXO [A-Z] [^\r\n]+|[A-Z]\d+(?:\.\d+)*\.? [^\r\n]+|TCG — \d+ DECKS|RUSH DUEL — \d+ DECKS|GOAT — \d+ DECKS|TAREAS PENDIENTES[^\r\n]*)\r?$'
    matches = list(re.finditer(pattern, text, re.M))
    positions = [(0, 'global:intro', 'Criterios generales', [], 'reference')]
    fmt = None
    in_annex = False
    for m in matches:
        title = m[0].rstrip('\r')
        owners = []
        if title.startswith(('TCG —', 'RUSH DUEL —', 'GOAT —')):
            fmt = {'TCG': 'tcg_advanced', 'RUSH': 'rush_duel', 'GOAT': 'goat'}[title.split()[0]]
            key = 'format:'+fmt
        elif title.startswith('ANEXO '):
            in_annex = True
            topic = title[6]+'-intro'
            key = 'doc:'+topic
            owners = associations.get(topic, [])
        elif re.match(r'^[A-Z]\d', title):
            topic = re.match(r'^([A-Z]\d+(?:\.\d+)*)', title)[1]
            key = 'doc:'+topic
            owners = associations.get(topic, [])
        elif title.startswith('TAREAS PENDIENTES'):
            key = 'global:pending'
        elif not in_annex:
            name = title.split('. ', 1)[1]
            deck_id = lookup.get((fmt, norm(name)))
            if deck_id is None:
                raise ValueError('Unmapped deck: '+title+'; update the catalog explicitly')
            key = 'deck:'+str(deck_id)+':profile'
            owners = [deck_id]
        else:
            continue
        positions.append((m.start(), key, title, owners, section_for(title)))
    ids = [p[1] for p in positions]
    if len(set(ids)) != len(ids):
        raise ValueError('Duplicate stable block IDs')
    blocks = []
    for i, (offset, key, title, owners, section) in enumerate(positions):
        end = positions[i+1][0] if i+1 < len(positions) else len(text)
        blocks.append({'id': key, 'title': title, 'deck_ids': sorted(owners),
                       'section': section, 'text': text[offset:end]})
    return {'schema_version': 'station-x-editorial/1', 'bom': raw.startswith(b'\xef\xbb\xbf'),
            'catalog': catalog, 'blocks': blocks}

def source_bytes(store):
    return (b'\xef\xbb\xbf' if store['bom'] else b'')+''.join(b['text'] for b in store['blocks']).encode('utf-8')

def manifest(store):
    blocks = [{**{k: v for k, v in b.items() if k not in ('text', 'visual')},
               'sha256': digest(b['text'].encode('utf-8'))} for b in store['blocks']]
    result = {'schema_version': store['schema_version'], 'source_sha256': digest(source_bytes(store)),
              'catalog': store['catalog'], 'blocks': blocks}
    result['revision'] = 'sx-'+digest(json.dumps(store, ensure_ascii=False, sort_keys=True).encode())[:20]
    return result

def validate(store, delta, inventory=None):
    if set(delta) != {'schema_version', 'update_id', 'base_revision', 'base_source_sha256', 'changes'}:
        raise ValueError('Unknown/missing package fields; use editorial_update.schema.json')
    base = manifest(store)
    if delta.get('schema_version') != store['schema_version'] or delta.get('base_revision') != base['revision']:
        raise ValueError('Schema/base revision mismatch; request the current manifest')
    if delta.get('base_source_sha256') != base['source_sha256']:
        raise ValueError('Source hash mismatch')
    if not isinstance(delta.get('update_id'), str) or not delta['update_id'].strip():
        raise ValueError('Missing update_id')
    if delta['update_id'] in store.get('update_ids', []):
        raise ValueError('update_id was already staged')
    changes = delta.get('changes')
    if not isinstance(changes, list) or not changes:
        raise ValueError('changes must be a nonempty list')
    current = {b['id']: b for b in store['blocks']}
    catalog = {d['deck_id']: d for d in store['catalog']}
    seen = set()
    for c in changes:
        required = {'op', 'id', 'previous_sha256', 'deck_ids', 'section', 'reason', 'text'}
        if not isinstance(c, dict) or not required <= set(c) or set(c)-required-{'title', 'after_id', 'visual'}:
            raise ValueError('Unknown/missing change fields')
        key = c.get('id')
        if not isinstance(key, str) or not key or key in seen:
            raise ValueError('Missing/duplicate block ID')
        seen.add(key)
        if c.get('op') not in ('add', 'replace'):
            raise ValueError('Only add/replace supported. Omissions never delete content')
        if c.get('section') not in SECTIONS or not isinstance(c.get('text'), str) or not c['text'].strip() or not c.get('reason'):
            raise ValueError('Missing section, complete text or reason: '+key)
        owners = c.get('deck_ids')
        if not isinstance(owners, list) or len(set(owners)) != len(owners) or any(d not in catalog for d in owners):
            raise ValueError('Unknown/duplicate deck IDs: '+key)
        if len({catalog[d]['format'] for d in owners}) > 1:
            raise ValueError('Cross-format block rejected: '+key)
        if 'visual' in c and not isinstance(c['visual'], dict):
            raise ValueError('visual must be an object: '+key)
        if inventory is not None and c['section'] != 'reference':
            lists = {d['deck_id']: d for d in inventory['decks']}
            bindings = c.get('visual', {}).get('list_bindings', {})
            if not owners:
                raise ValueError('List-specific analysis must identify its decks: '+key)
            for owner in owners:
                entry = lists.get(owner)
                if not entry or not entry['eligible_for_list_specific_analysis']:
                    raise ValueError('Load the saved Main before list-specific analysis: '+str(owner))
                if bindings.get(str(owner)) != entry['list_sha256']:
                    raise ValueError('Missing/stale visual.list_bindings for deck '+str(owner))
        if c['op'] == 'replace':
            old = current.get(key)
            if old is None or c.get('previous_sha256') != digest(old['text'].encode()):
                raise ValueError('Previous block hash mismatch: '+key)
            if sorted(owners) != old['deck_ids']:
                raise ValueError('Existing block ownership cannot change silently: '+key)
            if old['text'] == c['text'] and old.get('visual') == c.get('visual') and old['section'] == c['section']:
                raise ValueError('Unchanged content: '+key)
            if old['text'].splitlines()[0] != c['text'].splitlines()[0]:
                raise ValueError('Keep the source heading; use visual.title for display titles: '+key)
        else:
            if key in current or c.get('previous_sha256') is not None:
                raise ValueError('Addition already exists or has a previous hash: '+key)
            if c.get('after_id') not in current:
                raise ValueError('Addition requires an existing after_id: '+key)
            if not c.get('title'):
                raise ValueError('Addition requires title: '+key)
    return base

def stage(store, delta, inventory=None):
    validate(store, delta, inventory)
    updated = copy.deepcopy(store)
    updated.setdefault('update_ids', []).append(delta['update_id'])
    for c in delta['changes']:
        rows = updated['blocks']
        block = {k: copy.deepcopy(c[k]) for k in ['id', 'deck_ids', 'section', 'text']}
        if 'visual' in c:
            block['visual'] = copy.deepcopy(c['visual'])
        if c['op'] == 'replace':
            i = next(i for i,b in enumerate(rows) if b['id'] == c['id'])
            block['title'] = rows[i]['title']
            if 'visual' not in c and 'visual' in rows[i]:
                block['visual'] = rows[i]['visual']
            rows[i] = block
        else:
            block['title'] = c['title']
            i = next(i for i,b in enumerate(rows) if b['id'] == c['after_id'])
            rows.insert(i+1, block)
    return updated

def bundle(target, store, delta=None):
    target = Path(target)
    if target.exists():
        raise ValueError('Output exists; use a new revision directory')
    target.parent.mkdir(parents=True, exist_ok=True)
    with tempfile.TemporaryDirectory(dir=target.parent) as tmp:
        folder = Path(tmp)/'bundle'
        folder.mkdir()
        dump(folder/'blocks.json', store)
        dump(folder/'manifest.json', manifest(store))
        (folder/'station_x_perfiles_67_decks.txt').write_bytes(source_bytes(store))
        if delta:
            dump(folder/'changes.json', delta)
            dump(folder/'integration-plan.json', {
                'status': 'staged_only_not_published', 'database_writes': 0,
                'changed_blocks': [c['id'] for c in delta['changes']],
                'affected_deck_ids': sorted({d for c in delta['changes'] for d in c['deck_ids']}),
                'shared_blocks_require_dependency_review': [c['id'] for c in delta['changes'] if not c['deck_ids']],
                'required': ['Review semantic/rules correctness of changed blocks',
                             'Rebuild source line ranges: headings can shift for unchanged decks',
                             'Regenerate affected profile/visual derivatives; preserve unrelated data',
                             'Use native profile revision/history if DB updates are needed',
                             'Test changed views and publish separately; acknowledge new manifest only after integration']})
        folder.rename(target)

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    sub = parser.add_subparsers(dest='cmd', required=True)
    p = sub.add_parser('compare')
    p.add_argument('--store', required=True); p.add_argument('--source', required=True); p.add_argument('--out', required=True)
    p = sub.add_parser('context')
    p.add_argument('--store', required=True); p.add_argument('--deck-id', type=int, required=True); p.add_argument('--out', required=True)
    p.add_argument('--block-id', action='append', help='Optional: export only these relevant blocks plus general criteria')
    p.add_argument('--inventory', help='Saved-card inventory; defaults to the confirmed context state')
    p.add_argument('--reference-only', action='store_true', help='Export historical/reference content without authorizing list-specific analysis')
    for cmd in ['validate', 'stage']:
        p = sub.add_parser(cmd); p.add_argument('--store', required=True); p.add_argument('--delta', required=True)
        p.add_argument('--inventory', help='Saved-card inventory; defaults to the confirmed context state')
        if cmd == 'stage': p.add_argument('--out', required=True)
    args = parser.parse_args()
    store = read_json(args.store)
    inventory = None
    saved_state = {}
    workspace = Path(__file__).resolve().parents[2]
    if args.cmd != 'compare':
        inventory_path = args.inventory
        state_path = Path(__file__).resolve().parents[1]/'context/editorial-sync-state.json'
        if state_path.exists():
            saved_state = read_json(state_path)
        if not inventory_path and state_path.exists():
            if saved_state.get('workspace_relative_inventory'):
                inventory_path = Path(__file__).resolve().parents[2]/saved_state['workspace_relative_inventory']
        if inventory_path:
            inventory = read_json(inventory_path)
    if args.cmd == 'context':
        base = manifest(store)
        deck = next((d for d in store['catalog'] if d['deck_id'] == args.deck_id), None)
        if deck is None:
            raise ValueError('Unknown deck ID')
        entry = next((d for d in (inventory or {}).get('decks', []) if d['deck_id'] == args.deck_id), None)
        if not args.reference_only and (not entry or not entry['eligible_for_list_specific_analysis']):
            raise ValueError('No saved Main verified. Load the decklist first; --reference-only exports historical material without analysis eligibility')
        if args.deck_id in saved_state.get('superseded_legacy_deck_ids', []):
            current_path = workspace/saved_state['active_record_folder']/('deck-'+str(args.deck_id)+'.json')
            current = read_json(current_path)
            topics = current['topics']
            if args.block_id:
                topics = [t for t in topics if t['key'] in args.block_id]
                if len(topics) != len(set(args.block_id)):
                    raise ValueError('Use current delivery topic keys; legacy doc: blocks were replaced for this deck')
            payload = {'schema_version': 'station-x-current-delivery-context/1',
                       'source_delivery_sha256': current['source_sha256'],
                       'current_record_sha256': digest(current_path.read_bytes()), 'deck': deck,
                       'application_status': 'imported_and_verified', 'reference_only': args.reference_only,
                       'saved_decklist': entry, 'topics': topics,
                       'original_record': current['original_record'] if not args.block_id else None,
                       'instruction': 'This delivery REPLACED the legacy analysis for this deck. Preserve verification labels and exact inventory. Do not emit old doc: patches; replacement integration requires explicit owner authorization and review.'}
            dump(args.out, payload)
            print(json.dumps({'ok': True, 'deck_id': args.deck_id, 'current_delivery': True, 'topics': len(topics)}))
            return
        selected = [b for b in store['blocks'] if args.deck_id in b['deck_ids'] or b['id'] == 'global:intro' or b['id'].startswith('doc:J')]
        if args.block_id:
            available = {b['id'] for b in selected}
            if set(args.block_id)-available:
                raise ValueError('Requested blocks are absent or belong to a different deck')
            selected = [b for b in selected if b['id'] in args.block_id or b['id'] == 'global:intro']
        payload = {'schema_version': store['schema_version'], 'base_revision': base['revision'],
                   'base_source_sha256': base['source_sha256'], 'deck': deck,
                   'reference_only': args.reference_only, 'saved_decklist': entry,
                   'blocks': [{**b, 'sha256': digest(b['text'].encode())} for b in selected],
                   'instruction': 'Return only changed blocks under the editorial contract. Unlisted blocks remain unchanged. This is a subset, never a replacement for the full document.'}
        dump(args.out, payload)
        print(json.dumps({'ok': True, 'deck_id': args.deck_id, 'exported_blocks': len(selected), 'complete_document': False}))
    elif args.cmd == 'compare':
        associations = {b['id'][4:]: b['deck_ids'] for b in store['blocks'] if b['id'].startswith('doc:')}
        incoming = split_source(Path(args.source).read_bytes(), store['catalog'], associations)
        old = {b['id']: b for b in store['blocks']}; new = {b['id']: b for b in incoming['blocks']}
        report = {'base_revision': manifest(store)['revision'], 'changed': [k for k in new.keys() & old.keys() if old[k]['text'] != new[k]['text']],
                  'added': sorted(new.keys()-old.keys()), 'missing_not_deleted': sorted(old.keys()-new.keys()),
                  'unchanged': sum(old[k]['text'] == new[k]['text'] for k in new.keys() & old.keys()),
                  'database_writes': 0, 'status': 'review_only',
                  'note': 'New blocks need ownership/section review. Missing blocks are never deleted. No UI or DB update applied.'}
        report['changed'].sort(); dump(args.out, report); print(json.dumps(report))
    else:
        delta = read_json(args.delta)
        replaced_ids = set(saved_state.get('superseded_legacy_deck_ids', []))
        if any(replaced_ids.intersection(c.get('deck_ids', [])) for c in delta.get('changes', [])):
            raise ValueError('These decks have a newer complete delivery. Export their current context; legacy source blocks are no longer their active content')
        if inventory is None and any(c.get('section') != 'reference' for c in delta.get('changes', [])):
            raise ValueError('Supply a current saved-card inventory before preparing list-specific analysis')
        validate(store, delta, inventory)
        if args.cmd == 'stage': bundle(args.out, stage(store, delta, inventory), delta)
        print(json.dumps({'ok': True, 'status': 'staged' if args.cmd == 'stage' else 'validated',
                          'changed_blocks': len(delta['changes']), 'database_writes': 0}))

if __name__ == '__main__':
    main()
