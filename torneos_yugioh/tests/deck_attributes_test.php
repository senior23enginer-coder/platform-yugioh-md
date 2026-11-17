<?php
declare(strict_types=1);
require __DIR__.'/../includes/decks/attributes.php';
require __DIR__.'/deck_worlds_fixture.php';
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
ygo_worlds_test_schema($pdo);
foreach([[650,'Elfnote','tcg_advanced'],[665,'Kashtira Pure','tcg_advanced'],[647,'Branded','tcg_traditional'],[704,'Pyro','rush_duel'],[800,'GOAT','goat']] as [$id,$name,$format])ygo_worlds_test_deck($pdo,$id,$name,$format);
$pdo->exec('CREATE TABLE unaffected_cards(id INT,quantity INT); INSERT INTO unaffected_cards VALUES(1,3); CREATE TABLE unaffected_results(id INT,result TEXT); INSERT INTO unaffected_results VALUES(1,"A")');
ygo_deck_attributes_schema($pdo);$checks=0;
function daCheck(bool $ok): void {global $checks;$checks++;if(!$ok)throw new RuntimeException('Deck attributes test failed at '.$checks);}
function daReject(callable $fn,string $type=InvalidArgumentException::class): void {try{$fn();}catch(Throwable $e){daCheck($e instanceof $type);return;}throw new RuntimeException('Expected rejection');}
$r=ygo_deck_attributes_save($pdo,['deck_id'=>650,'expected_revision'=>0,'attributes'=>['dark','water','fire']]);daCheck($r['revision']===1&&$r['primary']==='dark'&&count($r['attributes'])===3);
$same=ygo_deck_attributes_save($pdo,['deck_id'=>650,'expected_revision'=>1,'attributes'=>['dark','water','fire']]);daCheck($same['revision']===1);
daReject(fn()=>ygo_deck_attributes_save($pdo,['deck_id'=>650,'expected_revision'=>1,'attributes'=>['dark','water','fire','wind']]));
daReject(fn()=>ygo_deck_attributes_save($pdo,['deck_id'=>650,'expected_revision'=>1,'attributes'=>['water','water']]));
daReject(fn()=>ygo_deck_attributes_save($pdo,['deck_id'=>650,'expected_revision'=>1,'attributes'=>['divine']]));
daReject(fn()=>ygo_deck_attributes_save($pdo,['deck_id'=>650,'expected_revision'=>1,'attributes'=>[]]));
daReject(fn()=>ygo_deck_attributes_save($pdo,['deck_id'=>999,'expected_revision'=>0,'attributes'=>['light']]));
$r=ygo_deck_attributes_save($pdo,['deck_id'=>650,'expected_revision'=>1,'attributes'=>['water','dark','fire']]);daCheck($r['revision']===2&&$r['primary']==='water');
daReject(fn()=>ygo_deck_attributes_save($pdo,['deck_id'=>650,'expected_revision'=>1,'attributes'=>['earth']]),DomainException::class);
daCheck(ygo_deck_attributes_records($pdo)[0]['primary']==='water');
daReject(fn()=>ygo_deck_attributes_save($pdo,['deck_id'=>650,'expected_revision'=>2,'action'=>'remove']));
$r=ygo_deck_attributes_save($pdo,['deck_id'=>650,'expected_revision'=>2,'attributes'=>['dark']]);daCheck($r['revision']===3&&$r['primary']==='dark'&&$r['attributes']===['dark']);
daReject(fn()=>ygo_deck_attributes_save($pdo,['deck_id'=>650,'expected_revision'=>0,'attributes'=>['fire']]),DomainException::class);
$r=ygo_deck_attributes_save($pdo,['deck_id'=>650,'expected_revision'=>3,'attributes'=>['fire']]);daCheck($r['revision']===4&&$r['primary']==='fire');
daCheck(count(ygo_deck_attributes_history($pdo,650))===4);
$pdo->exec("CREATE TRIGGER fail_history BEFORE INSERT ON sx_ygo_deck_attribute_revisions WHEN NEW.deck_id=665 BEGIN SELECT RAISE(ABORT,'fixture failure'); END");
daReject(fn()=>ygo_deck_attributes_save($pdo,['deck_id'=>665,'expected_revision'=>0,'attributes'=>['earth']]),PDOException::class);
daCheck(count(ygo_deck_attributes_records($pdo))===1);
daCheck((int)$pdo->query('SELECT quantity FROM unaffected_cards')->fetchColumn()===3&&$pdo->query('SELECT result FROM unaffected_results')->fetchColumn()==='A');
daCheck((int)$pdo->query('SELECT COUNT(*) FROM sx_ygo_decks')->fetchColumn()===5);
daReject(fn()=>ygo_deck_attributes_save($pdo,['deck_id'=>704,'expected_revision'=>0,'attributes'=>['fire']]));
daReject(fn()=>ygo_deck_attributes_save($pdo,['deck_id'=>800,'expected_revision'=>0,'attributes'=>['dark']]));
$traditional=ygo_deck_attributes_save($pdo,['deck_id'=>647,'expected_revision'=>0,'attributes'=>['light']]);daCheck($traditional['primary']==='light');
$pdo->exec("INSERT INTO sx_ygo_deck_attributes VALUES(704,'[\"fire\"]',1,'2026-11-14 10:00:00')");
daCheck(count(ygo_deck_attributes_records($pdo))===2);
daCheck((int)$pdo->query('SELECT COUNT(*) FROM sx_ygo_deck_attributes WHERE deck_id=704')->fetchColumn()===1);
daReject(fn()=>ygo_deck_attributes_save($pdo,['deck_id'=>704,'expected_revision'=>1,'action'=>'remove']));
echo 'PASS '.$checks." deck attribute checks: create, max3, primary, conflicts, tombstone, history, rollback and preserved inventories/results.\n";
