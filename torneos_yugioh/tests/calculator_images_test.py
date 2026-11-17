"""Check local card identities and original-image integrity without network access."""
import hashlib
import json
from pathlib import Path
import unittest
from urllib.parse import parse_qs, urlparse

APP = Path(__file__).resolve().parents[1]


class CalculatorImageTests(unittest.TestCase):
    def test_every_documented_card_has_its_bound_local_image(self):
        inventory = json.loads((APP / 'tests/fixtures/calculator_inventory.json').read_text(encoding='utf-8'))
        index = json.loads((APP / 'assets/cards/originals/index.json').read_text(encoding='utf-8'))
        expected = {f"{c['deck_id']}:{c['id']}" for c in inventory['cards']}
        self.assertEqual(set(index['cards']), expected)
        self.assertEqual(len(expected), 390)
        formats = {int(d['id']): d['format_codes'] for d in inventory['allDecks']}
        for card in inventory['cards']:
            image = index['cards'][f"{card['deck_id']}:{card['id']}"]
            namespace = 'rushdb' if 'rush_duel' in formats[int(card['deck_id'])] else 'yugiohdb'
            self.assertTrue(Path(image).name.startswith(namespace + '-'), image)
        for key, source in index['sources'].items():
            image = (APP / source['file'].removeprefix('./')).resolve()
            self.assertTrue(image.is_relative_to(APP / 'assets/cards/originals'))
            body = image.read_bytes()
            self.assertTrue(body.startswith(b'\xff\xd8'), key)
            self.assertEqual(hashlib.sha256(body).hexdigest(), source['sha256'], key)
            url = urlparse(source['source'])
            namespace, cid = key.rsplit('-', 1)
            self.assertEqual(url.hostname, 'www.db.yugioh-card.com')
            self.assertTrue(url.path.startswith('/' + namespace + '/'))
            self.assertEqual(parse_qs(url.query)['cid'], [cid])
        self.assertEqual(len(index['sources']), len(set(index['cards'].values())))


if __name__ == '__main__':
    unittest.main()
