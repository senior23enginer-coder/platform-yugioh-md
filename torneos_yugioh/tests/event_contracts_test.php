<?php
require dirname(__DIR__).'/includes/event_models.php';
$n=0;function ec(bool $ok,string $label):void{global $n;$n++;if(!$ok)throw new RuntimeException($label);}
$preset=['label'=>'Clasificatorio tres enfrentamientos','type'=>'qualifier','execution_model'=>'swiss'];
$config=['prompt_template'=>'clasificatorio_divisiones_3_duelos','type'=>'qualifier','execution_model'=>'swiss','phase_structure'=>'NONE','advancement_rule'=>'SOURCE_ADMISSION'];
ec(ygo_event_contract_errors($config,$preset)===[],'compatible Swiss recipe');
foreach([['execution_model'=>'swiss_top_cut'],['phase_structure'=>'TOP_CUT'],['type'=>'cup']] as $bad)ec(count(ygo_event_contract_errors(array_replace($config,$bad),$preset))>0,'incompatible recipe rejected');
$s=ygo_workflow_begin('series',[1,2],['series_matches'=>5,'series_wins'=>3]);$count=0;
do{$next=ygo_workflow_next($s);$s=$next['state'];if($next['complete'])break;$count+=count($next['matches']);$s=ygo_workflow_accept($s,[['winner'=>'A','game_difference'=>2]]);}while(true);
ec($count===3,'five match series needs three match wins, not BO3 duels');
$s=ygo_workflow_begin('groups_knockout',range(1,8),['group_qualifiers'=>1]);$count=0;
do{$next=ygo_workflow_next($s);$s=$next['state'];if($next['complete'])break;$count+=count($next['matches']);$s=ygo_workflow_accept($s,array_fill(0,count($next['matches']),['winner'=>'A','game_difference'=>2]));}while(true);
ec($count===13,'two groups RR6 each plus final1 with configured one qualifier');
foreach([['swiss_rounds'=>0],['swiss_rounds'=>10],['points'=>['win'=>99]],['group_max_size'=>2,'group_qualifiers'=>3]] as $bad){$rejected=false;try{ygo_workflow_begin('swiss',range(1,8),$bad);}catch(InvalidArgumentException $e){$rejected=true;}ec($rejected,'invalid operational parameter rejected');}
ec(ygo_team_kind(['prompt_template'=>'all_star_event','participants_per_side'=>1])==='','All-Star does not imply3v3');
ec(ygo_model_diagnostics(['prompt_template'=>'pegasus_challenge','execution_model'=>'swiss','event_ruleset'=>'tcg_advanced'])===[],'Pegasus individual Swiss now defined by user');
ec(ygo_model_diagnostics(['prompt_template'=>'pegasus_challenge','execution_model'=>'swiss','event_ruleset'=>'goat'])!==[],'Pegasus incompatible format rejected');
echo 'PASS '.$n.' structural contract and configurable execution assertions'.PHP_EOL;
