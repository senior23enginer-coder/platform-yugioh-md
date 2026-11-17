"""Owner-authorized documentary import 01-10. Never activate section 11."""
import argparse
import json
from pathlib import Path
from build_study_eleven import build
from build_checked_study import APP


def apply(live):
    manifest = build(live, max_section=10, output=APP/'assets/editorial/20261107-study-ten')
    path = APP/'assets/editorial/strategy-content-state.json'
    policy = json.loads(path.read_text(encoding='utf-8'))
    policy.update(version=manifest['version'], checked_studies=manifest['decks'],
        active_scope='Owner instruction: all received documentary content 01-10; section 11 removed from active content; no certification implied.')
    path.write_bytes((json.dumps(policy, ensure_ascii=False, indent=2)+'\n').encode())
    return manifest


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--live-snapshot', required=True)
    apply(json.loads(Path(parser.parse_args().live_snapshot).read_text(encoding='utf-8-sig')))
