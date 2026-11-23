<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/includes/decks/analysis-progress.php';

function ck(bool $condition,string $message):void {
    if(!$condition)throw new RuntimeException($message);
}
function expect_fail(callable $call,string $message):void {
    try {$call();} catch(DomainException|InvalidArgumentException $e){return;}
    throw new RuntimeException('Se esperaba bloqueo: '.$message);
}
$manifest=ygo_progress_manifest(dirname(__DIR__));
$inv=['deck'=>['id'=>98765],'format'=>'tcg_advanced',
  'inventory_sha256'=>hash('sha256','synthetic fixture'),
  'cards'=>[
    ['id'=>1,'section'=>'main','quantity'=>2],
    ['id'=>2,'section'=>'extra','quantity'=>1],
    ['id'=>3,'section'=>'side','quantity'=>1]
  ]];
ck(ygo_progress_inventory_counts($inv)===['main'=>2,'extra'=>1,'side'=>1,'reserve'=>0],
  'The optional Reserve must be empty without blocking admission.');
expect_fail(static fn()=>ygo_progress_new_ledger(98765,$inv,$manifest,false,'2026-11-21T12:00:00Z'),
  'no assignment');
$withoutSide=$inv;
$withoutSide['cards']=array_values(array_filter($inv['cards'],static fn($r)=>$r['section']!=='side'));
ck(ygo_progress_new_ledger(98765,$withoutSide,$manifest,true,'2026-11-21T12:00:00Z')['inventory_counts']['side']===0,
  'Side must be optional under current Main+Extra minimum');
// Format-specific admission: Rush Duel and GOAT can legally omit both Extra and Side.
foreach(['goat','rush_duel','rush'] as $format){
  $single=$inv;
  $single['format']=$format;
  $single['cards']=[['id'=>1,'section'=>'main','quantity'=>2]];
  ck(ygo_progress_required_sections($format)===['main'],'Format must require Main only: '.$format);
  ck(ygo_progress_new_ledger(98765,$single,$manifest,true,'2026-11-21T12:00:00Z')['inventory_counts']['extra']===0,
    'GOAT/Rush Main-only deck incorrectly blocked: '.$format);
}
foreach(['tcg','tcg_advanced','tcg_traditional','ocg','ocg_advanced'] as $format){
  ck(ygo_progress_required_sections($format)===['main','extra'],
    'TCG/OCG minimum changed: '.$format);
}
expect_fail(static fn()=>ygo_progress_required_sections('unspecified'),
  'Unverified format cannot be interpreted as TCG or GOAT');
foreach(['main','extra'] as $missing){
  $copy=$inv;
  $copy['cards']=array_values(array_filter($copy['cards'],static fn($r)=>$r['section']!==$missing));
  expect_fail(static fn()=>ygo_progress_new_ledger(98765,$copy,$manifest,true,'2026-11-21T12:00:00Z'),
    'required section '.$missing);
}
expect_fail(static fn()=>ygo_progress_new_ledger(12345,$inv,$manifest,true,'2026-11-21T12:00:00Z'),
  'another deck');
$copy=$inv;
$copy['cards'][]=['id'=>4,'section'=>'tech','quantity'=>2];
ck(ygo_progress_inventory_counts($copy)['reserve']===2,'Tech is optional Reserve.');
$ledger=ygo_progress_new_ledger(98765,$inv,$manifest,true,'2026-11-21T12:00:00Z');
ck(count($ledger['tasks'])===40,'A01-A10A=37, A10B=3.');
ck(ygo_progress_summary($ledger)['total']===37,'Optional A10B must not affect mandatory progress.');
ck(ygo_progress_summary($ledger)['eta_seconds']===null,'Never claim fabricated task ETA.');
expect_fail(static fn()=>ygo_progress_transition($ledger,'A01-T2','start','2026-11-21T12:01:00Z'),
  'cannot skip earlier tasks');
expect_fail(static fn()=>ygo_progress_transition($ledger,'A10B-T4','start','2026-11-21T12:01:00Z'),
  'A10B not requested');
$ledger=ygo_progress_transition($ledger,'A01-T1','start','2026-11-21T12:00:01Z');
expect_fail(static fn()=>ygo_progress_transition($ledger,'A01-T1','complete','2026-11-21T12:02:01Z'),
  'cannot complete without evidence');
$ledger=ygo_progress_transition($ledger,'A01-T1','complete','2026-11-21T12:02:01Z','source://verified');
ck($ledger['tasks'][0]['elapsed_seconds']===120,'Elapsed time must be the real duration.');
ck(ygo_progress_summary($ledger)['complete']===1,'Exactly one task was evidenced.');
$ledger=ygo_progress_transition($ledger,'A01-T2','start','2026-11-21T12:03:01Z');
expect_fail(static fn()=>ygo_progress_transition($ledger,'A01-T2','block','2026-11-21T12:03:51Z'),
  'no silent blocks');
$ledger=ygo_progress_transition($ledger,'A01-T2','block','2026-11-21T12:03:51Z',null,'missing specific source');
ck(ygo_progress_summary($ledger)['blocked']===1,'Blocked task is not complete.');
$ledger=ygo_progress_transition($ledger,'A01-T2','start','2026-11-21T12:04:00Z');
$ledger=ygo_progress_transition($ledger,'A01-T2','complete','2026-11-21T12:05:00Z','source://recovered');
ck($ledger['tasks'][1]['elapsed_seconds']===110,'Retry time accumulates without inventing work.');
// Safe P04 recovery: paused intervals are excluded from measured active time.
// A reload reads this saved ledger; no AI request is ever triggered by resume.
$ledger=ygo_progress_transition($ledger,'A01-T3','start','2026-11-21T12:06:00Z');
$ledger=ygo_progress_transition($ledger,'A01-T3','pause','2026-11-21T12:06:30Z');
ck($ledger['tasks'][2]['status']==='PAUSED','Paused checkpoint survives serialization.');
ck($ledger['tasks'][2]['elapsed_seconds']===30,'First active segment is measured.');
expect_fail(static fn()=>ygo_progress_transition($ledger,'A01-T3','complete',
  '2026-11-21T13:06:00Z','proof'), 'paused tasks may not be completed');
expect_fail(static fn()=>ygo_progress_transition($ledger,'A01-T4','start',
  '2026-11-21T13:06:00Z'), 'cannot jump past a paused task');
$checkpoint=json_decode(json_encode($ledger,JSON_THROW_ON_ERROR),true,64,JSON_THROW_ON_ERROR);
$checkpoint=ygo_progress_transition($checkpoint,'A01-T3','resume','2026-11-21T13:06:00Z');
ck($checkpoint['tasks'][2]['attempts']===2,'Resume starts a second observed work segment.');
$checkpoint=ygo_progress_transition($checkpoint,'A01-T3','complete',
  '2026-11-21T13:06:30Z','real evidence');
ck($checkpoint['tasks'][2]['elapsed_seconds']===60,
  'One-hour pause does not become AI processing time.');
ck($checkpoint['tasks'][2]['status']==='COMPLETE','Only actual evidence closes a resumed task.');
$ledger=$checkpoint;
$ledger['calculator_requested']=true;
ck(ygo_progress_summary($ledger)['total']===40,'A10B opt-in counts only when requested.');
if(in_array('sqlite',PDO::getAvailableDrivers(),true)){
  $pdo=new PDO('sqlite::memory:');
  $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
  ygo_progress_schema($pdo);
  $fingerprint=hash('sha256','synthetic fixture');
  ygo_progress_write($pdo,$ledger,$fingerprint,'tcg_advanced',null);
  $saved=ygo_progress_saved($pdo,98765);
  ck($saved!==null && $saved['revision']===$ledger['revision'],'Persisted revision and ledger must roundtrip.');
  expect_fail(static fn()=>ygo_progress_write($pdo,$ledger,$fingerprint,'tcg_advanced',999999),
    'concurrent stale write');
  $next=$saved['ledger'];$next['revision']++;
  $next['updated_at']='2026-11-21T12:06:00Z';
  ygo_progress_write($pdo,$next,$fingerprint,'tcg_advanced',$saved['revision']);
  ck(ygo_progress_saved($pdo,98765)['revision']===$next['revision'],
    'Optimistic write can commit once.');
}
// Five comparable synthetic durations for one *unfinished* A02 task cannot
// calibrate the entire deck, or another unfinished task in the same stage.
$partial=['A02-T1'=>[41,42,43,44,45]];
$unknown=ygo_progress_summary($ledger,$partial);
ck($unknown['eta_seconds']===null,'Whole-deck ETA needs all tasks calibrated.');
$a01=array_values(array_filter($unknown['stages'],
  static fn($stage)=>$stage['stage_id']==='A01'))[0];
ck($a01['eta_seconds']===null,'A01-T4/T5 remain open until evidenced for this deck.');
ck($a01['total']===5&&$a01['complete']===3,'Historical T1-T3 evidence must not complete T4/T5.');
$a02=array_values(array_filter($unknown['stages'],
  static fn($stage)=>$stage['stage_id']==='A02'))[0];
ck($a02['eta_seconds']===null,'Other A02 tasks must not inherit the ETA of A02-T1.');
ck(ygo_progress_summary($ledger)['eta_status']==='INSUFFICIENT_REAL_SAMPLES',
  'Real ETA needs historical successes from comparable real decks.');
echo json_encode(['ok'=>true,'synthetic_tests'=>true,'analyses_executed'=>0,
 'mandatory_tasks'=>37,'optional_a10b_tasks'=>3,'persisted'=>in_array('sqlite',PDO::getAvailableDrivers(),true)],
 JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
