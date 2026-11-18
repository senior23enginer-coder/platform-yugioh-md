<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/decks/data-package.php';
$base=['deck_id'=>665,'format'=>'tcg_advanced','cards'=>[['cid'=>17768]],'routes'=>[]];
$rule=['version'=>1,'deckId'=>665,'format'=>'tcg_advanced','id'=>'search','actor'=>['cid'=>17768],'guards'=>[],'select'=>[],'costs'=>[],'resolve'=>[],'tags'=>[],'assumptions'=>[]];
$contract=['schema'=>'station-x-calculator/1','deck_id'=>665,'format'=>'tcg_advanced','rules'=>[$rule]];
$raw="```json\n".json_encode($contract)."\n```";
$result=ygo_data_markdown($raw,$base,665);
if(($result['calculator']??null)!==$contract)throw new RuntimeException('Contract was discarded');
foreach(["```json\n{\"schema\":\"station-x-calculator/1\",broken}\n```",str_replace('"deck_id":665','"deck_id":656',$raw),$raw."\n".$raw,str_replace('"cid":17768','"cid":12345',$raw)] as $invalid){
    try{ygo_data_markdown($invalid,$base,665);throw new RuntimeException('Invalid contract accepted');}catch(InvalidArgumentException $expected){}
}
if(str_contains(ygo_data_reader('Test',$raw),'station-x-calculator/1'))throw new RuntimeException('Machine metadata leaked to player reader');
if(isset($base['calculator']))throw new RuntimeException('Input mutated');
echo "PASS calculator contract import, exact deck/format/identity, duplicates and immutable input.\n";
