"""Package exact per-deck author folders for the web Data export (no private QA)."""
import hashlib
import json
import zipfile
from pathlib import Path

APP = Path(__file__).resolve().parents[1]
ROOT = APP.parent
SOURCE = APP / 'context/gpt/01_INFORMACION_PARA_IMPORTAR/decks'
TARGET = APP / 'assets/editorial/deck-data-sources'


def build():
    state = json.loads((APP / 'assets/editorial/strategy-content-state.json').read_text(encoding='utf8'))
    TARGET.mkdir(parents=True, exist_ok=True)
    index = {}
    for deck_id, entry in state['checked_studies'].items():
        record = json.loads((APP / entry['path'].removeprefix('./')).read_text(encoding='utf8'))
        path = (ROOT / record.get('folder_delivery', {}).get('source_file', '')).resolve()
        if not path.is_file() or not path.is_relative_to(SOURCE.resolve()):
            continue
        folder = path.parent
        # Modern deliveries each have their own folder. Never export neighboring decks.
        candidates = folder.rglob('*') if folder != SOURCE else [path]
        files = [f for f in candidates if f.is_file() and f.suffix.lower() in {'.md', '.txt', '.json', '.html', '.svg', '.png', '.jpg', '.jpeg', '.webp', '.gif'}]
        dest = TARGET / f'{deck_id}.zip'
        with zipfile.ZipFile(dest, 'w', zipfile.ZIP_DEFLATED) as z:
            for f in sorted(files):
                if any(part.startswith('.') for part in f.relative_to(folder).parts):
                    continue
                z.write(f, (Path(folder.name) / f.relative_to(folder)).as_posix())
        index[deck_id] = {'zip': './assets/editorial/deck-data-sources/' + dest.name, 'source_document': path.name, 'sha256': hashlib.sha256(dest.read_bytes()).hexdigest()}
    (TARGET / 'index.json').write_text(json.dumps(index, ensure_ascii=False, indent=2) + '\n', encoding='utf8')
    print(json.dumps({'decks': len(index), 'bytes': sum((TARGET / f'{id}.zip').stat().st_size for id in index)}))


if __name__ == '__main__':
    build()
