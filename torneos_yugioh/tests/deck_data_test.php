<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/decks/data-http.php';
function check(bool $value,string $message): void { if(!$value)throw new RuntimeException($message); }
function fails(callable $fn,string $message):void{try{$fn();}catch(InvalidArgumentException|DomainException $e){return;}throw new RuntimeException($message);}
// Historical curated sources may include a nested local image and lazy loading.
$localImageFiles=['DECK_650_ELFNOTE/imagenes/tcg/22356.jpg'=>'fixture'];
check(ygo_data_safe_markdown_images('<img src="imagenes/tcg/22356.jpg" alt="Carta" loading="lazy">',$localImageFiles)==='[Imagen local: imagenes/tcg/22356.jpg]','Local curated image not recognized');
fails(fn()=>ygo_data_safe_markdown_images('<img src="imagenes/tcg/22356.jpg" onerror="alert(1)">',$localImageFiles),'Executable attribute accepted');
fails(fn()=>ygo_data_safe_markdown_images('<img src="imagenes/../tcg/22356.jpg">',$localImageFiles),'Traversal accepted');
fails(fn()=>ygo_data_safe_markdown_images('<img src="https://example.com/carta.jpg">',$localImageFiles),'Remote image accepted');
check(ygo_data_safe_markdown_images('- **reference file:** guias/TEARLAMENTS.md',[])==='- **Archivo de referencia:** guias/TEARLAMENTS.md','Editorial file label was rejected');
check(ygo_data_safe_markdown_images('file:///etc/passwd',[])==='file:///etc/passwd','The editorial normalizer must not rewrite actual file URIs');
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
    if(in_array((int)$id,[671,677],true)) {
        $quality=ygo_data_presentation_quality($r);
        check($quality['routes']===($id==671?12:14),'Pilot route count changed in visual audit');
        check($quality['steps']>0,'No documentary steps to display');
        check(!$quality['visual_complete'],'Unillustrated legacy study wrongly certified as visual complete');
        $fraud=$r;$fraud['presentation_contract']='station-x-visual-v1';
        fails(fn()=>ygo_data_validate($fraud,$inventory),'False complete presentation contract was accepted');
        // New import previews must tell the difference between a documentary
        // route and a verified field with card placements.
        check($quality['full_endboards']<=count($r['routes']),'Invalid endboard audit count');
        // A route with illustrated slots must not become a spatially certified
        // endboard merely because it carries an endboard.variants array.
        $illustrative=$r;
        // Establish a no-board baseline before injecting exactly one illustrated field.
        // Pilot studies now ship their own non-spatial illustrations, so an
        // absolute count of one would regress when legitimate images are added.
        unset($illustrative['routes'][0]['endboard']);
        $qualityWithoutBoard=ygo_data_presentation_quality($illustrative);
        $first=$illustrative['cards'][0];
        $illustrative['routes'][0]['endboard']=['spatial_verified'=>false,'variants'=>[[
            'spatial_verified'=>false,'columns'=>5,'layout_note'=>'Casillas ilustrativas',
            'monster'=>[['name'=>$first['name'],'image'=>$first['image'],'slot'=>1]],
            'spell'=>[],'field'=>[],
        ]]];
        ygo_data_validate($illustrative,$inventory);
        $qualityIllustrated=ygo_data_presentation_quality($illustrative);
        check($qualityIllustrated['illustrative_endboards']===$qualityWithoutBoard['illustrative_endboards']+1,'An illustrative board must be counted as illustrative');
        check($qualityIllustrated['full_endboards']===$qualityWithoutBoard['full_endboards'],'A nonspatial illustration was counted as a verified field');
        check(!$qualityIllustrated['visual_complete'],'An illustrative endboard was falsely certified');
    }
    $files=ygo_data_sources($app,(int)$id,$entry);check(count($files)>0,'Original sources missing '.$id);
    foreach($files as $path=>$bytes)if(str_ends_with($path,'CONTENIDO_CURADO.md')){
        $updated=ygo_data_markdown($bytes,$r,(int)$id);ygo_data_validate($updated,$inventory);$raws++;
        check(count($updated['routes'])>=count($r['routes']),'A route was silently removed');
    }
    // Direct GPT folder ZIP (without stationx-deck.json), preserving the original study.
    if ((int)$id === 671 || (int)$id === 677) {
        $markdownPath=null;$imagePath=null;
        foreach (array_keys($files) as $path) {
            if (str_ends_with($path,'CONTENIDO_CURADO.md')) $markdownPath=$path;
            if ($imagePath===null && preg_match('~/imagenes/[^/]+\.(png|jpe?g|webp|gif)$~i',$path)) $imagePath=$path;
        }
        check($markdownPath!==null && $imagePath!==null,'Pilot GPT source must include Markdown and local images '.$id);
        $relative='imagenes/'.basename($imagePath);
        $legacy=$files;
        $legacy[$markdownPath].="\n\n<img src=\"".$relative."\" width=\"55\" alt=\"Carta de prueba\">\n";
        $candidate=ygo_data_prepare($app,$legacy,$inventory,$r,$files);
        check(count($candidate['routes']) >= count($r['routes']),'Legacy folder lost existing routes');
        check(str_contains($candidate['imported_source_text'],'[Imagen local: '.$relative.']'),'Local image marker missing');
        check(!str_contains($candidate['imported_source_text'],'<img'),'Executable HTML retained in reader');
        $hostile=$legacy;
        $hostile[$markdownPath].="\n<img src=\"https://example.com/evil.png\" onerror=\"alert(1)\">\n";
        fails(fn()=>ygo_data_prepare($app,$hostile,$inventory,$r,$files),'Remote image script accepted');
        $missing=$legacy;
        $missing[$markdownPath].="\n<img src=\"imagenes/9999999999.png\" width=\"55\">\n";
        fails(fn()=>ygo_data_prepare($app,$missing,$inventory,$r,$files),'Missing image accepted');
        check($legacy[$markdownPath] === $files[$markdownPath]."\n\n<img src=\"".$relative."\" width=\"55\" alt=\"Carta de prueba\">\n",'Original Markdown bytes changed unexpectedly');
    }
    $package=['schema'=>'station-x-deck-package/1','deck_id'=>(int)$id,'inventory_sha256'=>$inventory['inventory_sha256'],'record'=>$r];
    $input=['stationx-deck.json'=>ygo_data_json($package)]+$files;$copy=ygo_data_prepare($app,$input,$inventory,$r,$files);
    check(count($copy['routes'])===count($r['routes']),'Structured roundtrip lost routes');
    $export=ygo_data_export_files($app,$inventory+['format'=>'tcg'],$r,$files,[]);$folder=ygo_data_folder($export,(int)$id,$r['name']);
    foreach(array_keys($export) as $name)check(str_starts_with($name,$folder.'/'),'Loose file outside deck folder');
    $metadata=$folder.'/datos/stationx-deck.json';check(isset($export[$metadata]),'Canonical package missing');
    $roundtrip=ygo_data_prepare($app,$export,$inventory,$r,$files);check(count($roundtrip['routes'])===count($r['routes']),'Folder roundtrip lost routes');

    if (in_array((int)$id,[671,677],true)) {
        $portable=json_decode($export[$metadata],true,128,JSON_THROW_ON_ERROR);
        unset($portable['inventory_sha256'],$portable['baseline']);
        $portable['inventory_binding']='deck-slots-v1';
        $portable['content_mode']='structured';
        $portable['record']=$r;
        $portable['inventory']=$cards;
        $candidate=$export;
        $candidate[$metadata]=ygo_data_json($portable);
        $live=$inventory+['format'=>'tcg'];
        $parsed=ygo_data_prepare($app,$candidate,$live,$r,$files);
        check(count($parsed['routes'])===($id==671?12:14),'Portable folder lost Memento/Crystron routes');
        // Real production behavior: older deck-deliveries.json omits both IDs.
        $unknownFormat=$live;
        $unknownFormat['format']='unspecified';
        $fallbackParsed=ygo_data_prepare($app,$candidate,$unknownFormat,$r,$files);
        check(count($fallbackParsed['routes'])===count($r['routes']),'Verified current format was not used for '.$id);
        fails(fn()=>ygo_data_prepare($app,$candidate,$unknownFormat,null,$files),'Accepted unverified format for '.$id);
        $wrongCurrent=$r;$wrongCurrent['deck_id']=$id+1;
        fails(fn()=>ygo_data_prepare($app,$candidate,$unknownFormat,$wrongCurrent,$files),'Accepted format from another deck '.$id);
        $wrongCurrent=$r;$wrongCurrent['format']='rush_duel';
        fails(fn()=>ygo_data_prepare($app,$candidate,$unknownFormat,$wrongCurrent,$files),'Accepted conflicting study format '.$id);
        check(count($parsed['cards'])===39,'Portable folder lost Memento/Crystron cards');
        $mismatch=$portable;$mismatch['inventory'][0]['quantity']++;
        $wrong=$candidate;$wrong[$metadata]=ygo_data_json($mismatch);
        fails(fn()=>ygo_data_prepare($app,$wrong,$live,$r,$files),'Portable wrong inventory accepted');
        $mismatch=$portable;array_pop($mismatch['inventory']);
        $wrong[$metadata]=ygo_data_json($mismatch);
        fails(fn()=>ygo_data_prepare($app,$wrong,$live,$r,$files),'Portable missing card accepted');
        $mismatch=$portable;$mismatch['record']['routes']=array_slice($r['routes'],1);
        $wrong[$metadata]=ygo_data_json($mismatch);
        fails(fn()=>ygo_data_prepare($app,$wrong,$live,$r,$files),'Portable route deletion accepted');
        $mismatch=$portable;$mismatch['record']['format']='rush_duel';
        $wrong[$metadata]=ygo_data_json($mismatch);
        fails(fn()=>ygo_data_prepare($app,$wrong,$live,$r,$files),'Portable cross-format content accepted');
        $mismatch=$portable;$mismatch['package_root']='DECK_999_OTHER';
        $wrong[$metadata]=ygo_data_json($mismatch);
        fails(fn()=>ygo_data_prepare($app,$wrong,$live,$r,$files),'Portable wrong folder accepted');
        $mismatch=$portable;$mismatch['source_document']='absent.md';
        $wrong[$metadata]=ygo_data_json($mismatch);
        fails(fn()=>ygo_data_prepare($app,$wrong,$live,$r,$files),'Portable missing source document accepted');
        $mismatch=$portable;$mismatch['inventory_sha256']='invented-hash';
        $wrong[$metadata]=ygo_data_json($mismatch);
        fails(fn()=>ygo_data_prepare($app,$wrong,$live,$r,$files),'Portable forged hash accepted');
    }

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
// A01-T4/T5 synthetic profile import/export contract regression.
function a01_assert(bool $value,string $label): void { if(!$value)throw new RuntimeException($label); }
function a01_reject(array $record,string $reason): void {
    try{ ygo_data_validate_a01_profile($record); }
    catch(InvalidArgumentException $e){return;}
    throw new RuntimeException('A01 should reject '.$reason);
}
$profile=[
 'Arquetipo'=>'Nexo Umbral',
 'Tipo de estrategia'=>'Combo de Fusión y Cementerio',
 'Estilo breve'=>'Prepara recursos en el Cementerio y desarrolla Fusiones para mantener presión.',
 'Resumen para ficha'=>'Inicia con cartas válidas, convierte recursos en materiales de Fusión y obtiene ventaja condicional.',
 'Cómo se juega'=>'Primero conserva recursos; segundo verifica amenazas y materiales. En juego tardío recupera cartas solo si se cumplen las condiciones acreditadas.',
 'Qué vigilar'=>'Pérdida de recursos e interrupciones de las Invocaciones.',
];
$record=[
 'schema'=>'station-x-curated-v62','deck_id'=>13579,'format'=>'tcg_advanced',
 'name'=>'Nexo Umbral',
 'a01_profile_contract'=>'station-x-a01-profile/1','profile'=>$profile,
 'cards'=>[
   ['id'=>'13579:CARD:1','cid'=>1111,'name'=>'Uno','fields'=>[],
    'slots'=>[['card_id'=>1,'section'=>'main','quantity'=>1]],
    'sources'=>[],'synergy'=>['labels'=>[]]],
   ['id'=>'13579:CARD:2','cid'=>2222,'name'=>'Dos','fields'=>[],
    'slots'=>[['card_id'=>2,'section'=>'extra','quantity'=>1]],
    'sources'=>[],'synergy'=>['labels'=>[]]],
   ['id'=>'13579:CARD:3','cid'=>3333,'name'=>'Tres','fields'=>[],
    'slots'=>[['card_id'=>3,'section'=>'side','quantity'=>1]],
    'sources'=>[],'synergy'=>['labels'=>[]]],
 ],
 'routes'=>[],'skills'=>[],'engine_maps'=>[],'decisions'=>[],'practice'=>[]
];
$inventory=[
 'deck'=>['id'=>13579,'name'=>'Nexo Umbral'],
 'cards'=>[
  ['id'=>1,'section'=>'main','quantity'=>1],
  ['id'=>2,'section'=>'extra','quantity'=>1],
  ['id'=>3,'section'=>'side','quantity'=>1]
 ]
];
ygo_data_validate_a01_profile($record);
ygo_data_validate($record,$inventory);
$encoded=ygo_data_json($record);
$again=json_decode($encoded,true,128,JSON_THROW_ON_ERROR);
a01_assert($again['profile']===$profile,'JSON roundtrip lost six profile fields');
a01_assert(($again['a01_profile_contract']??null)==='station-x-a01-profile/1','Contract marker lost');
ygo_data_validate($again,$inventory);
$bad=$record; unset($bad['profile']['Resumen para ficha']); a01_reject($bad,'missing field');
$bad=$record; $bad['profile']['Estilo breve']=str_repeat('x',191);a01_reject($bad,'190 character bound');
$bad=$record; $bad['profile']['Resumen para ficha']=str_repeat('x',251);a01_reject($bad,'250 character bound');
$bad=$record; $bad['profile']['Tipo de estrategia']='Consultar el plan de juego';a01_reject($bad,'placeholder');
$bad=$record; $bad['profile']['Arquetipo']='Nexo Umbral — estudio individual';a01_reject($bad,'dirty archetype');
$bad=$record; $bad['profile']['Cómo se juega']='Poco';a01_reject($bad,'gameplan');
$bad=$record; $bad['profile']['Qué vigilar']='N/A';a01_reject($bad,'risks');
$bad=$record; $bad['a01_profile_contract']='unrecognized';a01_reject($bad,'unknown contract');
$legacy=$record;unset($legacy['a01_profile_contract'],$legacy['profile']['Estilo breve']);
ygo_data_validate_a01_profile($legacy); // Historical data remains readable, NOT certified T4/T5.
a01_assert(!isset($legacy['a01_profile_contract']),'Legacy data must not receive automatic A01 certification');


// Player-facing guide contract: representative cards need reasons and four useful plans.
$guideRecord=$record;
$guideRecord['profile']['Cómo se juega']=trim(str_repeat('Primero prepara cartas y recursos desde una mano real. Si el oponente interviene, revisa condiciones y materiales antes de continuar. ',5));
$guideRecord['player_guide']=[
 'schema'=>'station-x-player-guide/1',
 'featured_cards'=>[[
  'cid'=>1111,'role'=>'Iniciadora para obtener recursos del motor',
  'reason'=>'Esta carta abre las opciones de desarrollo de las piezas presentes en la construcción.',
  'when_to_use'=>'Cuando la mano contiene materiales y las ventanas relevantes están disponibles.',
  'related_cids'=>[2222]
 ]],
 'phases'=>array_map(static fn($id)=>[
  'id'=>$id,'title'=>'Plan de juego para '.$id,
  'plan'=>'Confirma primero los recursos que existen en la mano, el Cementerio y el campo. Después desarrolla únicamente las acciones habilitadas y conserva las piezas necesarias para responder al siguiente turno.',
  'preconditions'=>'Necesita cartas reales con zonas y cantidades comprobadas.',
  'risks'=>'Un efecto negado impide suponer una línea completada o materiales adicionales.',
  'related_route_ids'=>[]
 ],['first','second','mid','late'])
];
ygo_data_validate_player_guide($guideRecord);
ygo_data_validate($guideRecord,$inventory);
$bad=$guideRecord;$bad['player_guide']['featured_cards'][0]['cid']=99999;
try {ygo_data_validate_player_guide($bad);throw new RuntimeException('Unknown featured CID was accepted');}
catch(InvalidArgumentException $e){}
$bad=$guideRecord;$bad['player_guide']['phases'][0]['plan'].=' CID 19364';
try {ygo_data_validate_player_guide($bad);throw new RuntimeException('Audit leakage was accepted');}
catch(InvalidArgumentException $e){}
$bad=$guideRecord;$bad['player_guide']['phases'][0]['related_route_ids'][]='13579:A09:C99';
try {ygo_data_validate_player_guide($bad);throw new RuntimeException('Unknown route was accepted');}
catch(InvalidArgumentException $e){}

echo ygo_data_json(['ok'=>true,'structured_roundtrips'=>$count,'markdown_deliveries'=>$raws,'isolation'=>'passed','archive_limits'=>'passed','concurrency'=>'passed'])."\n";
