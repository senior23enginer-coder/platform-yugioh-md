import copy
import importlib.util
from pathlib import Path
import tempfile
import unittest

spec=importlib.util.spec_from_file_location('editorial_sync',Path(__file__).resolve().parents[1]/'tools/editorial_sync.py')
e=importlib.util.module_from_spec(spec);spec.loader.exec_module(e)

class EditorialSyncTest(unittest.TestCase):
 def setUp(self):
  self.catalog=[{'deck_id':1,'name':'ALPHA','source_name':'ALPHA','format':'tcg_advanced'},{'deck_id':2,'name':'BETA','source_name':'BETA','format':'goat'}]
  self.raw=b'\xef\xbb\xbf'+('Texto original.\r\nTCG — 1 DECKS\r\n01. ALPHA\r\nPlan completo.\r\nGOAT — 1 DECKS\r\n01. BETA\r\nOtro plan.\r\nANEXO A — ALPHA\r\nA1. COMBOS\r\nCondición y restricción.\r\n').encode()
  self.store=e.split_source(self.raw,self.catalog,{'A-intro':[1],'A1':[1]})
  self.block=next(b for b in self.store['blocks'] if b['id']=='doc:A1')
 def delta(self):
  m=e.manifest(self.store)
  return {'schema_version':m['schema_version'],'update_id':'test-001','base_revision':m['revision'],'base_source_sha256':m['source_sha256'],'changes':[{'op':'replace','id':'doc:A1','previous_sha256':e.digest(self.block['text'].encode()),'deck_ids':[1],'section':'combos','reason':'Añadir condición documentada','text':self.block['text']+'Condición adicional.\r\n'}]}
 def test_exact_bom_and_crlf_roundtrip(self):
  self.assertEqual(e.source_bytes(self.store),self.raw)
 def test_one_change_preserves_all_other_blocks_and_original(self):
  before=copy.deepcopy(self.store);result=e.stage(self.store,self.delta())
  self.assertEqual(self.store,before)
  for b,new in zip(self.store['blocks'],result['blocks']):
   if b['id']!='doc:A1':self.assertEqual(b,new)
  self.assertNotEqual(e.manifest(result)['revision'],e.manifest(self.store)['revision'])
 def test_stale_source_revision_or_block_rejected(self):
  for field in ['base_revision','base_source_sha256']:
   d=self.delta();d[field]='stale'
   with self.assertRaises(ValueError):e.stage(self.store,d)
  d=self.delta();d['changes'][0]['previous_sha256']='0'*64
  with self.assertRaises(ValueError):e.stage(self.store,d)
 def test_deletion_and_blank_replacement_rejected(self):
  d=self.delta();d['changes'][0]['op']='delete'
  with self.assertRaises(ValueError):e.stage(self.store,d)
  d=self.delta();d['changes'][0]['text']=''
  with self.assertRaises(ValueError):e.stage(self.store,d)
 def test_cross_format_unknown_and_reassigned_decks_rejected(self):
  for owners in [[1,2],[999],[2]]:
   d=self.delta();d['changes'][0]['deck_ids']=owners
   with self.assertRaises(ValueError):e.stage(self.store,d)
 def test_duplicate_and_unrecognized_fields_rejected(self):
  d=self.delta();d['changes'].append(copy.deepcopy(d['changes'][0]))
  with self.assertRaises(ValueError):e.stage(self.store,d)
  d=self.delta();d['changes'][0]['decklist_write']=True
  with self.assertRaises(ValueError):e.stage(self.store,d)
 def test_addition_preserves_old_content(self):
  d=self.delta();c=d['changes'][0];c.update(op='add',id='deck:1:practice:test-001',previous_sha256=None,after_id='doc:A1',title='Prueba documentada',text='Ejercicio nuevo.\r\n')
  result=e.stage(self.store,d)
  self.assertEqual([b for b in result['blocks'] if b['id']!=c['id']],self.store['blocks'])
 def test_unchanged_content_and_repeated_update_id_rejected(self):
  d=self.delta();d['changes'][0]['text']=self.block['text']
  with self.assertRaises(ValueError):e.stage(self.store,d)
  d=self.delta();result=e.stage(self.store,d);m=e.manifest(result);d.update(base_revision=m['revision'],base_source_sha256=m['source_sha256'])
  with self.assertRaises(ValueError):e.stage(result,d)
 def test_staging_writes_separate_complete_bundle(self):
  with tempfile.TemporaryDirectory() as tmp:
   out=Path(tmp)/'candidate';d=self.delta();e.bundle(out,e.stage(self.store,d),d)
   self.assertTrue((out/'manifest.json').exists())
   self.assertEqual(e.read_json(out/'integration-plan.json')['database_writes'],0)
   with self.assertRaises(ValueError):e.bundle(out,self.store)
 def test_missing_saved_main_rejects_analysis_but_preserves_reference(self):
  inventory={'decks':[{'deck_id':1,'eligible_for_list_specific_analysis':False,'list_sha256':None}]}
  with self.assertRaises(ValueError):e.validate(self.store,self.delta(),inventory)
  d=self.delta();d['changes'][0]['section']='reference'
  e.validate(self.store,d,inventory)
 def test_saved_main_requires_exact_list_binding(self):
  inventory={'decks':[{'deck_id':1,'eligible_for_list_specific_analysis':True,'list_sha256':'a'*64}]}
  d=self.delta()
  with self.assertRaises(ValueError):e.validate(self.store,d,inventory)
  d['changes'][0]['visual']={'list_bindings':{'1':'b'*64}}
  with self.assertRaises(ValueError):e.validate(self.store,d,inventory)
  d['changes'][0]['visual']['list_bindings']['1']='a'*64
  result=e.stage(self.store,d,inventory)
  self.assertEqual(len(result['blocks']),len(self.store['blocks']))

if __name__=='__main__':unittest.main()
