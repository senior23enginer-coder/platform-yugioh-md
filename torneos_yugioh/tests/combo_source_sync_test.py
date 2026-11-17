import copy
import json
from pathlib import Path
import sys
import tempfile
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'tools'))
from combo_source_sync import apply_feed, apply_overrides, stable_id, SOURCE


class SourceSyncTest(unittest.TestCase):
    def test_incremental_route_updates_its_own_source(self):
        feed = copy.deepcopy(json.loads(Path(sys.argv[1]).read_text(encoding='utf-8')))
        row = feed['records'][0]
        key = '665:A09:V11:TEARKASH:OGRE:BIRTH:SHANGRI'
        row.update(deck_id=665, route_id=key, id=stable_id(665, key))
        row['document'].update(deck_id=665, source_route_id=key, id=row['id'])
        with tempfile.TemporaryDirectory(prefix='stationx-source-v12-') as folder:
            source = Path(folder)
            files = ['GUIAS_Y_DECISIONES_V16.json', 'LOTE_JUGABLE_11_DECKS_A01_A10_V11.json', 'COMBOS_COTEJADOS_A09_A10_V12.json']
            # Independent fixtures also exercise historical multi-file imports.
            (source/files[0]).write_text(json.dumps({'rutas':[]}),encoding='utf-8')
            (source/files[1]).write_text(json.dumps({'rutas':[{'id':key,'deck_id':665}]}),encoding='utf-8')
            (source/files[2]).write_text(json.dumps({'rutas':[]}),encoding='utf-8')
            baseline = (source/files[0]).read_bytes()
            self.assertEqual(apply_feed(feed, source), [key])
            self.assertEqual(apply_feed(feed, source), [])
            self.assertEqual((source/files[0]).read_bytes(), baseline)
            supplemental = json.loads((source/files[1]).read_text(encoding='utf-8'))
            self.assertIn('revision_editor_vigente', next(r for r in supplemental['rutas'] if r['id'] == key))
            data = {'deck_id':665, 'routes':[{'id':key}]}
            apply_overrides(data, source)
            self.assertEqual(data['routes'][0]['native_editor_document'], row['document'])

    def test_native_save_to_source_and_reimport(self):
        feed = json.loads(Path(sys.argv[1]).read_text(encoding='utf-8'))
        with tempfile.TemporaryDirectory(prefix='stationx-source-') as folder:
            source = Path(folder)
            catalog_file = source / 'COMBOS_EXISTENTES_INDICE_EDITOR.json'
            original = json.dumps({'rutas':[{'id':r['route_id'],'deck_id':r['deck_id']} for r in feed['records']]}).encode('utf-8')
            catalog_file.write_bytes(original)
            self.assertTrue(apply_feed(feed, source))
            self.assertEqual(apply_feed(feed, source), [])
            index = json.loads((source / 'revisiones_editor/index.json').read_text(encoding='utf-8'))
            row = feed['records'][0]
            revised = source / 'revisiones_editor' / index['routes'][row['route_id']]['path']
            self.assertIn(row['document']['title'], revised.with_suffix('.md').read_text(encoding='utf-8'))
            catalog = json.loads(catalog_file.read_text(encoding='utf-8'))
            for route in catalog['rutas']:
                route.pop('revision_editor_vigente', None)
            self.assertEqual(catalog, json.loads(original), 'Original routes and inventories preserved')
            data = {'deck_id': row['deck_id'], 'routes': [{'id': row['route_id'], 'title': 'Historical'}]}
            apply_overrides(data, source)
            self.assertEqual(data['routes'][0]['native_editor_document'], row['document'])
            newer = copy.deepcopy(feed)
            newer['records'][0]['revision'] += 1
            newer['records'][0]['document']['title'] = 'Siguiente revisión'
            apply_feed(newer, source)
            self.assertEqual(json.loads(revised.read_text(encoding='utf-8'))['document'], row['document'])
            with self.assertRaises(ValueError):
                apply_feed(feed, source)
            foreign = copy.deepcopy(newer)
            foreign['records'][0]['deck_id'] = 650
            with self.assertRaises(ValueError):
                apply_feed(foreign, source)
            conflict = copy.deepcopy(newer)
            conflict['records'][0]['document']['title'] = 'Conflicto'
            with self.assertRaises(ValueError):
                apply_feed(conflict, source)


if __name__ == '__main__':
    unittest.main(argv=[sys.argv[0]])
