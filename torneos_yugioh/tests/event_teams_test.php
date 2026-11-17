<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/event_models.php';
$checks=0;function ta(bool $ok,string $s):void{global $checks;if(!$ok)throw new RuntimeException($s);$checks++;}
function tf(callable $f):void{try{$f();throw new RuntimeException('Invalid configuration accepted');}catch(InvalidArgumentException $e){ta(true,'rejected');}}
foreach(['team'=>3,'tag'=>2] as $kind=>$size)foreach(range(2,64) as $n){$rows=[];$id=1;foreach(range(1,$n) as $i){$ids=[];foreach(range(1,$size) as $_)$ids[]=$id++;$rows[]=['name'=>'Equipo '.$i,'decks'=>$ids];}$s=ygo_team_lineups($rows,$kind);ta(count($s['lineups'])===$n,'squad count');ta(count(ygo_team_all_ids($s))===$n*$size,'all decks');$rows[1]['decks'][0]=$rows[0]['decks'][0];tf(fn()=>ygo_team_lineups($rows,$kind));}
$e=fn($v)=>['status'=>'FINALIZADA','winner'=>$v,'score'=>$v==='A'?'2-0':($v==='B'?'0-2':'1-1')];
foreach(['{','null','42','{"a":1}'] as $bad)tf(fn()=>ygo_team_decode_payload($bad));ta(ygo_team_decode_payload('[]')===[],'valid empty list reaches business validation');
foreach(['A','B','E'] as $a)foreach(['A','B','E'] as $b)foreach(['A','B','E'] as $c){$ev=[$e($a),$e($b),$e($c)];$wins=array_count_values([$a,$b,$c]);$expected=($wins['A']??0)>=2?'A':(($wins['B']??0)>=2?'B':'E');ta(ygo_team_aggregate('team',$ev,true)['winner']===$expected,'2 of 3 policy');if($expected==='E')tf(fn()=>ygo_team_aggregate('team',$ev,false));else ta(ygo_team_aggregate('team',$ev,false)['winner']===$expected,'KO winner');}
foreach(['A','B','E'] as $v)ta(ygo_team_aggregate('tag',[$e($v)],true)['winner']===$v,'shared Tag');
tf(fn()=>ygo_team_aggregate('tag',[$e('A'),$e('B')],true));tf(fn()=>ygo_team_aggregate('team',[$e('A'),$e('A')],true));tf(fn()=>ygo_team_aggregate('tag',[['status'=>'EN CURSO','winner'=>'A']],true));
foreach(['_teams_round_robin','_teams_double_round_robin','_teams_single_elimination'] as $model)foreach(range(2,32) as $n){$s=ygo_workflow_begin($model,range(1,$n));$total=0;$seen=[];$steps=0;while(true){$next=ygo_workflow_next($s);$s=$next['state'];if($next['complete'])break;ta(++$steps<100,'termination');$total+=count($s['pending']);$rs=[];foreach($s['pending'] as $m){$key=min($m['a'],$m['b']).':'.max($m['a'],$m['b']);$seen[$key]=($seen[$key]??0)+1;$rs[]=['winner'=>'A','game_difference'=>1];}$s=ygo_workflow_accept($s,$rs);}ta($total===($model==='_teams_single_elimination'?$n-1:$n*($n-1)/2*($model==='_teams_double_round_robin'?2:1)),'exact team fixtures');ta(in_array($s['champion'],$s['roster'],true),'valid winner');}
echo "PASS $checks team policy and fixture assertions.\n";
