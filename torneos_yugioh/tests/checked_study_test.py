"""Integrity of approved-unit selection and identity/photo provenance (no network)."""
from pathlib import Path
import hashlib, json, re, unittest, subprocess
APP=Path(__file__).resolve().parents[1]
SOURCE=APP/'context/gpt/STATION_X_PROMPTS_MODULARES_V8_APARTADO_01_ROL_OBJETIVO_11_DECKS'
OUT=APP/'assets/editorial/20261104-checked-study'

class CheckedStudy(unittest.TestCase):
    def test_every_published_card_has_individual_check_and_photo(self):
        manifest=json.loads((OUT/'manifest.json').read_text(encoding='utf-8'));self.assertEqual(len(manifest['decks']),11)
        count=0
        for deck_id,entry in manifest['decks'].items():
            raw=(APP/entry['path']).read_bytes();self.assertEqual(hashlib.sha256(raw).hexdigest(),entry['sha256'])
            deck=json.loads(raw);source=(SOURCE/'decks'/deck['source_file']).read_bytes()
            if hashlib.sha256(source).hexdigest()!=deck['source_sha256']:
                # An immutable delivery refers to its source revision, not later
                # authorized additions to that same maintained deck document.
                root=APP.parent;relative=(SOURCE/'decks'/deck['source_file']).relative_to(root).as_posix()
                for revision in subprocess.check_output(['git','log','--format=%H','--',relative],cwd=root,text=True).splitlines():
                    candidate=subprocess.check_output(['git','show',revision+':'+relative],cwd=root)
                    if hashlib.sha256(candidate).hexdigest()==deck['source_sha256']:source=candidate;break
            self.assertEqual(hashlib.sha256(source).hexdigest(),deck['source_sha256']);self.assertEqual(str(deck['deck_id']),deck_id)
            self.assertEqual(deck['combos'],[]);self.assertEqual(deck['engine_relations'],[])
            for card in deck['cards']:
                count+=1;self.assertFalse(card['rules_certified']);self.assertEqual(card['check'],'FICHA_A05')
                line=next(line for line in source.decode().splitlines() if line.startswith('| '+card['id']+' |'))
                self.assertIn('[X] FICHA_A05',line);self.assertTrue((APP/card['image']).is_file());self.assertTrue(card['fields'])
                photo=APP/card['image'];original=SOURCE/'imagenes'/photo.parent.name/photo.name
                self.assertEqual(photo.read_bytes(),original.read_bytes())
                self.assertTrue(all(s['quantity']>0 for s in card['slots']))
            if deck_id=='687':
                for suffix in ['C019','C029','C036']:
                    self.assertIsNone(next(c for c in deck['cards'] if c['id'].endswith(suffix))['classification'])
                self.assertEqual(next(c for c in deck['cards'] if c['cid']==13002)['slots'][0]['section'],'reserve')
        self.assertEqual(count,370)

if __name__=='__main__':unittest.main()
