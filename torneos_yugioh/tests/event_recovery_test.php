<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/event_models.php';
$checks=0;$cases=0;
function recoveryAssert(bool $ok,string $why):void{global $checks;if(!$ok)throw new RuntimeException($why);$checks++;}
foreach(ygo_workflow_models() as $model)foreach([2,3,5,10,17,32,64] as $n){
    if($model==='series' && $n!==2)continue;
    foreach([0,1,2] as $scenario){
        $s=ygo_workflow_begin($model,range(1,$n));$s['schedule']=['start'=>'2026-10-26','pattern'=>'consecutive','matches_per_day'=>1];
        $nextId=1;$steps=0;
        while(++$steps<=max(100,2*$n+1)){
            $before=$s;$next=ygo_workflow_next($s);$s=$next['state'];if($next['complete'])break;
            foreach($s['pending'] as &$m)$m['match_id']=$nextId++;unset($m);
            if($s['round']>1){
                $plan=ygo_workflow_recovery_plan($s);$restored=$plan['state'];
                recoveryAssert($restored['round']===$s['round']-1,'restore previous round');
                recoveryAssert(count($restored['pending'])>0,'original matches restored');
                $accepted=ygo_workflow_accept($restored,array_slice($before['history'],-count($restored['pending'])));
                $again=ygo_workflow_next($accepted)['state'];
                foreach($again['pending'] as $i=>&$m)$m['match_id']=$s['pending'][$i]['match_id'];unset($m);
                recoveryAssert($again==$s,'exact deterministic replay including BYEs and seeds');
                recoveryAssert($plan['remove_ids']===array_column($s['pending'],'match_id'),'remove only new stage');$cases++;
            }
            $results=[];foreach($s['pending'] as $i=>$m){$winner=($scenario+$steps+$i)%2?'A':'B';if($scenario===2 && in_array($m['phase'],['swiss','groups','league'],true))$winner='E';$results[]=['winner'=>$winner,'game_difference'=>$winner==='E'?0:($winner==='A'?2:-2)];}
            $s=ygo_workflow_accept($s,$results);
        }
        recoveryAssert($steps<=max(100,2*$n+1),'finite simulation');
    }
}
echo "PASS $checks recovery assertions; $cases exact stage replays.\n";
