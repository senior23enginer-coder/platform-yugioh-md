"""Publish a validated editorial folder; retain live entry points and inventories."""
import concurrent.futures
import hashlib
import importlib.util
import io
import json
import os
from pathlib import Path
import re
import requests

APP=Path(__file__).resolve().parents[1];ROOT=APP.parent
OUT=ROOT/os.environ['YGO_IMPORT_OUTPUT'];VERSION=os.environ['YGO_IMPORT_VERSION']
TARGET=APP/'assets/editorial'/VERSION
REGISTRY='assets/editorial/strategy-content-state.json'


def read(path):return json.loads(path.read_text(encoding='utf8'))


def main():
    report=read(OUT/'report.json');validation=read(OUT/'native-validation.json');ui=read(OUT/'ui-validation.json')
    assert report['version']==VERSION and validation['decks']==len(report['decks'])==ui['decks'] and ui['ok']
    assert validation['routes']==sum(d['routes'] for d in report['decks'])
    spec=importlib.util.spec_from_file_location('release',ROOT/'outputs/deploy-20261102/release.py')
    base=importlib.util.module_from_spec(spec);spec.loader.exec_module(base)
    files={p.resolve() for p in TARGET.glob('*.json')}|{p.resolve() for p in TARGET.glob('*.html')}
    for p in list(files):
        text=p.read_text(encoding='utf8')
        urls=re.findall(r'(?:src|href)="([^"]+)"',text) if p.suffix=='.html' else re.findall(r'\./assets/editorial/'+re.escape(VERSION)+r'/[^"\s]+',text)
        for url in urls:
            dest=((p.parent/url) if p.suffix=='.html' else APP/url.removeprefix('./')).resolve()
            if dest.is_file() and dest.is_relative_to(TARGET.resolve()):files.add(dest)
    payload={p.relative_to(APP.resolve()).as_posix():p.read_bytes() for p in files}
    # Refresh the downloadable per-deck originals alongside their editorial data.
    if (OUT/'extra-assets.json').exists():
        for name in read(OUT/'extra-assets.json'):
            assert name.startswith('assets/editorial/deck-data-sources/') and Path(name).suffix in ('.json','.zip') and '..' not in Path(name).parts
            payload[name]=(APP/name).read_bytes()
    before={name:(OUT/'before'/name).read_bytes() for name in (REGISTRY,'index.php','service-worker.js')}
    patches=read(OUT/'module-patches.json') if (OUT/'module-patches.json').exists() else []
    for name in patches:
        assert name.startswith('assets/modules/') and name.endswith('.js') and '..' not in Path(name).parts
        before[name]=(OUT/'before'/name).read_bytes()
        payload[name]=(OUT/'patches'/name).read_bytes()
    index=before['index.php'].decode('utf8')
    sw=before['service-worker.js'].decode('utf8');match=re.search(r"yugioh-md-match-v(\d+)",sw);assert match
    cache=int(match[1])+1
    sw=sw.replace(match[0],f'yugioh-md-match-v{cache}')
    for name in patches:
        pattern=re.escape(name)+r'\?v=[^\s\x27\x22]+'
        index,n=re.subn(pattern,name+'?v='+VERSION,index);assert n==1
        sw,n=re.subn(pattern,name+'?v='+VERSION,sw);assert n==1
    # Source revision changes are checked immediately before publication.
    current=requests.get('https://stationxtulua.com/yugioh/?api=combo_creator&source_prompts=1',timeout=60);current.raise_for_status()
    assert current.json()['records']==read(OUT/'editor-feed.json')['records'],'Editor changed during import; rebuild with its latest revisions.'
    with base.connect() as ftp:
        for name,raw in before.items():assert base.get(ftp,name)==raw,('Remote changed during preparation',name)
        for directory in sorted({str(Path(n).parent).replace('\\','/') for n in payload}):base.ensure_dirs(ftp,directory+'/placeholder')
    uploaded=[]
    def put(ftp,name,raw):
        previous=base.get(ftp,name)
        if name in before:assert previous==before[name],('Concurrent change',name)
        if previous==raw:return
        ftp.storbinary('STOR '+name+'.release-upload',io.BytesIO(raw));assert base.get(ftp,name+'.release-upload')==raw
        ftp.rename(name+'.release-upload',name);assert base.get(ftp,name)==raw;uploaded.append(name)
    def batch(items):
        with base.connect() as ftp:
            for name,raw in items:put(ftp,name,raw)
    items=list(payload.items())
    with concurrent.futures.ThreadPoolExecutor(max_workers=3) as pool:list(pool.map(batch,[items[i::3] for i in range(3)]))
    with base.connect() as ftp:
        put(ftp,REGISTRY,(APP/REGISTRY).read_bytes())
        if patches:put(ftp,'index.php',index.encode('utf8'))
        put(ftp,'service-worker.js',sw.encode('utf8'))
    _,live=base.public();original=read(OUT/'live.json')
    signature=lambda rows:sorted((int(c['id']),int(c['deck_id']),c['section'],int(c['quantity']),c['notes']) for c in rows)
    assert signature(live['cards'])==signature(original['cards'])
    result={'ok':True,'version':VERSION,'cache':cache,'decks':len(report['decks']),'cards':sum(d['cards'] for d in report['decks']),
        'routes':validation['routes'],'files':len(uploaded),'database_writes':0,'inventories_unchanged':True,'sporting_writes':0,'remote_bytes_verified':True,'pending_destinations':report['skipped']}
    (OUT/'deployment.json').write_text(json.dumps(result,indent=2),encoding='utf8');print(json.dumps(result))


if __name__=='__main__':main()
