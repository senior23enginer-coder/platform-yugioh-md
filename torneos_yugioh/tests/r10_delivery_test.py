import importlib.util,json,unittest
from pathlib import Path
APP=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('r10',APP/'tools/build_r10_delivery.py')
b=importlib.util.module_from_spec(spec);spec.loader.exec_module(b)

class R10Tests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.inventory=json.loads((APP/'tests/fixtures/r10_inventory.json').read_text(encoding='utf-8'))
        cls.files,cls.index=b.build(cls.inventory)
    def test_reproducible_delivery_and_active_index(self):
        for n,body in self.files.items():self.assertEqual((b.DEST/n).read_bytes(),body,n)
        self.assertEqual((APP/'assets/editorial/deck-deliveries.json').read_bytes(),b.dump(self.index))
    def test_only_folder_01_and_exact_source_records(self):
        manifest=json.loads(self.files['manifest.json'])
        self.assertEqual(len(manifest['source_files']),142)
        for n in manifest['source_files']:self.assertNotIn('03_',n);self.assertNotIn('02_',n)
        for id_,row in self.index['decks'].items():
            record=json.loads(self.files[f'deck-{id_}.json'])
            path=b.SOURCE/'decks'/str(record['curated']['identificador_paquete']+'.json')
            self.assertEqual(record['curated'],json.loads(path.read_text(encoding='utf-8')))
    def test_all_counts_states_and_skills(self):
        m=json.loads(self.files['manifest.json'])
        self.assertEqual(m['counts'],dict(scenario_links=150,decks=11,cards=390,routes=126,hands=110,chapters=396,skills=4,scenarios=66,matchups=42,ratings=55))
        self.assertEqual(m['route_states'],dict(CONDICIONAL=114,PENDIENTE_DE_VERIFICACION=6,NO_EJECUTABLE=6))
        for id_ in [706,704,707,705]:
            r=json.loads(self.files[f'deck-{id_}.json']);self.assertEqual(len(r['skills']),1)
            self.assertTrue(r['curated']['skill_not_in_draw_pool'])
        royal=json.loads(self.files['deck-705.json'])
        self.assertEqual(royal['curated']['inventario']['extra'],[])
    def test_no_inventory_changes_or_new_routes(self):
        import copy
        original=copy.deepcopy(self.inventory);b.build(self.inventory);self.assertEqual(original,self.inventory)
        for id_ in self.index['decks']:
            r=json.loads(self.files[f'deck-{id_}.json']);self.assertEqual(len(r['combos']),len(r['curated']['combos']))
            self.assertTrue(all(len(c['analisis_18_campos'])==18 for c in r['curated']['cartas']))
            e=r['curated']['evaluacion_competitiva_justificada'];self.assertEqual(sum(c['calificacion_1_10']for c in e['dimensiones'])/5,e['promedio_competitivo'])
    def test_changed_inventory_is_rejected_without_overwriting(self):
        import copy
        changed=copy.deepcopy(self.inventory);changed['cards'][0]['quantity']+=1
        with self.assertRaises(AssertionError):b.build(changed)

if __name__=='__main__':unittest.main()
