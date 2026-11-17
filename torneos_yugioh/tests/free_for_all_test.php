<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/free_for_all.php';
$checks=0;
function fa(bool $yes,string $why):void{global $checks;$checks++;if(!$yes)throw new RuntimeException($why);}
function fr(callable $fn):void{$caught=false;try{$fn();}catch(InvalidArgumentException $e){$caught=true;}fa($caught,'invalid action rejected');}
foreach([[1,2],[1,2,3,4,5],[1,2,2],[1,2,'3']] as $ids)fr(fn()=>ygo_ffa_begin($ids));
$pending=ygo_ffa_begin([1,2,3],8000,false);fa($pending['turn']===0&&$pending['status']==='PENDIENTE','scheduled table does not invent started turns');fr(fn()=>ygo_ffa_apply($pending,['kind'=>'next_turn']));$started=ygo_ffa_apply($pending,['kind'=>'start']);fa($started['turn']===1&&$started['players'][0]['turns']===1,'explicit start');
foreach([3,4] as $n){
    $s=ygo_ffa_begin(range(1,$n));
    foreach(range(1,120) as $turn){fa($s['turn']===$turn,'global turn counter');fa($s['players'][$s['current']]['deck_id']===1+(($turn-1)%$n),'independent cyclic order');$s=ygo_ffa_apply($s,['kind'=>'next_turn']);}
    foreach($s['players'] as $p)fa($p['turns']>=intdiv(120,$n),'each player owns turns');
    foreach(range(2,$n) as $id){$s=ygo_ffa_apply($s,['kind'=>'lp','deck_id'=>$id,'delta'=>-8000]);fa($s['players'][$id-1]['eliminated'],'LP0 eliminates');if($id<$n)fa($s['status']==='EN CURSO','no early winner');}
    fa($s['status']==='FINALIZADA'&&$s['winner']===1,'last survivor wins');fr(fn()=>ygo_ffa_apply($s,['kind'=>'next_turn']));
}
$s=ygo_ffa_begin([10,20,30,40]);$s=ygo_ffa_apply($s,['kind'=>'eliminate','deck_id'=>10,'reason'=>'surrender']);fa($s['current']===1&&$s['turn']===2,'eliminated current player skipped');
$s=ygo_ffa_apply($s,['kind'=>'eliminate','deck_id'=>30,'reason'=>'deck_out']);$s=ygo_ffa_apply($s,['kind'=>'next_turn']);fa($s['current']===3,'eliminated future player skipped');
fr(fn()=>ygo_ffa_apply($s,['kind'=>'lp','deck_id'=>30,'delta'=>8000]));fr(fn()=>ygo_ffa_apply($s,['kind'=>'lp','deck_id'=>40,'delta'=>-9000]));fr(fn()=>ygo_ffa_apply($s,['kind'=>'lp','deck_id'=>40,'delta'=>'-1']));fr(fn()=>ygo_ffa_apply($s,['kind'=>'eliminate','deck_id'=>40,'reason'=>'invented']));
for($i=0;$i<6;$i++)$s=ygo_ffa_apply($s,['kind'=>'next_phase']);fa($s['current']===1&&$s['phase']==='DRAW','six phases advance to next alive player');
$s=ygo_ffa_apply($s,['kind'=>'eliminate','deck_id'=>40,'reason'=>'effect']);fa($s['winner']===20,'effect elimination closes with one survivor');
echo 'PASS '.$checks.' multiplayer turn, LP, elimination and closure assertions'.PHP_EOL;
