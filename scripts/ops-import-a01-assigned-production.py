#!/usr/bin/env python3
"""One-off authorized native Station X imports for the 16 assigned A01 decks.
No direct DB/FTP writes; same-session native status, preview, commit, status/export.
Every write is gated by deck identity, physical inventory, and exact A01 profile/guide.
"""
import datetime, hashlib, http.cookiejar, json, pathlib, secrets, sys, tempfile, urllib.error, urllib.parse, urllib.request, zipfile, shutil

ROOT = "https://stationxtulua.com/yugioh/"
FOLDERS = {
  671:"DECK_NUEVO_12_MEMENTOTLAN_DEMONIHERRERO",677:"DECK_NUEVO_13_CRISTRON_HORUS",
  665:"DECK_665_KASHTIRA_PURE",650:"DECK_650_ELFNOTE",653:"DECK_653_DOOMZ",
  656:"DECK_656_TEARLAMENTS",670:"DECK_670_INFERNOBLE",684:"DECK_684_CYBER_DRAGON_PURE",
  687:"DECK_687_VOLCANIC",660:"DECK_NUEVO_14_PURRELY",673:"DECK_NUEVO_15_SALAMANGRANDE",
  699:"DECK_NUEVO_16_GOAT_CONTROL",704:"DECK_704_PYRO",705:"DECK_705_ROYAL",
  706:"DECK_706_HERO",707:"DECK_707_DARK_MAGICIAN"
}
FIELDS = ("Arquetipo","Tipo de estrategia","Estilo breve","Resumen para ficha","Cómo se juega","Qué vigilar")
def require(ok, message):
    if not ok: raise RuntimeError(message)
def request(session,url,body=None,headers=None):
    try:
        with session.open(urllib.request.Request(url,data=body,headers=headers or {"Accept":"application/json"}),timeout=300) as response:
            require(response.status==200,"unexpected HTTP status")
            return response.read()
    except urllib.error.HTTPError as e:
        # No response body, session values, ticket or credentials in public logs.
        raise RuntimeError("HTTP "+str(e.code)) from None
def native_json(session,url,body=None,headers=None):
    result=json.loads(request(session,url,body,headers))
    require(isinstance(result,dict) and result.get("ok") is True,"native API rejected request")
    return result
def zone(z): return "reserve" if z=="tech" else z
def inv(rows): return sorted((int(r["id"]),zone(r["section"]),int(r["quantity"])) for r in rows)
def record_inv(record):
    return sorted((int(s["card_id"]),zone(s["section"]),int(s["quantity"])) for c in record["cards"] for s in c["slots"])
def routes(record): return [r["id"] for r in record["routes"]]
def rules(record): return sorted(str(r["id"]) for r in (record.get("calculator") or {}).get("rules",[]))
def compare(record, expected, inventory, deck_id):
    require(record.get("deck_id")==deck_id and record.get("format")==expected["format"],"deck identity/format drift")
    require(record.get("profile")==expected["profile"] and record.get("player_guide")==expected["player_guide"],"A01 profile/guide drift")
    require(record_inv(record)==inventory,"physical card slots drift")
    require(routes(record)==routes(expected),"route identity drift")
    require(rules(record)==rules(expected),"calculator rule identities drift")
def unpack_export(blob):
    import io
    with zipfile.ZipFile(io.BytesIO(blob)) as z:
        require(z.testzip() is None,"export ZIP corrupt")
        files=[n for n in z.namelist() if n.endswith("/datos/stationx-deck.json") or n=="datos/stationx-deck.json"]
        require(len(files)==1,"export lacks unique native JSON")
        return json.loads(z.read(files[0]))
def run_one(source_root, deck_id, folder, scratch, state):
    api=f"{ROOT}?api=deck_data&deck_id={deck_id}&op="
    source=source_root/folder
    pkg=json.loads((source/"datos/stationx-deck.json").read_text("utf-8"))
    rec=pkg["record"]
    require(pkg.get("schema")=="station-x-deck-package/1" and pkg.get("deck_id")==deck_id and pkg.get("package_root")==folder and pkg.get("inventory_binding")=="deck-slots-v1" and pkg.get("content_mode")=="structured","source package identity mismatch")
    require(rec.get("a01_profile_contract")=="station-x-a01-profile/1" and rec.get("player_guide",{}).get("schema")=="station-x-player-guide/1","uncertified A01")
    require(all(isinstance(rec["profile"].get(k),str) and rec["profile"][k].strip() for k in FIELDS),"missing A01 profile fields")
    require(len(rec["player_guide"]["phases"])==4,"missing game phases")
    strategy=rec["player_guide"].get("strategy",{})
    require(strategy.get("schema")=="station-x-player-strategy/1" and [x.get("id") for x in strategy.get("sections",[])]==["first","second","mid","late","weak_hand","disruption","win_condition","common_errors"],"missing revised eight-lesson plan")
    expected_inv=inv(pkg["inventory"])
    require(record_inv(rec)==expected_inv,"source inventory mismatch")
    counts={z:sum(q for _,s,q in expected_inv if s==z) for z in ("main","extra","side","reserve")}
    require(counts["main"]>0 and (rec["format"] in ("goat","rush","rush_duel") or counts["extra"]>0),"required inventory missing")
    session=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    state["stage"]="live_inventory"
    live=native_json(session,f"{ROOT}?api=deck_builder&deck_id={deck_id}")
    require(int(live["deck"]["id"])==deck_id and inv(live["cards"])==expected_inv,"live inventory differs from GPT source")
    before=native_json(session,api+"status")
    token=before.get("token")
    require(isinstance(token,str) and len(token)>=16,"native CSRF unavailable")
    if before.get("has_data"):
        old=request(session,api+"export")
        old_record=unpack_export(old)["record"]
        require(old_record.get("deck_id")==deck_id,"precommit export identity mismatch")
        (scratch/f"{deck_id}-before.zip").write_bytes(old)
    state["stage"]="build_zip"
    archive=scratch/f"{deck_id}.zip"
    files=sorted(p for p in source.rglob("*") if p.is_file())
    require(files and all(not p.is_symlink() for p in files),"missing/unsafe source files")
    with zipfile.ZipFile(archive,"w",zipfile.ZIP_DEFLATED,compresslevel=7) as z:
        for file in files: z.write(file,file.relative_to(source_root).as_posix())
    require(archive.stat().st_size<48*1024*1024,"ZIP exceeds native upload limit")
    with zipfile.ZipFile(archive) as z:
        require(z.testzip() is None and len(z.namelist())==len(files),"ZIP invalid")
    state["stage"]="preview"
    boundary="StationXAssignedA01"+secrets.token_hex(12)
    head=(f"--{boundary}\r\n"+f'Content-Disposition: form-data; name="package"; filename="{deck_id}-A01.zip"\r\n'+"Content-Type: application/zip\r\n\r\n").encode("ascii")
    payload=head+archive.read_bytes()+f"\r\n--{boundary}--\r\n".encode("ascii")
    common={"X-CSRF-Token":token,"Accept":"application/json"}
    preview=native_json(session,api+"preview",payload,{**common,"Content-Type":"multipart/form-data; boundary="+boundary})
    require(preview.get("inventory_changed") is False,"native preview changes physical inventory")
    compare(preview["candidate"],rec,expected_inv,deck_id)
    require((preview.get("compatibility") or {}).get("player_guide_status")=="PERFIL_PEDAGOGICO_VALIDADO","native guide structure rejected")
    require((preview.get("compatibility") or {}).get("strategy_status")=="ESTRUCTURA_OCHO_LECCIONES","native revised strategy contract unavailable")
    ticket=preview.get("ticket")
    require(isinstance(ticket,str) and len(ticket)>=24,"native ticket missing")
    print(json.dumps({"stage":"PREVIEW_APPROVED","deck_id":deck_id,"counts":counts,"routes":len(routes(rec))},ensure_ascii=False),flush=True)
    state["stage"]="commit"
    state["commit_attempted"]=True
    started=datetime.datetime.now(datetime.timezone.utc)
    native_json(session,api+"commit",urllib.parse.urlencode({"ticket":ticket}).encode("ascii"),{**common,"Content-Type":"application/x-www-form-urlencoded"})
    state["stage"]="verify"
    after=native_json(session,api+"status")
    require(after.get("has_data") and after.get("cards")==len(rec["cards"]) and after.get("routes")==len(routes(rec)),"postcommit status mismatch")
    updated=after.get("updated_at")
    require(isinstance(updated,str) and updated!=before.get("updated_at"),"fresh import timestamp missing")
    stamp=datetime.datetime.fromisoformat(updated.replace("Z","+00:00"))
    require(stamp.tzinfo is not None and stamp>=started-datetime.timedelta(minutes=2),"timestamp is not fresh")
    exported=unpack_export(request(session,api+"export"))
    compare(exported["record"],rec,expected_inv,deck_id)
    live_after=native_json(session,f"{ROOT}?api=deck_builder&deck_id={deck_id}")
    require(inv(live_after["cards"])==expected_inv,"postcommit physical inventory drift")
    return {"deck_id":deck_id,"status":"PRODUCTION_IMPORT_VERIFIED","updated_at":updated,"counts":counts,"cards":len(rec["cards"]),"routes":len(routes(rec)),"calculator_rules":len(rules(rec)),"profile_fields":6,"phases":4,"lessons":8,"semantic_certification":False,"source_zip_sha256":hashlib.sha256(archive.read_bytes()).hexdigest()}
def main():
    source_root=pathlib.Path(sys.argv[1]).resolve()/"01_INFORMACION_PARA_IMPORTAR"/"decks"
    results=[]
    with tempfile.TemporaryDirectory(prefix="stationx-assigned-a01-") as tmp:
        for deck_id,folder in FOLDERS.items():
            state={"stage":"source","commit_attempted":False}
            try: result=run_one(source_root,deck_id,folder,pathlib.Path(tmp),state)
            except Exception as e:
                result={"deck_id":deck_id,"status":"COMMIT_UNVERIFIED" if state["commit_attempted"] else "PRECOMMIT_REJECTED","stage":state["stage"],"error":str(e)[:300]}
            results.append(result)
            print(json.dumps(result,ensure_ascii=False),flush=True)
            if result["status"]=="COMMIT_UNVERIFIED": break
    done={r["deck_id"] for r in results}
    results.extend({"deck_id":i,"status":"NOT_ATTEMPTED"} for i in FOLDERS if i not in done)
    report={"scope":"authorized 16 A01 decks","verified":sum(r["status"]=="PRODUCTION_IMPORT_VERIFIED" for r in results),"decks":results}
    pathlib.Path("outputs").mkdir(exist_ok=True)
    pathlib.Path("outputs/a01-production-import.json").write_text(json.dumps(report,ensure_ascii=False,indent=2)+"\n")
    print(json.dumps(report,ensure_ascii=False),flush=True)
    return 0 if report["verified"]==16 else 1
if __name__=="__main__": sys.exit(main())
