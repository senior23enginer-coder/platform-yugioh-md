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
$historical=['decks'=>[['deck_id'=>660,'entries'=>[[
    'card_id'=>[566],'cid'=>11708,'inventory_name'=>'Hermana Fantasma & Conejo Espectral',
    'official_title'=>'Ogro Fantasma & Conejo de las Nieves','text_sha256'=>'64721b0d675f420c6a779b07aac1eec65921fe24b40c36944dea551d1dcd9ba7',
]]]]];
$corrected=applyPurrelyPhotoEvidence($historical);
requireCondition($corrected['decks'][0]['entries'][0]['cid']===14275 &&
    $corrected['decks'][0]['entries'][0]['card_id']===[566] &&
    $corrected['decks'][0]['entries'][0]['name_review_required']===true,'PURR_OVERLAY_RESULT');
$badHistorical=$historical;
$badHistorical['decks'][0]['entries'][0]['card_id'][]=567;
mustReject(fn()=>applyPurrelyPhotoEvidence($badHistorical),'PURRELY_HISTORICAL_EVIDENCE_DRIFT');
$item=['before'=>['deck_id'=>660,'id'=>566,'section'=>'main','quantity'=>2],
    'cid'=>11708,'title'=>'Ogro Fantasma & Conejo de las Nieves','alias_review_required'=>true];
$old=['566'=>['cid'=>11708,'official_title'=>$item['title']]];
requireCondition(a03Eligibility($item,$old)===[false,'PURRELY_ROW_566_REQUIRES_PHOTO_VERIFIED_CID_14275'],'PURR_WRONG_CARD_APPROVED');
$item['cid']=14275;$item['title']='Hermana Fantasma & Cornejo Espectral';
$correct=['566'=>['cid'=>14275,'official_title'=>$item['title']]];
requireCondition(a03Eligibility($item,[])===[false,'ALIAS_APPROVAL_REQUIRED'],'PURR_CORRECT_ALIAS_UNREVIEWED');
requireCondition(a03Eligibility($item,$correct)===[true,null],'PURR_CORRECT_CARD_REJECTED');
$item['before']['quantity']=1;
requireCondition(a03Eligibility($item,$correct)[0]===false,'PURR_WRONG_QUANTITY_APPROVED');
$mixedNoir=['before'=>['deck_id'=>660,'id'=>587,'section'=>'extra','quantity'=>3],
    'cid'=>18008,'title'=>'Noir Expurrely','alias_review_required'=>false];
requireCondition(a03Eligibility($mixedNoir,[])===[false,'PURRELY_ROW_587_REQUIRES_DISTINCT_NOIR_INVENTORY_ROWS'],
    'PURR_MIXED_NOIR_TREATED_AS_EXACT');
requireCondition(a03Eligibility($mixedNoir,['587'=>['cid'=>18008,'official_title'=>'Noir Expurrely']])[0]===false,
    'PURR_MIXED_NOIR_APPROVAL_BYPASS');
echo "PASS: Purrely physical evidence guard and explicit alias approval.\n";
echo "PASS: 130-row plan, preserved notes, rejected duplicates, bad sources, existing CIDs, mismatched names, incomplete plan.\n";
