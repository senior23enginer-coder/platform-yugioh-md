"""Synchronize native editor revisions into their local documentary sources.

Only the existing combo API is read. No inventory, sporting result or remote
record is changed. Revision files are immutable; originals remain historical.
"""
import argparse
import json
import os
from pathlib import Path
import time
import urllib.error
import urllib.request

APP = Path(__file__).resolve().parents[1]
SOURCE = APP / 'context/gpt/01_INFORMACION_A_IMPORTAR'
URL = 'https://stationxtulua.com/yugioh/?api=combo_creator&source_prompts=1'


def stable_id(deck, route):
    a, b = 2166136261, 5381
    for ch in f'{deck}:{route}':
        code = int.from_bytes(ch.encode('utf-16-le')[:2], 'little')
        a = ((a ^ code) * 16777619) & 0xffffffff
        b = ((b * 33) ^ code) & 0xffffffff
    return f'documented-{deck}-{a:08x}{b:08x}'


def write_json(file, value):
    atomic(file, json.dumps(value, ensure_ascii=False, indent=2) + '\n')


def atomic(file, text):
    file.parent.mkdir(parents=True, exist_ok=True)
    temp = file.with_suffix(file.suffix + '.tmp')
    temp.write_text(text, encoding='utf-8', newline='\n')
    os.replace(temp, file)


def apply_feed(feed, source=SOURCE):
    if feed.get('schema') != 'station-x-combo-source/1' or feed.get('ok') is not True:
        raise ValueError('Respuesta de sincronización no válida')
    catalog_file = source / 'GUIAS_Y_DECISIONES_V16.json'
    if not catalog_file.exists():
        catalog_file = source / 'COMBOS_EXISTENTES_INDICE_EDITOR.json'
    catalog = json.loads(catalog_file.read_text(encoding='utf-8-sig'))
    routes = {r['id']: r for r in catalog['rutas']}
    catalogs = {catalog_file: catalog}
    owners = {key: catalog_file for key in routes}
    for name in ('LOTE_JUGABLE_11_DECKS_A01_A10_V11.json', 'COMBOS_COTEJADOS_A09_A10_V12.json',
                 'DELTA_ELFNOTE_LOTE_01.json', 'DELTA_ELFNOTE_APERTURA_SP_ITERACION_04.json'):
        file = source / name
        if not file.exists():
            continue
        supplemental = json.loads(file.read_text(encoding='utf-8'))
        catalogs[file] = supplemental
        for route in supplemental.get('rutas', supplemental.get('routes_added', [])):
            if route['id'] in routes:
                raise ValueError('ID repetido entre fuentes de rutas')
            routes[route['id']] = route
            owners[route['id']] = file
    target = source / 'revisiones_editor'
    index_file = target / 'index.json'
    index = json.loads(index_file.read_text(encoding='utf-8')) if index_file.exists() else {'schema': 'station-x-editor-overrides/1', 'routes': {}}
    changed = []
    changed_catalogs = set()
    # Validate the entire response before writing any source.
    for row in feed['records']:
        route = routes.get(row['route_id'])
        doc = row['document']
        if not route or int(route.get('deckId', route.get('deck_id', catalogs[owners[row['route_id']]].get('deck_id', 0)))) != row['deck_id'] or row['id'] != stable_id(row['deck_id'], row['route_id']):
            raise ValueError('Ruta o deck ajeno a la fuente local')
        if doc['id'] != row['id'] or doc['deck_id'] != row['deck_id'] or doc.get('source_route_id') != row['route_id'] or not isinstance(row['revision'], int) or row['revision'] < 1:
            raise ValueError('Identidad de revisión no válida')
    for row in feed['records']:
        key = row['route_id']
        current = index['routes'].get(key, {})
        if current.get('revision', 0) > row['revision']:
            raise ValueError('El servidor ofrece una revisión anterior a la fuente local')
        stem = Path(str(row['deck_id'])) / row['id'] / f"revision-{row['revision']:06d}"
        relative = stem.with_suffix('.json').as_posix()
        revision_file = target / relative
        # Feed formatting/ETag may change; content identity is the native document.
        payload = {k: row[k] for k in ('deck_id', 'id', 'route_id', 'revision', 'updated_at', 'document', 'cards_snapshot', 'markdown')}
        if revision_file.exists():
            old = json.loads(revision_file.read_text(encoding='utf-8'))
            if old['document'] != payload['document'] or old['cards_snapshot'] != payload['cards_snapshot']:
                raise ValueError('Conflicto: una revisión existente tiene otro contenido')
        else:
            write_json(revision_file, payload)
        if not (target / stem.with_suffix('.md')).exists():
            atomic(target / stem.with_suffix('.md'), row['markdown'])
        pointer = {'deck_id': row['deck_id'], 'id': row['id'], 'revision': row['revision'], 'path': relative}
        if current != pointer:
            index['routes'][key] = pointer
            changed.append(key)
        # Keep the complete original sequence and inventory, and point readers
        # explicitly to the authoritative edited graph instead of flattening it.
        marker = {'revision': row['revision'], 'documento': 'revisiones_editor/' + relative}
        if routes[key].get('revision_editor_vigente') != marker:
            changed_catalogs.add(owners[key])
        routes[key]['revision_editor_vigente'] = marker
    if changed or changed_catalogs or not index_file.exists():
        write_json(index_file, index)
        for file in changed_catalogs:
            write_json(file, catalogs[file])
        atomic(target / 'LEEME.md', '# Revisiones vigentes del editor\n\nEstas revisiones tienen prioridad sobre la descripción histórica de su misma ruta. '
               'Lee `index.json` y el documento enlazado antes de reimportar o modificar un combo. '
               'El grafo nativo conserva todas las cartas, pasos, conexiones y explicaciones guardadas. '
               'Los originales y sus inventarios se conservan sin reemplazo.\n')
    return changed


def apply_overrides(data, source=SOURCE):
    file = source / 'revisiones_editor/index.json'
    if not file.exists():
        return
    entries = json.loads(file.read_text(encoding='utf-8'))['routes']
    root = (source / 'revisiones_editor').resolve()
    for route in data['routes']:
        item = entries.get(route['id'])
        if not item:
            continue
        file = (root / item['path']).resolve()
        if not file.is_relative_to(root):
            raise ValueError('Ruta de revisión fuera de la fuente')
        row = json.loads(file.read_text(encoding='utf-8'))
        doc = row['document']
        if row['deck_id'] != data['deck_id'] or row['route_id'] != route['id'] or doc['id'] != stable_id(data['deck_id'], route['id']) or doc.get('source_route_id') != route['id']:
            raise ValueError('Revisión de otro deck o ruta')
        route.update(native_editor_document=doc, native_editor_cards=row['cards_snapshot'], native_editor_revision=row['revision'], title=doc['title'], purpose=doc['objective'])


def poll(url=URL, source=SOURCE, etag=None):
    request = urllib.request.Request(url, headers={'If-None-Match': etag or '', 'User-Agent': 'Station-X-Combo-Source-Sync/1'})
    try:
        with urllib.request.urlopen(request, timeout=25) as response:
            feed = json.load(response)
            return response.headers.get('ETag'), apply_feed(feed, source)
    except urllib.error.HTTPError as error:
        if error.code == 304:
            return etag, []
        raise


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--watch', action='store_true')
    parser.add_argument('--interval', type=int, default=10)
    args = parser.parse_args()
    out = APP.parent / 'outputs/combo-source-sync'
    out.mkdir(parents=True, exist_ok=True)
    # Windows byte-range lock is released by the OS even after a crash.
    import msvcrt
    lock = (out / 'watch.lock').open('a+b')
    lock.seek(0); lock.write(b'0'); lock.flush(); lock.seek(0)
    try:
        msvcrt.locking(lock.fileno(), msvcrt.LK_NBLCK, 1)
    except OSError:
        return
    etag = None
    while True:
        try:
            etag, changed = poll(etag=etag)
            status = {'ok': True, 'checked_at': time.strftime('%Y-%m-%dT%H:%M:%S'), 'updated_routes': changed, 'pid': os.getpid()}
        except Exception as error:
            status = {'ok': False, 'checked_at': time.strftime('%Y-%m-%dT%H:%M:%S'), 'error': str(error), 'pid': os.getpid()}
        write_json(out / 'status.json', status)
        if not args.watch:
            print(json.dumps(status, ensure_ascii=False))
            return
        time.sleep(max(5, args.interval))


if __name__ == '__main__':
    main()
