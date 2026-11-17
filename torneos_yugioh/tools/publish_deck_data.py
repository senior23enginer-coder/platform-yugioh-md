"""Publish only the deck Data feature, patching current remote entry points."""
import concurrent.futures
import hashlib
import importlib.util
import io
import json
from pathlib import Path
import re
import requests

APP = Path(__file__).resolve().parents[1]
ROOT = APP.parent
OUT = ROOT / 'outputs/deck-data-20261117'
VERSION = '20261117-data-1'


def main():
    assert json.loads((OUT / 'ui-validation.json').read_text())['ok']
    spec = importlib.util.spec_from_file_location('release', ROOT / 'outputs/deploy-20261102/release.py')
    base = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(base)
    OUT.mkdir(parents=True, exist_ok=True)
    _, before_live = base.public()
    with base.connect() as ftp:
        baseline = {n: base.get(ftp, n) for n in ['index.php', 'assets/app.js', 'service-worker.js']}
        assert all(baseline.values())
        assert base.get(ftp, 'assets/editorial/strategy-content-state.json') == (APP / 'assets/editorial/strategy-content-state.json').read_bytes(), 'Editorial data changed; reconcile first.'
    for name, raw in baseline.items():
        dest = OUT / 'before' / name
        dest.parent.mkdir(parents=True, exist_ok=True)
        dest.write_bytes(raw)
    index = baseline['index.php'].decode('utf8')
    anchor = "require_once dirname(__DIR__) . '/store-lib.php';"
    assert index.count(anchor) == 1
    index = index.replace(anchor, anchor + "\nif ((string)($_GET['api'] ?? '') === 'deck_data') { require_once __DIR__.'/includes/decks/data-http.php'; ygo_data_http(); }")
    assert index.count('>11 secciones</span>') == 1
    index = index.replace('>11 secciones</span>', '>12 secciones</span>')
    match = re.search(r'^[ \t]*<button[^\n]+data-deck-category-open="edit"[^\n]+</button>', index, re.M)
    assert match
    button = '<button type="button" data-deck-data-open><i class="bi bi-arrow-down-up"></i><span><b>Importar / exportar Data</b><small>Listado de cartas y contenido del deck en ZIP</small></span><i class="bi bi-chevron-right"></i></button>'
    index = index[:match.end()] + '\n                        ' + button + index[match.end():]
    script = re.search(r'<script src="\./assets/modules/deck-export/export.js[^\n]+</script>', index)
    assert script
    extra = f'\n<link rel="stylesheet" href="./assets/modules/deck-data/styles.css?v={VERSION}">\n<script src="./assets/modules/deck-data/ui.js?v={VERSION}" defer></script>'
    index = index[:script.end()] + extra + index[script.end():]
    app = baseline['assets/app.js'].decode('utf8')
    anchor = "    window.YgoComboCreator?.mountViewer(document.querySelector('[data-deck-created-combos]'), deck);"
    assert app.count(anchor) == 1
    app = app.replace(anchor, '    window.YgoDeckData?.mount(deck);\n' + anchor)
    index, n = re.subn(r'assets/app\.js\?v=[^"\s]+', 'assets/app.js?v=' + VERSION, index)
    assert n == 1
    sw = baseline['service-worker.js'].decode('utf8')
    m = re.search(r'yugioh-md-match-v(\d+)', sw)
    cache = int(m[1]) + 1
    sw = sw.replace(m[0], f'yugioh-md-match-v{cache}')
    sw, n = re.subn(r'assets/app\.js\?v=[^\x27\s]+', 'assets/app.js?v=' + VERSION, sw)
    assert n == 1
    assert 'const APP_SHELL = [' in sw
    sw = sw.replace('const APP_SHELL = [', f"const APP_SHELL = [\n  './assets/modules/deck-data/ui.js?v={VERSION}',\n  './assets/modules/deck-data/styles.css?v={VERSION}',")
    payload = {p.relative_to(APP).as_posix(): p.read_bytes() for p in (APP / 'assets/editorial/deck-data-sources').glob('*') if p.suffix in ['.json', '.zip']}
    for name in ['includes/decks/data-package.php', 'includes/decks/data-http.php', 'assets/modules/deck-data/ui.js', 'assets/modules/deck-data/styles.css']:
        payload[name] = (APP / name).read_bytes()
    uploaded = []

    def put(ftp, name, raw):
        old = base.get(ftp, name)
        if name in baseline:
            assert old == baseline[name], ('Concurrent change', name)
        if old == raw:
            return
        dest = OUT / 'before' / name
        if old is not None and not dest.exists():
            dest.parent.mkdir(parents=True, exist_ok=True)
            dest.write_bytes(old)
        ftp.storbinary('STOR ' + name + '.release-upload', io.BytesIO(raw))
        assert base.get(ftp, name + '.release-upload') == raw
        ftp.rename(name + '.release-upload', name)
        assert base.get(ftp, name) == raw
        uploaded.append(name)
        print(json.dumps({'uploaded': name, 'bytes': len(raw)}), flush=True)

    with base.connect() as ftp:
        for directory in sorted({str(Path(n).parent).replace('\\', '/') for n in payload}):
            base.ensure_dirs(ftp, directory + '/placeholder')

    def batch(items):
        with base.connect() as ftp:
            for name, raw in items:
                put(ftp, name, raw)

    items = list(payload.items())
    with concurrent.futures.ThreadPoolExecutor(max_workers=3) as pool:
        list(pool.map(batch, [items[i::3] for i in range(3)]))
    with base.connect() as ftp:
        put(ftp, 'assets/app.js', app.encode('utf8'))
        put(ftp, 'index.php', index.encode('utf8'))
        put(ftp, 'service-worker.js', sw.encode('utf8'))
    _, live = base.public()
    signature = lambda rows: sorted((int(c['id']), int(c['deck_id']), c['section'], int(c['quantity']), c['notes']) for c in rows)
    assert signature(live['cards']) == signature(before_live['cards'])
    response = requests.get('https://stationxtulua.com/yugioh/', params={'api': 'deck_data', 'deck_id': 665, 'op': 'status'}, timeout=60)
    response.raise_for_status()
    status = response.json()
    assert status['ok'] and status['cards'] == 38 and status['routes'] == 16
    result = {'ok': True, 'cache': cache, 'files': len(uploaded), 'source_decks': 16, 'remote_bytes_verified': True, 'status_http': response.status_code, 'zip_available': True, 'inventory_unchanged': True, 'database_writes': 0}
    (OUT / 'deployment.json').write_text(json.dumps(result, indent=2), encoding='utf8')
    print(json.dumps(result), flush=True)


if __name__ == '__main__':
    main()
