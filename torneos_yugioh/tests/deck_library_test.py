import hashlib
import importlib.util
import json
from pathlib import Path
import re
import unittest
from urllib.parse import unquote
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('deck_library_builder', ROOT/'tools/build_deck_library.py')
builder = importlib.util.module_from_spec(spec)
spec.loader.exec_module(builder)


class LibraryTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.outputs, cls.manifest = builder.build()

    def test_generated_files_are_reproducible(self):
        for name, content in self.outputs.items():
            self.assertEqual((builder.DEST/name).read_bytes(), content, name)

    def test_complete_approved_inventory_and_originals(self):
        self.assertEqual(self.manifest['totals'], dict(chapters=396,cards=390,routes=126,hands=110))
        self.assertEqual(len(self.manifest['source_files']), 20)
        for source in self.manifest['source_files']:
            self.assertEqual(hashlib.sha256((builder.SOURCE/source['path']).read_bytes()).hexdigest(), source['sha256'])

    def test_every_card_has_all_eighteen_fields_including_royal(self):
        total = 0
        for deck_id in builder.IDS:
            d = json.loads(self.outputs[f'deck-{deck_id}.json'])
            self.assertEqual(d['deck_id'], deck_id)
            self.assertEqual(len(d['card_profiles']), len(d['card_checks']))
            for entry in d['card_profiles']:
                profile=entry['profile']
                self.assertEqual(len(profile.get('eighteen_obligatory_fields',profile.get('campos_18',{}))),18)
                total += 1
        self.assertEqual(total,390)

    def test_original_documents_contain_only_the_scoped_deck_navigation(self):
        for deck_id in builder.IDS:
            d=json.loads(self.outputs[f'deck-{deck_id}.json'])
            for doc in d['documents']:
                if doc['kind']!='html':
                    continue
                tree=builder.Document(doc['html']).root
                for node in tree.walk():
                    self.assertNotIn(node.tag,{'script','iframe','nav','aside','style','form','input'})
                    self.assertTrue(all(not key.startswith('on') for key in node.attrs))
                    if node.attrs.get('id'):
                        self.assertTrue(node.attrs['id'].startswith(f'source-{deck_id}-'))
                    for key in ['href','src']:
                        self.assertFalse(node.attrs.get(key,'').lower().startswith('javascript:'))

    def test_all_original_svg_diagrams_are_valid_images(self):
        count=0
        for name, content in self.outputs.items():
            if not re.fullmatch(r'deck-\d+\.json',name):
                continue
            d=json.loads(content)
            for doc in d['documents']:
                if doc['kind']!='html':
                    continue
                for n in builder.Document(doc['html']).root.walk():
                    src=n.attrs.get('src','')
                    if src.startswith('data:image/svg+xml;charset=utf-8,'):
                        ET.fromstring(unquote(src.split(',',1)[1]))
                        count+=1
        self.assertGreater(count,126)


if __name__=='__main__':
    unittest.main()
