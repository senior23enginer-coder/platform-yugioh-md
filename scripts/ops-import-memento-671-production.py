#!/usr/bin/env python3
"""One-time, explicitly authorized Station X native API import for deck 671 only.

No FTP, direct database writes, filesystem edits to production, or secret logging.
Uses a PHP session + same-session CSRF obtained from native status API.
"""
import datetime
import hashlib
import http.cookiejar
import json
import pathlib
import secrets
import shutil
import sys
import tempfile
import urllib.error
import urllib.parse
import urllib.request
import zipfile


DECK_ID = 671
SOURCE_DIR_NAME = "DECK_NUEVO_12_MEMENTOTLAN_DEMONIHERRERO"
API = "https://stationxtulua.com/yugioh/?api=deck_data&deck_id=671&op="
FIELDS = ("Arquetipo", "Tipo de estrategia", "Estilo breve",
          "Resumen para ficha", "Cómo se juega", "Qué vigilar")


def require(condition, detail):
    if not condition:
        raise RuntimeError("IMPORT_ABORTED: " + detail)


def load_json(body, stage):
    try:
        data = json.loads(body)
    except (json.JSONDecodeError, UnicodeDecodeError) as exc:
        raise RuntimeError(f"IMPORT_ABORTED: {stage}: invalid JSON") from exc
    require(isinstance(data, dict) and data.get("ok") is True, stage + ": API not ok")
    return data


def read_response(opener, op, method="GET", body=None, headers=None, timeout=180):
    req = urllib.request.Request(
        API + op, data=body, headers=headers or {"Accept": "application/json"},
        method=method,
    )
    try:
        with opener.open(req, timeout=timeout) as response:
            require(response.status == 200, op + ": unexpected HTTP response")
            return response.read()
    except urllib.error.HTTPError as exc:
        message = ""
        try:
            error_data = json.loads(exc.read(6000))
            message = str(error_data.get("message", ""))[:300]
        except (ValueError, UnicodeDecodeError):
            pass
        raise RuntimeError(f"IMPORT_ABORTED: {op} HTTP {exc.code}: {message}") from exc


def zones_for(cards):
    counts = {"main": 0, "extra": 0, "side": 0, "reserve": 0}
    for card in cards:
        for slot in card["slots"]:
            require(slot["section"] in counts, "unexpected physical zone")
            counts[slot["section"]] += int(slot["quantity"])
    return counts


def main(repo_root):
    source_root = pathlib.Path(repo_root).resolve() / "01_INFORMACION_PARA_IMPORTAR" / "decks"
    source = source_root / SOURCE_DIR_NAME
    json_file = source / "datos" / "stationx-deck.json"
    require(source.is_dir() and json_file.is_file(), "certified GPT source is missing")
    package = json.loads(json_file.read_text("utf-8"))
    record = package["record"]
    require(
        package["schema"] == "station-x-deck-package/1"
        and package["deck_id"] == DECK_ID
        and record["deck_id"] == DECK_ID
        and record["schema"] == "station-x-curated-v62"
        and package["package_root"] == SOURCE_DIR_NAME
        and package["inventory_binding"] == "deck-slots-v1"
        and package["content_mode"] == "structured",
        "source package identity/schema mismatch",
    )
    require(record["a01_profile_contract"] == "station-x-a01-profile/1", "A01 profile uncertified")
    require(record["player_guide"]["schema"] == "station-x-player-guide/1", "player guide not certified")
    require(len(record["cards"]) == 39 and len(record["routes"]) == 12, "card or route mismatch")
    require(len(record["player_guide"]["featured_cards"]) == 4, "featured card count mismatch")
    require(len(record["player_guide"]["phases"]) == 4, "plan phases count mismatch")
    require(all(isinstance(record["profile"].get(k), str) and record["profile"][k].strip()
                for k in FIELDS), "missing A01 fields")
    require(zones_for(record["cards"]) == {
        "main": 41, "extra": 15, "side": 14, "reserve": 0,
    }, "physical inventory mismatch")

    files = sorted(p for p in source.rglob("*") if p.is_file())
    require(len(files) == 43, "expected exactly 43 certified files")
    session = urllib.request.build_opener(
        urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar())
    )
    # Native preflight must be read from the real server, not a fixture.
    before = load_json(read_response(session, "status"), "status-before")
    require(before.get("has_data") is True and before.get("cards") == 39
            and before.get("routes") == 12, "unexpected live Memento snapshot")
    token = before.get("token")
    require(isinstance(token, str) and len(token) >= 16, "no valid CSRF")
    print(json.dumps({
        "stage": "STATUS_BEFORE", "deck_id": DECK_ID, "cards": 39,
        "routes": 12, "previous_updated_at": before.get("updated_at"),
    }, ensure_ascii=False), flush=True)

    with tempfile.TemporaryDirectory(prefix="stationx-native-api-671-") as tmp:
        base = pathlib.Path(tmp)
        archive = base / "Memento-671-A01T5.zip"
        with zipfile.ZipFile(archive, "w", zipfile.ZIP_DEFLATED, compresslevel=7) as z:
            for file in files:
                z.write(file, file.relative_to(source_root).as_posix())
        require(archive.stat().st_size < 48 * 1024 * 1024, "ZIP exceeds native upload limit")
        with zipfile.ZipFile(archive) as z:
            require(z.testzip() is None and len(z.namelist()) == 43, "ZIP integrity check failed")
            require(all(n.startswith(SOURCE_DIR_NAME + "/") for n in z.namelist()),
                    "multiple deck folders detected")
        digest = hashlib.sha256(archive.read_bytes()).hexdigest()
        print(json.dumps({
            "stage": "ZIP_VERIFIED", "deck_id": DECK_ID,
            "files": len(files), "size_bytes": archive.stat().st_size,
            "sha256": digest,
        }, ensure_ascii=False), flush=True)

        # Download current production export locally as a pre-commit safety snapshot.
        old_export = base / "memento-671-precommit.zip"
        with session.open(urllib.request.Request(API + "export"), timeout=180) as response:
            require(response.status == 200, "could not back up current native export")
            with old_export.open("wb") as handle:
                shutil.copyfileobj(response, handle)
        with zipfile.ZipFile(old_export) as z:
            require(z.testzip() is None and any(n.endswith("stationx-deck.json") for n in z.namelist()),
                    "current native export is not a valid deck ZIP")
        print(json.dumps({
            "stage": "PRECOMMIT_EXPORT_VERIFIED", "bytes": old_export.stat().st_size,
            "sha256": hashlib.sha256(old_export.read_bytes()).hexdigest(),
        }), flush=True)

        boundary = "StationXNativeMemento671" + secrets.token_hex(12)
        part = archive.read_bytes()
        head = (f"--{boundary}\r\n"
                'Content-Disposition: form-data; name="package"; filename="Memento-671-A01T5.zip"\r\n'
                "Content-Type: application/zip\r\n\r\n").encode("ascii")
        payload = head + part + f"\r\n--{boundary}--\r\n".encode("ascii")
        common_headers = {"X-CSRF-Token": token, "Accept": "application/json"}
        preview = load_json(read_response(
            session, "preview", "POST", payload,
            {**common_headers, "Content-Type": "multipart/form-data; boundary=" + boundary},
            timeout=300,
        ), "preview")
        candidate = preview.get("candidate", {})
        require(candidate.get("deck_id") == DECK_ID
                and candidate.get("format") == "tcg_advanced"
                and len(candidate.get("cards", [])) == 39
                and len(candidate.get("routes", [])) == 12
                and preview.get("inventory_changed") is False,
                "live preview altered deck format, inventory, cards or routes")
        require(candidate.get("profile") == record["profile"], "live preview altered A01 player profile")
        require(candidate.get("player_guide") == record["player_guide"], "live preview altered A01 guide")
        compatibility = preview.get("compatibility") or {}
        require(compatibility.get("player_guide_status") == "PERFIL_PEDAGOGICO_VALIDADO",
                "native importer has not certified the guide")
        ticket = preview.get("ticket")
        require(isinstance(ticket, str) and len(ticket) >= 24, "no valid native preview ticket")
        print(json.dumps({
            "stage": "PREVIEW_APPROVED", "deck_id": DECK_ID,
            "cards": preview["cards"], "routes": preview["routes"],
            "new_routes": preview.get("new_routes"),
            "changed_routes": preview.get("changed_routes"),
            "removed_routes": preview.get("removed_routes"),
            "guide_certified": True, "commit_attempted": False,
        }, ensure_ascii=False), flush=True)

        # The user explicitly authorized this exact one-deck production import.
        commit_payload = urllib.parse.urlencode({"ticket": ticket}).encode("ascii")
        start = datetime.datetime.now(datetime.timezone.utc)
        commit = load_json(read_response(
            session, "commit", "POST", commit_payload,
            {**common_headers, "Content-Type": "application/x-www-form-urlencoded"},
            timeout=300,
        ), "commit")
        require(commit.get("deck") in ("Memento", record["name"]),
                "native commit returned a different deck")
        print(json.dumps({"stage": "COMMIT_ACCEPTED", "deck_id": DECK_ID,
                          "server_deck": commit.get("deck")}, ensure_ascii=False), flush=True)

        after = load_json(read_response(session, "status"), "status-after")
        updated = after.get("updated_at")
        require(after.get("cards") == 39 and after.get("routes") == 12,
                "post-commit status changed card or route count")
        require(isinstance(updated, str) and updated != before.get("updated_at"),
                "new native import timestamp was not registered")
        stamp = datetime.datetime.fromisoformat(updated.replace("Z", "+00:00"))
        require(stamp.tzinfo is not None and
                stamp >= start - datetime.timedelta(minutes=2),
                "native import timestamp is not fresh")
        print(json.dumps({"stage": "STATUS_AFTER", "deck_id": DECK_ID,
                          "updated_at": updated, "cards": 39, "routes": 12},
                         ensure_ascii=False), flush=True)

        exported = base / "memento-671-after.zip"
        with session.open(urllib.request.Request(API + "export"), timeout=300) as response:
            require(response.status == 200, "post-commit export unavailable")
            with exported.open("wb") as handle:
                shutil.copyfileobj(response, handle)
        with zipfile.ZipFile(exported) as z:
            require(z.testzip() is None, "post-commit ZIP corrupted")
            files_in_zip = [n for n in z.namelist() if n.endswith("/datos/stationx-deck.json")]
            require(len(files_in_zip) == 1, "post-commit export lacks unique portable JSON")
            exported_package = json.loads(z.read(files_in_zip[0]))
        exported_record = exported_package["record"]
        require(exported_record.get("deck_id") == 671 and
                exported_record.get("profile") == record["profile"] and
                exported_record.get("player_guide") == record["player_guide"] and
                len(exported_record.get("cards", [])) == 39 and
                len(exported_record.get("routes", [])) == 12,
                "post-commit re-export failed A01 profile/guide parity")
        print(json.dumps({
            "stage": "PRODUCTION_IMPORT_VERIFIED", "deck_id": 671,
            "updated_at": updated, "source_files": len(files),
            "profile_fields": len(FIELDS), "featured_cards": 4, "phases": 4,
            "cards": 39, "routes": 12, "export_roundtrip": True,
            "production_imported": True,
        }, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    require(len(sys.argv) == 2, "usage: pass GPT checkout path")
    main(sys.argv[1])
