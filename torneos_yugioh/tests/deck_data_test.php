<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/decks/data-http.php';
function check(bool $value,string $message): void { if(!$value)throw new RuntimeException($message); }
function fails(callable $fn,string $message):void{try{$fn();}catch(InvalidArgumentException|DomainException $e){return;}throw new RuntimeException($message);}
$app=dirname(__DIR__);$state=ygo_data_read($app.'/assets/editorial/strategy-content-state.json');$count=0;$raws=0;
// Canonical pilot identities: the prompt generator must not replace these with invented IDs.
foreach ([671=>['main'=>41,'extra'=>15,'side'=>14,'tech'=>0,'cards'=>39,'routes'=>12],677=>['main'=>40,'extra'=>15,'side'=>15,'tech'=>0,'cards'=>39,'routes'=>14]] as $pilotId=>$expected) {
    check(isset($state['checked_studies'][(string)$pilotId]),'Missing canonical study for '.$pilotId);
    $pilot=ygo_data_read(ygo_data_local($app,$state['checked_studies'][(string)$pilotId]['path']));
    check((int)$pilot['deck_id']===$pilotId,'Incorrect pilot deck ID');
    check(($pilot['schema']??'')==='station-x-curated-v62','Incorrect pilot record schema');
    check(($pilot['format']??'')==='tcg_advanced','Incorrect pilot format');
    check(count($pilot['cards'])===$expected['cards'],'Pilot card identity drift '.$pilotId);
    check(count($pilot['routes'])===$expected['routes'],'Pilot route count drift '.$pilotId);
    $zones=['main'=>0,'extra'=>0,'side'=>0,'tech'=>0];
    foreach($pilot['cards'] as $card)foreach($card['slots'] as $slot){
        $zone=$slot['section'];
        check(isset($zones[$zone]),'Unknown zone in pilot '.$pilotId);
        $zones[$zone]+=(int)$slot['quantity'];
    }
    check($zones===array_intersect_key($expected,$zones),'Pilot physical inventory drift '.$pilotId);
    $sourceIds=array_column($pilot['routes'],'id');
    check(count($sourceIds)===count(array_unique($sourceIds)),'Duplicate pilot route ID '.$pilotId);
    // No calculator rules means these routes remain documentary, not engine-certified.
    if(!isset($pilot['calculator']))check(!isset($pilot['calculator']['rules']),'Pilot calculator claim not justified');
}
foreach($state['checked_studies'] as $id=>$entry){
    $r=ygo_data_read(ygo_data_local($app,$entry['path']));$cards=[];
    foreach($r['cards'] as $card)foreach($card['slots'] as $s)$cards[]=['id'=>$s['card_id'],'deck_id'=>(int)$id,'card_name'=>$card['name'],'section'=>$s['section'],'quantity'=>$s['quantity'],'ygoprodeck_id'=>null];
    $inventory=['deck'=>['id'=>(int)$id,'name'=>$r['name']],'cards'=>$cards,'inventory_sha256'=>'fixture-'.$id];
    ygo_data_validate($r,$inventory);$count++;
    $files=ygo_data_sources($app,(int)$id,$entry);check(count($files)>0,'Original sources missing '.$id);
    foreach($files as $path=>$bytes)if(str_ends_with($path,'CONTENIDO_CURADO.md')){
        $updated=ygo_data_markdown($bytes,$r,(int)$id);ygo_data_validate($updated,$inventory);$raws++;
        check(count($updated['routes'])>=count($r['routes']),'A route was silently removed');
    }
    $package=['schema'=>'station-x-deck-package/1','deck_id'=>(int)$id,'inventory_sha256'=>$inventory['inventory_sha256'],'record'=>$r];
    $input=['stationx-deck.json'=>ygo_data_json($package)]+$files;$copy=ygo_data_prepare($app,$input,$inventory,$r,$files);
    check(count($copy['routes'])===count($r['routes']),'Structured roundtrip lost routes');
    $export=ygo_data_export_files($app,$inventory+['format'=>'tcg'],$r,$files,[]);$folder=ygo_data_folder($export,(int)$id,$r['name']);
    foreach(array_keys($export) as $name)check(str_starts_with($name,$folder.'/'),'Loose file outside deck folder');
    $metadata=$folder.'/datos/stationx-deck.json';check(isset($export[$metadata]),'Canonical package missing');
    $roundtrip=ygo_data_prepare($app,$export,$inventory,$r,$files);check(count($roundtrip['routes'])===count($r['routes']),'Folder roundtrip lost routes');
    $structured=$export;$envelope=json_decode($structured[$metadata],true);$envelope['record']['profile']['Estilo de juego']='Updated structured style';$structured[$metadata]=ygo_data_json($envelope);
    check(ygo_data_prepare($app,$structured,$inventory,$r,$files)['profile']['Estilo de juego']==='Updated structured style','Automatic JSON changes ignored');
    $markdown=$export;$envelope=json_decode($markdown[$metadata],true);$markdown[$envelope['source_document']].="\n\nNew documentary paragraph.\n";
    check(str_contains(ygo_data_prepare($app,$markdown,$inventory,$r,$files)['imported_source_text'],'New documentary paragraph.'),'Automatic Markdown changes ignored');
    $both=$structured;$both[$envelope['source_document']]=$markdown[$envelope['source_document']];fails(fn()=>ygo_data_prepare($app,$both,$inventory,$r,$files),'Conflicting changes silently selected');
    $foreign=$export;$foreign['DECK_999_OTHER/other.md']='other';fails(fn()=>ygo_data_prepare($app,$foreign,$inventory,$r,$files),'Multiple deck folders accepted');
    check(isset(ygo_data_normalize_files(['01_INFORMACION_PARA_IMPORTAR/decks/'.$folder.'/test.md'=>'test'])[$folder.'/test.md']),'Wrapped paths not normalized');
    $bad=$package;$bad['deck_id']=(int)$id+1;fails(fn()=>ygo_data_prepare($app,['stationx-deck.json'=>ygo_data_json($bad)],$inventory,$r,$files),'Cross-deck accepted');
    $bad=$package;$bad['record']['cards'][0]['slots'][0]['quantity']++;fails(fn()=>ygo_data_prepare($app,['stationx-deck.json'=>ygo_data_json($bad)],$inventory,$r,$files),'Inventory change accepted');
    if((int)$id===665){$fixture=$r;$inv=$inventory;$sourceFiles=$input;}
}
foreach(['../oops.php','C:/oops','folder/../../oops','/tmp/test'] as $path)fails(fn()=>ygo_data_path($path),'Traversal accepted');
$zipTemp=tempnam(sys_get_temp_dir(),'ygo-hostile-');$zip=new ZipArchive();$zip->open($zipTemp,ZipArchive::OVERWRITE);$zip->addFromString('../outside.php','bad');$zip->close();fails(fn()=>ygo_data_archive($zipTemp),'Hostile archive accepted');unlink($zipTemp);
$zipTemp=tempnam(sys_get_temp_dir(),'ygo-hostile-');$zip=new ZipArchive();$zip->open($zipTemp,ZipArchive::OVERWRITE);$zip->addFromString('large.txt',str_repeat('a',2*1024*1024));$zip->close();fails(fn()=>ygo_data_archive($zipTemp),'Compression bomb accepted');unlink($zipTemp);
$bad=$fixture;$bad['routes'][0]['title']='<script>alert(1)</script>';fails(fn()=>ygo_data_validate($bad,$inv),'Script accepted');
$scratch=sys_get_temp_dir().'/ygo-data-'.bin2hex(random_bytes(8));mkdir($scratch.'/assets/editorial',0755,true);
try{
    file_put_contents($scratch.'/assets/editorial/strategy-content-state.json',ygo_data_json(['checked_studies'=>['665'=>['path'=>'old'],'650'=>['path'=>'unrelated']]]));
    $expected=hash('sha256',ygo_data_json(['path'=>'old']));$entry=ygo_data_publish($scratch,665,$fixture,$sourceFiles,$expected);
    $after=ygo_data_read($scratch.'/assets/editorial/strategy-content-state.json');check($after['checked_studies']['650']['path']==='unrelated','Other deck overwritten');
    check(is_file($scratch.'/'.substr($entry['path'],2)),'Published data missing');check($entry['routes']===16,'Wrong routes');
    fails(fn()=>ygo_data_publish($scratch,665,$fixture,$sourceFiles,$expected),'Stale preview accepted');
    check(str_contains(ygo_data_reader('test','<script>bad</script>'),'&lt;script&gt;'),'Reader HTML not escaped');
}finally{
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scratch,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($iterator as $f){if($f->isDir())rmdir($f->getPathname());else unlink($f->getPathname());}rmdir($scratch);
}
echo ygo_data_json(['ok'=>true,'structured_roundtrips'=>$count,'markdown_deliveries'=>$raws,'isolation'=>'passed','archive_limits'=>'passed','concurrency'=>'passed'])."\n";
