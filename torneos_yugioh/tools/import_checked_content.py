"""Apply the Codex guide's individual eligibility gate to the existing reader.

No source documents, inventories or native profiles are rewritten. Existing
archives retain rejected material; public records contain only eligible units.
"""
import argparse
import copy
import hashlib
import json
import re
from pathlib import Path
from build_checked_study import APP, SOURCE, sections, inventory, norm

VERSION = '20261107-checked-content-142'
OUT = APP / 'assets/editorial/20261107-checked-content'
BASE = APP / 'assets/editorial/20261107-study-eleven'


def sha(raw):
    return hashlib.sha256(raw).hexdigest()


def official_urls(text):
    return sorted(set(re.findall(r'https://(?:www\.db\.yugioh-card\.com|yugiohblog\.konami\.com)/[^\s)\]>*]+', text)))


def build(live):
    OUT.mkdir(exist_ok=True)
    entries = {}
    totals = dict(cards=0, interactions=0, cases=0, certified_combos=0)
    for file in sorted((SOURCE / 'decks').glob('DECK_*.md')):
        deck_id = int(file.name.split('_')[1])
        parts = sections(file.read_text(encoding='utf-8'))
        previous = json.loads((BASE / f'{deck_id}.json').read_text(encoding='utf-8'))
        native = next(d for d in live['allDecks'] if int(d['id']) == deck_id)
        assert native['name'] == previous['name'] and previous['format'] in native['format_codes']
        rows = [c for c in live['cards'] if int(c['deck_id']) == deck_id]
        signature = lambda rs: sorted((int(r.get('card_id', r.get('id'))), r['section'], int(r['quantity']), r.get('name', r.get('card_name'))) for r in rs)
        assert signature(rows) == signature(previous['inventory']), (deck_id, 'live inventory differs')
        # Also compare the current source inventory, independently of its old snapshot.
        source_rows = inventory(file.read_text(encoding='utf-8'))
        assert len(source_rows) == len(rows)
        for item in source_rows:
            matches = [r for r in rows if r['section'] == item['section'] and int(r['quantity']) == item['quantity']
                       and {norm(r['card_name']), *(norm(n) for n in r['card_name'].split(' / '))}.intersection(norm(n) for n in item['names'])]
            assert len(matches) == 1, (deck_id, item)
        checked_cards = {}
        interactions = []
        for line in parts[8].splitlines():
            if not line.startswith('|'):
                continue
            cells = [x.strip() for x in line.strip('|').split('|')]
            if len(cells) != 6 or '[X]' not in cells[5]:
                continue
            if re.fullmatch(rf'{deck_id}:A08:C\d+', cells[0]) and 'FICHA_A05' in cells[5]:
                checked_cards[cells[0]] = cells
            elif re.fullmatch(rf'{deck_id}:A08:\d+', cells[0]) and 'CHEQUEAD' in cells[5] and '[~]' not in cells[5]:
                # Keep only the checked interaction/blocking fact, never its pending route.
                interactions.append(dict(id=cells[0], kind='blocker' if 'BLOQUEO_CHEQUEADO' in cells[5] else 'interaction',
                    title=cells[2], result=cells[3], requirements=cells[4], check=cells[5].split(';')[0]))
        cards = []
        for m in re.finditer(r'^#### (\d+)\. (.*?)\n(.*?)(?=^#### |^### |\Z)', parts[5], re.M | re.S):
            key = f'{deck_id}:A08:C{int(m[1]):03}'
            if key not in checked_cards:
                continue
            photo = re.search(r'\.\./imagenes/(?:tcg|rush)/(\d+)\.', m[3])
            assert photo
            card = copy.deepcopy(next(c for c in previous['cards'] if c['cid'] == int(photo[1])))
            assert card['id'] == key and official_urls(m[3]), (deck_id, key, 'missing card evidence')
            body = re.sub(r'!\[[^\]]*\]\([^)]*\)', '', m[3]).strip()
            card.update(name=m[2].strip(), text=body, fields=[dict(title=f[1].rstrip(':'), text=f[2].strip())
                for f in re.finditer(r'^- \*\*(.*?)\*\*[:：]?\s*(.*?)(?=^- \*\*|\Z)', body, re.M | re.S)])
            cards.append(card)
        cases = []
        blocks = []
        for block in re.split(r'(?=^#### )', parts[11], flags=re.M):
            if previous['format'] == 'rush_duel' and re.search(r'\bSkill\b', block, re.I):
                # A separate checked bullet with its own route ID can be used
                # independently of an unadopted Skill in an adjacent case.
                for bullet in re.findall(r'^- .*?(?=^- |\Z)', block, re.M | re.S):
                    if '[X]' in bullet and re.search(r'`[A-Z0-9_:]+`', bullet) and not re.search(r'\bSkill\b', bullet, re.I):
                        blocks.append(block.splitlines()[0]+'\n\n'+bullet.strip())
            else:
                blocks.append(block)
        for block in blocks:
            checks = re.findall(r'\[X\]\s+([A-Z][A-Z_0-9]+)', block)
            if not checks or not block.startswith('#### '):
                continue
            # Gate the Skill-dependent unit, not every case of the Rush format.
            if previous['format'] == 'rush_duel' and re.search(r'\bSkill\b', block, re.I):
                continue
            tokens = []
            for token in re.findall(r'`([A-Z0-9_:/\*]+)`', block):
                if re.fullmatch(r'.*\d[A-Z](?:/[A-Z])+', token):
                    head, *tails = token.split('/')
                    tokens.extend([head, *(head[:-1]+tail for tail in tails)])
                else:
                    tokens.append(token)
            def matches(route_id, token):
                if token.endswith(':*'):
                    return (':'+token[:-1]) in (':'+route_id)
                return route_id == token or route_id.endswith(':'+token)
            referenced = [r for r in previous['routes'] if any(matches(r['id'], t) for t in tokens)]
            evidence = official_urls(block + '\n' + '\n'.join(r['source_full'] for r in referenced))
            if not evidence or not referenced:
                continue
            cases.append(dict(id=f'{deck_id}:A11:'+sha(block.splitlines()[0].encode())[:12],
                title=block.splitlines()[0].removeprefix('#### A11 — '), text=block.strip(),
                check=checks, sources=evidence, reference_ids=[r['id'] for r in referenced], full_combo_certified=False))
        # Fail closed: no CERTIFICADA + GUIA_COMPLETA evidence in this delivery.
        # Contrasted no-response sequences and editorial completion do not qualify.
        assert not re.search(r'\[X\]\s+(?:COMBO_)?CERTIFICADA\b', parts[9]), 'New certification requires explicit per-route mapping'
        data = dict(deck_id=deck_id, name=native['name'], format=previous['format'],
            source_file=file.name, source_sha256=sha(file.read_bytes()), guide_sha256=sha((SOURCE/'GUIA_CODEX_STATION_X.md').read_bytes()),
            eligibility_policy='checked-only-v1', inventory=previous['inventory'], cards=cards,
            combos=[], engine_relations=[], routes=[], checked_interactions=interactions, checked_cases=cases)
        raw = (json.dumps(data, ensure_ascii=False, indent=2)+'\n').encode()
        path = OUT / f'{deck_id}.json'
        # An unchanged deck/guide produces no rewrite and no new history entry.
        if not path.exists() or path.read_bytes() != raw:
            path.write_bytes(raw)
        entries[str(deck_id)] = dict(path=f'./assets/editorial/20261107-checked-content/{deck_id}.json',
            cards=len(cards), sections=11, interactions=len(interactions), cases=len(cases), routes=0, sha256=sha(raw), eligibility_policy='checked-only-v1')
        for key, value in [('cards', len(cards)), ('interactions', len(interactions)), ('cases', len(cases))]:
            totals[key] += value
    policy_path = APP / 'assets/editorial/strategy-content-state.json'
    policy = json.loads(policy_path.read_text(encoding='utf-8'))
    policy.update(version=VERSION, checked_studies=entries, active_scope='Checked individual units within 01-11 only; uncertified combos excluded; originals preserved.')
    policy_path.write_bytes((json.dumps(policy, ensure_ascii=False, indent=2)+'\n').encode())
    print(json.dumps(dict(decks=len(entries), **totals, inventory_writes=0)))
    return entries


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--live-snapshot', required=True)
    build(json.loads(Path(parser.parse_args().live_snapshot).read_text(encoding='utf-8-sig')))
