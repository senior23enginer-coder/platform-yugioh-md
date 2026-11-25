<?php
declare(strict_types=1);
require __DIR__.'/../tools/reconcile_a03_cids.php';
$native=[];$official=['decks'=>[]];$id=1;
foreach ([660=>36,673=>39,699=>39,705=>16] as $deck=>$count) {
    $rows=[];$entries=[];
    for ($i=0;$i<$count;$i++,$id++) {
        $cid=10000+$id;$name='Card '.$id;
        $rows[]=['id'=>$id,'deck_id'=>$deck,'card_name'=>$name,'section'=>'main','quantity'=>1,'role'=>null,'notes'=>'{"source_identity":"test"}','ygoprodeck_id'=>null,'created_at'=>'2026-01-01 00:00:00'];
        $entries[]=['cid'=>$cid,'card_id'=>[$id],'inventory_name'=>$name,'official_title'=>$name,'retrieved'=>true,'name_exact_normalized'=>true,'source'=>'https://www.db.yugioh-card.com/'.($deck===705?'rushdb':'yugiohdb').'/card_search.action?cid='.$cid.'&ope=2','text_sha256'=>str_repeat('a',64)];
    }
    $native[]=['id'=>$deck,'cards'=>$rows];
    $official['decks'][]=['deck_id'=>$deck,'entries'=>$entries];
}
$plan=buildA03Plan($native,$official);
requireCondition(array_sum(array_map('count',$plan))===130,'EXPECTED_130');
foreach ($plan as $rows) foreach($rows as $item) {
    $notes=json_decode($item['new_notes'],true);
    requireCondition($notes['source_identity']==='test' && $notes['konami_cid']===$item['cid'],'NOTES_PRESERVED');
}
function mustReject(callable $test,string $expected): void {
    try {$test();}catch(RuntimeException $e) {
        requireCondition(str_starts_with($e->getMessage(),$expected),'WRONG_REJECTION_'.$e->getMessage());return;
    }
    throw new RuntimeException('EXPECTED_REJECTION_'.$expected);
}
$bad=$official;$bad['decks'][0]['entries'][0]['card_id'][]=$bad['decks'][0]['entries'][0]['card_id'][0];
mustReject(fn()=>buildA03Plan($native,$bad),'ROW_ID_');
$bad=$official;$bad['decks'][0]['entries'][0]['source']='https://example.com/?cid=10001';
mustReject(fn()=>buildA03Plan($native,$bad),'SOURCE_');
$bad=$native;$bad[0]['cards'][0]['notes']='{"konami_cid":999}';
mustReject(fn()=>buildA03Plan($bad,$official),'ALREADY_IDENTIFIED_');
$bad=$official;$bad['decks'][0]['entries'][0]['inventory_name']='other';
mustReject(fn()=>buildA03Plan($native,$bad),'IDENTITY_');
$bad=$official;array_pop($bad['decks'][0]['entries']);
mustReject(fn()=>buildA03Plan($native,$bad),'INCOMPLETE_');
echo "PASS: 130-row plan, preserved notes, rejected duplicates, bad sources, existing CIDs, mismatched names, incomplete plan.\n";
