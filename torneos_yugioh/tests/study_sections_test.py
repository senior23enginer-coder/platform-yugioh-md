from pathlib import Path
import hashlib,json,sys,unittest
APP=Path(__file__).resolve().parents[1];sys.path.insert(0,str(APP/'tools'))
from build_checked_study import SOURCE,sections

class SevenSections(unittest.TestCase):
    def test_only_seven_source_sections_and_preserved_cards(self):
        folder=APP/'assets/editorial/20261104-study-seven';manifest=json.loads((folder/'manifest.json').read_text(encoding='utf-8'));self.assertEqual(len(manifest['decks']),11)
        count=maps=skills=0
        for deck_id,entry in manifest['decks'].items():
            raw=(APP/entry['path']).read_bytes();self.assertEqual(hashlib.sha256(raw).hexdigest(),entry['sha256']);record=json.loads(raw)
            original=(SOURCE/'decks'/record['source_file']).read_bytes();source=sections(original.decode());self.assertEqual(hashlib.sha256(original).hexdigest(),record['source_sha256'])
            self.assertEqual([s['number']for s in record['sections']],list(range(1,8)))
            for s in record['sections']:self.assertEqual(s['source_text'],source[s['number']].strip());self.assertFalse(s['rules_certified'])
            archive=json.loads((APP/f'assets/editorial/20261104-checked-study/{deck_id}.json').read_text(encoding='utf-8'))
            self.assertEqual(record['cards'],archive['cards']);self.assertEqual(record['inventory'],archive['inventory']);self.assertEqual(record['combos'],[]);self.assertEqual(record['engine_relations'],[])
            own={(c['cid'],c['image'])for c in record['cards']}
            for m in record['engine_maps']:
                self.assertEqual(m['source_section'],6);self.assertFalse(m['is_combo']);self.assertFalse(m['rules_certified']);self.assertTrue(m['identity_links_checked']);self.assertTrue(m['requirements'])
                for p in m['photos']:
                    self.assertTrue((APP/p['image']).is_file())
                    if p['kind']=='card':self.assertIn((p['cid'],p['image']),own)
                    else:self.assertTrue(p['not_in_draw_pool'])
            count+=len(record['sections']);maps+=len(record['engine_maps']);skills+=len(record['skills'])
        self.assertEqual((count,maps,skills),(77,58,4))

if __name__=='__main__':unittest.main()
