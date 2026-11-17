<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/event_models.php';
$custom=json_decode(file_get_contents(__DIR__.'/fixtures/custom_xp_22.json'),true);
$checks=0;
function ck($v,$message){global $checks;if(!$v)throw new Exception($message);$checks++;}
ck(count($custom)===22,'CUSTOM_XP 22 exact conditions');
foreach($custom as $row){
 $r=ygo_evaluate_sequence(explode(',',$row['target_value']),3,$custom);
 ck($r['status']===$row['status_value'],'state '.$row['target_value']);
 ck(($r['winner']??'')===($row['result_value']??''),'winner '.$row['target_value']);
 ck($r['score']===$row['score_value'],'score '.$row['target_value']);
}
$all=[[]];$level=[[]];
for($i=1;$i<=5;$i++){$next=[];foreach($level as $v)foreach(['A','B','E'] as $r)$next[]=array_merge($v,[$r]);$all=array_merge($all,$next);$level=$next;}
ck(count($all)===364,'all prefixes 0..5');$cases=[];
foreach([1,3,5] as $bo)foreach([[], $custom] as $rules)foreach($all as $values){$r=ygo_evaluate_sequence($values,$bo,$rules);$cases[]=['values'=>$values,'best_of'=>$bo,'custom'=>(bool)$rules,'result'=>$r];}
ck(ygo_evaluate_sequence(['A'],1)['status']==='FINALIZADA','BO1 closes D1');
ck(ygo_evaluate_sequence(['A','B'],1)['status']==='INVALID','BO1 rejects D2');
ck(ygo_evaluate_sequence(['A'],1,$custom)['status']==='EN CURSO','explicit CUSTOM may extend BO1');
foreach([['A','','B'],['X'],['A','B','A','B','A','B'],['B','B','A']] as $v)ck(ygo_evaluate_sequence($v,3,$custom)['status']==='INVALID','invalid '.json_encode($v));
file_put_contents(($argv[1] ?? sys_get_temp_dir().'/ygo-model-sequences.json'),json_encode(['conditions'=>$custom,'cases'=>$cases],JSON_UNESCAPED_UNICODE));
echo json_encode(['checks'=>$checks,'sequence_cases'=>count($cases),'custom_conditions_unchanged'=>22]).PHP_EOL;
