<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/decks/data-http.php';

$sourceRoot=$argv[1]??'';
if($sourceRoot===''||!is_dir($sourceRoot))throw new RuntimeException('Provide the checked-out GPT repo root.');
$app=dirname(__DIR__);
$state=ygo_data_read($app.'/assets/editorial/strategy-content-state.json');
$pilot=[
    671=>['folder'=>'DECK_NUEVO_12_MEMENTOTLAN_DEMONIHERRERO','routes'=>12,'steps'=>69,'main'=>41,'extra'=>15,'side'=>14],
    677=>['folder'=>'DECK_NUEVO_13_CRISTRON_HORUS','routes'=>14,'steps'=>60,'main'=>40,'extra'=>15,'side'=>15],
];
$reports=[];
foreach($pilot as $id=>$expected){
    $root=realpath($sourceRoot.'/01_INFORMACION_PARA_IMPORTAR/decks/'.$expected['folder']);
    if(!$root||!is_dir($root))throw new RuntimeException('Missing real GPT deck directory '.$id);
    $entry=$state['checked_studies'][(string)$id]??null;
    if(!$entry)throw new RuntimeException('Deck not registered in Station X '.$id);
    $current=ygo_data_read(ygo_data_local($app,$entry['path']));
    $cards=[];
    foreach($current['cards'] as $card)foreach($card['slots'] as $slot){
        $cards[]=[
            'id'=>$slot['card_id'],'deck_id'=>$id,'card_name'=>$card['name'],
            'section'=>$slot['section'],'quantity'=>$slot['quantity'],'ygoprodeck_id'=>null,
        ];
    }
    $inventory=['deck'=>['id'=>$id,'name'=>$current['name']],'cards'=>$cards,
        'format'=>'unspecified', // Reproduce the historical catalog fallback in production.
        'inventory_sha256'=>'isolated-fixture-'.$id];
    $tmp=tempnam(sys_get_temp_dir(),'gpt-real-');
    if($tmp===false)throw new RuntimeException('Could not allocate temporary ZIP');
    try{
        $zip=new ZipArchive();
        if($zip->open($tmp,ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Cannot create test ZIP');
        $filesAdded=0;
        $iterator=new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
        foreach($iterator as $file){
            if(!$file->isFile())continue;
            $relative=substr($file->getPathname(),strlen($root)+1);
            if(str_contains($relative,'..')||str_contains($relative,'\\'))throw new RuntimeException('Unsafe file name');
            $bytes=file_get_contents($file->getPathname());
            if($bytes===false)throw new RuntimeException('Failed reading GPT source');
            $zip->addFromString($expected['folder'].'/'.$relative,$bytes);
            $filesAdded++;
        }
        if(!$zip->close())throw new RuntimeException('Cannot close test ZIP');
        $files=ygo_data_normalize_files(ygo_data_archive($tmp));
        $sources=ygo_data_sources($app,$id,$entry);
        $imported=ygo_data_prepare($app,$files,$inventory,$current,$sources);
        $quality=ygo_data_presentation_quality($imported);
        if(count($imported['routes'])!==$expected['routes'])throw new RuntimeException('Routes lost for deck '.$id);
        if(count($imported['cards'])!==count($current['cards']))throw new RuntimeException('Card identities changed for deck '.$id);
        $steps=0;$illustrated=0;
        foreach($imported['routes'] as $route){
            foreach($route['steps'] as $step){
                $steps++;
                if(!empty($step['photos']))$illustrated++;
            }
        }
        if($steps!==$expected['steps']||$illustrated!==$expected['steps'])
            throw new RuntimeException('Missing A09 step images for deck '.$id);
        if(($quality['illustrative_endboards']??0)!==$expected['routes']
            ||$quality['full_endboards']!==0||$quality['visual_complete'])
            throw new RuntimeException('Illustrative spatial zones were falsely certified for deck '.$id);
        // A second parse of the same ZIP must preserve exactly the route/card count.
        $again=ygo_data_prepare($app,$files,$inventory,$imported,$sources);
        if(count($again['routes'])!==count($imported['routes'])
            ||count($again['cards'])!==count($imported['cards']))
            throw new RuntimeException('Reimportation changed inventory or routes '.$id);
        $zones=['main'=>0,'extra'=>0,'side'=>0];
        foreach($inventory['cards'] as $card)$zones[$card['section']]+=intval($card['quantity']);
        foreach($zones as $zone=>$number)if($number!==$expected[$zone])
            throw new RuntimeException('Zone count drift '.$id.' '.$zone);
        $reports[]=['deck_id'=>$id,'files'=>$filesAdded,'routes'=>$expected['routes'],
            'steps'=>$steps,'illustrated_steps'=>$illustrated,'illustrative_endboards'=>$quality['illustrative_endboards'],
            'exact_endboards'=>$quality['full_endboards'],'preview_ready'=>true,
            'reimport_consistent'=>true];
    }finally{unlink($tmp);}
}
echo json_encode(['ok'=>true,'type'=>'isolated-real-GPT-ZIP-into-Station-X-importer',
    'database_write'=>false,'decks'=>$reports],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
