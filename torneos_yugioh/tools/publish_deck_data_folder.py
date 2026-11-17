"""Bounded update of the self-contained deck-folder web import/export."""
import hashlib
import importlib.util
import io
import json
from pathlib import Path
import re
import zipfile
import requests

APP=Path(__file__).resolve().parents[1];ROOT=APP.parent;OUT=ROOT/'outputs/deck-data-folder-20261117'

def main():
    OUT.mkdir(parents=True,exist_ok=True)
    spec=importlib.util.spec_from_file_location('release',ROOT/'outputs/deploy-20261102/release.py');base=importlib.util.module_from_spec(spec);spec.loader.exec_module(base)
    _,before_live=base.public()
    names=['index.php','service-worker.js','includes/decks/data-http.php','includes/decks/data-folder.php','assets/modules/deck-data/ui.js']
    with base.connect() as ftp:before={n:base.get(ftp,n) for n in names}
    for n,b in before.items():
        if b is not None:
            p=OUT/'before'/n;p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(b)
    payload={n:(APP/n).read_bytes() for n in names if n not in ['index.php','service-worker.js']}
    index=before['index.php'].decode('utf8');sw=before['service-worker.js'].decode('utf8')
    token='assets/modules/deck-data/ui.js?v=20261117-data-folder-2'
    pattern=r'assets/modules/deck-data/ui\.js\?v=[^\s\x27\x22]+'
    index,n=re.subn(pattern,token,index);assert n==1
    sw,n=re.subn(pattern,token,sw);assert n==1
    match=re.search(r'yugioh-md-match-v(\d+)',sw);cache=int(match[1])+1;sw=sw.replace(match[0],f'yugioh-md-match-v{cache}')
    payload['index.php']=index.encode('utf8');payload['service-worker.js']=sw.encode('utf8')
    uploaded=[]
    with base.connect() as ftp:
        # New helper before its first consumer; entry points last.
        for name in ['includes/decks/data-folder.php','includes/decks/data-http.php','assets/modules/deck-data/ui.js','index.php','service-worker.js']:
            raw=payload[name];assert base.get(ftp,name)==before[name],('Concurrent change',name)
            if before[name]==raw:continue
            base.ensure_dirs(ftp,name);ftp.storbinary('STOR '+name+'.release-upload',io.BytesIO(raw));assert base.get(ftp,name+'.release-upload')==raw
            ftp.rename(name+'.release-upload',name);assert base.get(ftp,name)==raw;uploaded.append(name)
    _,live=base.public()
    sig=lambda rows:sorted((int(c['id']),int(c['deck_id']),c['section'],int(c['quantity']),c['notes']) for c in rows)
    assert sig(live['cards'])==sig(before_live['cards'])
    response=requests.get('https://stationxtulua.com/yugioh/',params={'api':'deck_data','deck_id':665,'op':'export'},timeout=90);response.raise_for_status()
    assert response.headers['Content-Type'].startswith('application/zip')
    with zipfile.ZipFile(io.BytesIO(response.content)) as z:
        assert z.testzip() is None
        assert all(n.startswith('DECK_665_KASHTIRA_PURE/') for n in z.namelist())
        record=json.loads(z.read('DECK_665_KASHTIRA_PURE/datos/stationx-deck.json'))
        assert record['deck_id']==665 and record['structure_reference']['deck_id']==665 and record['content_mode']=='auto'
        names=z.namelist()
    result={'ok':True,'cache':cache,'files':uploaded,'live_export_folder':'DECK_665_KASHTIRA_PURE','live_export_files':len(names),'live_export_bytes':len(response.content),'automatic_import_local_cycle':'passed','decks_roundtripped_locally':16,'inventories_unchanged':True,'database_writes':0,'remote_bytes_verified':True}
    (OUT/'deployment.json').write_text(json.dumps(result,indent=2),encoding='utf8');print(json.dumps(result))

if __name__=='__main__':main()
