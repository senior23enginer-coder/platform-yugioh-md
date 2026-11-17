"""Cache original Konami card faces by documented CID, never by similar names."""
from pathlib import Path
import concurrent.futures,hashlib,html,io,json,re,time
from urllib.parse import urlparse,parse_qs
import requests,unicodedata
from PIL import Image
APP=Path(__file__).resolve().parents[1]
DEST=APP/'assets/cards/originals';DEST.mkdir(parents=True,exist_ok=True)
# The committed index is the reviewed identity/provenance contract. Refreshing
# images must not depend on a different, unfinished editorial delivery.
previous=json.loads((DEST/'index.json').read_text(encoding='utf-8'))
tasks={}
for key,source in previous['sources'].items():
    namespace,cid=key.rsplit('-',1)
    assert namespace in {'rushdb','yugiohdb'}
    url=source['source'];parsed=urlparse(url)
    assert parsed.hostname=='www.db.yugioh-card.com' and int(parse_qs(parsed.query)['cid'][0])==int(cid)
    tasks[key]={'cid':int(cid),'namespace':namespace,'source':url,'image_id':int(source.get('native_image_id',0))}
bindings={key:Path(value).stem for key,value in previous['cards'].items()}

def worker(entry):
    key,task=entry;path=DEST/(key+'.jpg')
    if path.exists():
        Image.open(path).verify();return key,{**previous['sources'][key],'sha256':hashlib.sha256(path.read_bytes()).hexdigest()}
    session=requests.Session();last=None
    if '403' in previous.get('sources',{}).get(key,{}).get('error','') and task['image_id']:
        url=f"https://stationxtulua.com/yugioh/?api=deck_builder&op=image&id={task['image_id']}&size=full"
        response=session.get(url,timeout=60);response.raise_for_status();image=Image.open(io.BytesIO(response.content));assert image.width>=150
        path.write_bytes(response.content)
        return key,{'file':f'./assets/cards/originals/{key}.jpg','source':task['source'],'image_source':url,'native_image_id':task['image_id'],'sha256':hashlib.sha256(response.content).hexdigest()}
    for attempt in range(3):
        try:
            page=session.get(task['source'],timeout=45);page.raise_for_status()
            match=re.search(r"<meta\s+property=['\"]og:image['\"]\s+content=['\"]([^'\"]+)",page.text)
            assert match,'No official card image metadata'
            url=html.unescape(match[1]);p=urlparse(url);assert p.hostname=='www.db.yugioh-card.com'and p.path==f"/{task['namespace']}/get_image.action"and int(parse_qs(p.query)['cid'][0])==task['cid']
            response=session.get(url,headers={'Referer':page.url},timeout=45);response.raise_for_status()
            image=Image.open(io.BytesIO(response.content));assert image.width>=150 and image.height>=200
            if image.format=='JPEG':body=response.content
            else:
                b=io.BytesIO();image.convert('RGB').save(b,format='JPEG',quality=95);body=b.getvalue()
            path.write_bytes(body)
            return key,{'file':f'./assets/cards/originals/{key}.jpg','source':task['source'],'sha256':hashlib.sha256(body).hexdigest()}
        except Exception as e:last=str(e);time.sleep(1+attempt)
    return key,{'error':last,'source':task['source']}
results={}
with concurrent.futures.ThreadPoolExecutor(max_workers=3)as pool:
    for key,result in pool.map(worker,sorted(tasks.items())):
        results[key]=result
        if len(results)%25==0:print(json.dumps({'images_processed':len(results),'total':len(tasks)}),flush=True)
index={'version':1,'cards':{key:results[value]['file']for key,value in bindings.items()if'file'in results[value]},'sources':results}
(APP/'assets/cards/originals/index.json').write_text(json.dumps(index,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
print(json.dumps({'ok':all('file'in x for x in results.values()),'unique_images':len(results),'bound_card_rows':len(index['cards']),'errors':[k for k,v in results.items()if'error'in v]}),flush=True)
