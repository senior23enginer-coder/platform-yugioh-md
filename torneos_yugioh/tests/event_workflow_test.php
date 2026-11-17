<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/event_models.php';
$checks=0;$runs=0;
function assertWorkflow(bool $ok,string $why):void{global $checks;if(!$ok)throw new RuntimeException($why);$checks++;}
foreach(ygo_workflow_models() as $model)foreach(range(2,64) as $n){
    if($model==='series' && $n!==2)continue;
    foreach(range(0,4) as $scenario){
        $s=ygo_workflow_begin($model,range(1,$n));$steps=0;$seen=[];
        do{
            assertWorkflow(++$steps<=max(100,2*$n+1),'terminates '.$model.' '.$n);
            $next=ygo_workflow_next($s);$s=$next['state'];
            if($next['complete'])break;
            $busy=[];$results=[];
            foreach($next['matches'] as $i=>$m){
                assertWorkflow($m['a']!==$m['b'],'no self match');
                assertWorkflow(!isset($busy[$m['a']]) && !isset($busy[$m['b']]),'one appearance per stage');$busy[$m['a']]=$busy[$m['b']]=true;
                if($m['phase']==='swiss'){$key=min($m['a'],$m['b']).':'.max($m['a'],$m['b']);assertWorkflow(!isset($seen[$key]),'no Swiss repeats');$seen[$key]=true;}
                if($m['phase']==='groups')assertWorkflow(in_array($m['a'],$s['groups'][$m['group']],true) && in_array($m['b'],$s['groups'][$m['group']],true),'same group');
                $winner=(($i+$scenario+$steps)%3===0 && in_array($m['phase'],['swiss','groups','league'],true))?'E':((($i+$scenario+$steps)%2)?'A':'B');
                $results[]=['winner'=>$winner,'game_difference'=>$winner==='E'?0:($winner==='A'?2:-2)];
            }
            try{ygo_workflow_next($s);throw new LogicException('pending accepted');}catch(InvalidArgumentException $e){$checks++;}
            $s=ygo_workflow_accept($s,$results);
        }while(true);
        assertWorkflow(count(array_unique(array_column($s['byes'],'id')))===count($s['byes']),'no duplicate Swiss BYE');
        if($model!=='groups' && !($model==='gauntlet' && !empty($s['gauntlet_stopped_by'])))assertWorkflow(in_array($s['champion'],$s['roster'],true),'valid champion');
        if($model==='double_elimination'){
            $losses=array_fill_keys($s['roster'],0);foreach($s['history'] as $m)$losses[$m[$m['winner']==='A'?'b':'a']]++;
            foreach($losses as $id=>$loss)assertWorkflow($loss===($id===$s['champion']?$loss:2) && $loss<=2,'exactly two losses for elimination');
            assertWorkflow(count($s['history'])>=2*$n-2 && count($s['history'])<=2*$n-1,'double elimination match count');
        }
        if($model==='series')assertWorkflow(count($s['history'])>=2 && count($s['history'])<=3,'series match count');
        $runs++;
    }
}
foreach([['series',[1,2,3]],['swiss',[1,1]],['groups',[0,1]],['groups',[]],['swiss',range(1,65)]] as [$model,$ids]){try{ygo_workflow_begin($model,$ids);throw new LogicException('invalid roster accepted');}catch(InvalidArgumentException $e){$checks++;}}
$s=ygo_workflow_next(ygo_workflow_begin('series',[1,2]))['state'];
try{ygo_workflow_accept($s,[['winner'=>'E']]);throw new LogicException('draw accepted');}catch(InvalidArgumentException $e){$checks++;}
echo json_encode(['runs'=>$runs,'assertions'=>$checks,'status'=>'PASS'],JSON_PRETTY_PRINT).PHP_EOL;
