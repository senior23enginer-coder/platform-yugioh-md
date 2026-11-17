<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/event_models.php';
$n=0;function ok(bool $b,string $why):void{global $n;if(!$b)throw new RuntimeException($why);$n++;}function rejects(callable $f):void{try{$f();}catch(InvalidArgumentException $e){ok(true,'reject');return;}throw new RuntimeException('Invalid data accepted');}
foreach(['flights','ladder','king_of_the_hill','gauntlet'] as $model)foreach(range(2,64) as $size)foreach(['A','B','mix'] as $variant){
 $s=ygo_workflow_begin($model,range(1,$size));$steps=0;$count=0;
 while(true){$next=ygo_workflow_next($s);$s=$next['state'];if($next['complete'])break;ok(++$steps<150,'finite engine');$seen=[];foreach($next['matches'] as $m){ok($m['a']!==$m['b'] && in_array($m['a'],$s['roster'],true)&&in_array($m['b'],$s['roster'],true),'valid pair');ok(!isset($seen[$m['a']]) && !isset($seen[$m['b']]),'one match per participant per stage');$seen[$m['a']]=$seen[$m['b']]=true;}rejects(fn()=>ygo_workflow_next($s));rejects(fn()=>ygo_workflow_accept($s,array_fill(0,count($s['pending']),['winner'=>'E','game_difference'=>0])));
 $results=[];foreach($next['matches'] as $i=>$m)$results[]=['winner'=>$variant==='mix'?($i%2?'B':'A'):$variant,'game_difference'=>1];$count+=count($results);$s=ygo_workflow_accept($s,$results);
 }
 if(in_array($model,['flights','king_of_the_hill'],true))ok($count===$size-1,'exact N-1');
 if($model==='ladder')ok($steps===2*$size && $s['champion']===ygo_workflow_ladder_order($s)[0],'ladder close by position');
 if($model==='gauntlet' && $variant==='B')ok($count===1 && $s['champion']===null && $s['gauntlet_success']===false,'gauntlet failed no invented champion');
 else ok(in_array($s['champion'],$s['roster'],true),'champion valid');
}
$source=['champion_id'=>2,'runner_up_id'=>1,'ranking'=>[['id'=>2,'pts'=>9,'pg'=>3,'pj'=>5,'pp'=>2],['id'=>1,'pts'=>6,'pg'=>2,'pj'=>2,'pp'=>0],['id'=>3,'pts'=>3,'pg'=>1,'pj'=>1,'pp'=>0]]];
ok(array_column(ygo_qualification_candidates($source,['mode'=>'minimum_points','threshold'=>6]),'id')===[2,1],'minimum actual points');
ok(array_column(ygo_qualification_candidates($source,['mode'=>'best_record']),'id')===[1,3,2],'win rate exact and stable tie');
ok(array_column(ygo_qualification_candidates($source,['mode'=>'runner_up']),'id')===[1],'runner');rejects(fn()=>ygo_qualification_candidates($source,['mode'=>'minimum_points','threshold'=>'1.2']));rejects(fn()=>ygo_qualification_candidates($source,['mode'=>'unknown']));
$group=['grouped'=>true,'ranking'=>[['id'=>1,'group'=>1,'group_position'=>1],['id'=>2,'group'=>1,'group_position'=>2],['id'=>3,'group'=>2,'group_position'=>1]]];ok(array_column(ygo_qualification_candidates($group,['mode'=>'group_position','threshold'=>1]),'id')===[1,3],'per-group position');rejects(fn()=>ygo_qualification_candidates($group,['mode'=>'top_n']));
ok(ygo_admission_quotas([['mode'=>'runner_up'],['mode'=>'minimum_points','value'=>2,'threshold'=>6]],3)===[1,2],'typed quotas');
ok(!function_exists('ygo_card_rules_require') && !function_exists('ygo_card_validate_deck'),'card validators removed');
echo 'PASS '.$n." assertions: engines and qualification; no card validation loaded.\n";
