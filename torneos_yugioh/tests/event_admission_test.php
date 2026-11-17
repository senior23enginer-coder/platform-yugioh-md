<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/event_models.php';
$cases=[];$checks=0;
for($n=1;$n<=10;$n++)for($capacity=2;$capacity<=64;$capacity++){
 $parts=array_fill(0,$n,intdiv(100,$n));for($i=0;$i<100%$n;$i++)$parts[$i]++;
 $rows=array_map(static fn($v)=>['mode'=>'percentage','value'=>$v],$parts);$q=ygo_admission_quotas($rows,$capacity);
 if(array_sum($q)!==$capacity || count($q)!==$n)throw new Exception('Invalid quota total');$checks++;
 foreach($q as $i=>$quota){if(!is_int($quota) || $quota<intdiv($capacity*$parts[$i],100) || $quota>(int)ceil($capacity*$parts[$i]/100))throw new Exception('Invalid rounding');$checks++;}
 $cases[]=['rows'=>$rows,'capacity'=>$capacity,'quotas'=>$q,'valid'=>true];
}
foreach([[[],0],[[['mode'=>'percentage','value'=>99]],8],[[['mode'=>'percentage','value'=>50],['mode'=>'top_n','value'=>2]],8],[[['mode'=>'top_n','value'=>0]],0],[[['mode'=>'top_n','value'=>2.5]],0],[[['mode'=>'top_n','value'=>3]],2],[[['mode'=>'top_n','value'=>65]],0]] as [$rows,$capacity]){
 try{ygo_admission_quotas($rows,$capacity);throw new RuntimeException('Invalid input accepted');}catch(InvalidArgumentException $e){$checks++;$cases[]=['rows'=>$rows,'capacity'=>$capacity,'valid'=>false];}
}
if(ygo_admission_quotas([['mode'=>'percentage','value'=>50],['mode'=>'percentage','value'=>50]],5)!==[3,2])throw new Exception('Tie remainder order');$checks++;
file_put_contents(sys_get_temp_dir().'/ygo-admission-cases.json',json_encode($cases));echo 'PASS '.$checks.' quota assertions; '.count($cases).' parity cases.'.PHP_EOL;
