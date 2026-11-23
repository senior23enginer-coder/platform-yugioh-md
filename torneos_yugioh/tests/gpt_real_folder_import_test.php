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
        // A01-T5: use the REAL assigned Memento package with Station X's native
        // validator, immutable publisher, exporter and reimporter on a scratch root.
        // No production connection, live database or other deck is ever written.
        $a01T5=null;
        if($id===671){
            $expectedProfile=['Arquetipo','Tipo de estrategia','Estilo breve','Resumen para ficha','Cómo se juega','Qué vigilar'];
            if(($imported['a01_profile_contract']??null)!=='station-x-a01-profile/1')
                throw new RuntimeException('A01-T5 Memento profile contract missing');
            ygo_data_validate_a01_profile($imported);
            ygo_data_validate_player_guide($imported);
            $player=$imported['player_guide'];
            if(($player['schema']??null)!=='station-x-player-guide/1'
                ||count($player['featured_cards']??[])!==4
                ||count($player['phases']??[])!==4)
                throw new RuntimeException('A01-T5 expected 4 grounded featured cards and 4 phases');
            $packagePath=$expected['folder'].'/datos/stationx-deck.json';
            $sourcePath=$expected['folder'].'/'.$expected['folder'].'_CONTENIDO_CURADO.md';
            if(!isset($files[$packagePath],$files[$sourcePath]))
                throw new RuntimeException('A01-T5 canonical original missing from real package');
            $envelope=json_decode($files[$packagePath],true,128,JSON_THROW_ON_ERROR);
            $md=$files[$sourcePath];
            foreach($expectedProfile as $field){
                $value=$imported['profile'][$field]??null;
                if(!is_string($value)||trim($value)==='')
                    throw new RuntimeException('A01-T5 empty canonical field '.$field);
                $needle='- **'.$field.':** '.$value;
                if(substr_count($md,$needle)!==1)
                    throw new RuntimeException('A01-T5 Markdown-JSON parity failed: '.$field);
            }
            if($imported['profile']['Cómo se juega']===$current['profile']['Cómo se juega'])
                throw new RuntimeException('A01-T5 is still showing old editorial plan');
            if(preg_match('/Dictamen editorial|PARCIAL_AVANZADO|consultar el plan|CID[[:space:]]*[0-9]+|C0[1-9]/iu',
                implode(' ',array_values(array_intersect_key($imported['profile'],array_flip($expectedProfile))))))
                throw new RuntimeException('A01-T5 audit leaked into player profile');

            $negativeCount=0;
            $reject=static function(callable $modify,string $reason)
                use($envelope,$packagePath,$files,$app,$inventory,$current,$sources,&$negativeCount):void{
                $candidate=$envelope;
                $modify($candidate);
                $corrupted=$files;
                $corrupted[$packagePath]=ygo_data_json($candidate);
                try {ygo_data_prepare($app,$corrupted,$inventory,$current,$sources);}
                catch(InvalidArgumentException|DomainException $e){$negativeCount++;return;}
                throw new RuntimeException('A01-T5 failed to reject: '.$reason);
            };
            $reject(static function(array &$x):void{unset($x['record']['profile']['Resumen para ficha']);},'missing profile field');
            $reject(static function(array &$x):void{$x['record']['profile']['Estilo breve']=str_repeat('x',191);},'long style');
            $reject(static function(array &$x):void{$x['record']['profile']['Tipo de estrategia']='Consultar el plan';},'placeholder');
            $reject(static function(array &$x):void{$x['record']['a01_profile_contract']='invalid';},'unknown profile marker');
            $reject(static function(array &$x):void{$x['record']['player_guide']['featured_cards'][0]['cid']=99999999;},'foreign featured card');
            $reject(static function(array &$x):void{$x['record']['player_guide']['phases'][0]['related_route_ids'][]='671:A09:C99';},'foreign route');
            $reject(static function(array &$x):void{$x['inventory'][0]['quantity']++;},'changed physical inventory');
            $reject(static function(array &$x):void{$x['record']['deck_id']=677;},'other deck identity');
            if($negativeCount!==8)throw new RuntimeException('A01-T5 negative coverage lost');

            $scratch=sys_get_temp_dir().'/stationx-a01-t5-671-'.bin2hex(random_bytes(8));
            if(!mkdir($scratch.'/assets/editorial',0700,true))
                throw new RuntimeException('A01-T5 scratch directory cannot be created');
            try {
                $registry=$scratch.'/assets/editorial/strategy-content-state.json';
                $originalRegistry=ygo_data_json($state);
                if(file_put_contents($registry,$originalRegistry)===false)
                    throw new RuntimeException('A01-T5 scratch registry not created');
                $expectedEntryHash=hash('sha256',ygo_data_json($entry));
                $published=ygo_data_publish($scratch,671,$imported,$files,$expectedEntryHash);
                if(($published['cards']??-1)!==39 || ($published['routes']??-1)!==12)
                    throw new RuntimeException('A01-T5 wrong published card or route count');
                [$activatedEntry,$activated]=ygo_data_current($scratch,671);
                if(($activated['profile']??null)!==$imported['profile']
                    ||($activated['player_guide']??null)!==$player)
                    throw new RuntimeException('A01-T5 published source differs from imported A01');
                $importedAt=$activated['folder_delivery']['imported_at']??null;
                if(!is_string($importedAt)||strtotime($importedAt)===false)
                    throw new RuntimeException('A01-T5 native import timestamp not recorded');
                if(isset($activated['folder_delivery']['reader']))
                    throw new RuntimeException('A01-T5 resurrected external audit HTML');
                if(is_file(dirname(ygo_data_local($scratch,$activatedEntry['path'])).'/guide.html'))
                    throw new RuntimeException('A01-T5 exposed extra HTML guide');
                $afterState=ygo_data_read($registry);
                foreach($state['checked_studies'] as $otherId=>$otherEntry){
                    if((int)$otherId===671)continue;
                    if(($afterState['checked_studies'][(string)$otherId]??null)!==$otherEntry)
                        throw new RuntimeException('A01-T5 modified another deck '.$otherId);
                }
                $photos=0;
                foreach($activated['cards'] as $card){
                    if(!empty($card['image'])){
                        if(!ygo_data_local($scratch,$card['image']))
                            throw new RuntimeException('A01-T5 published card image missing '.$card['name']);
                        $photos++;
                    }
                }
                if($photos<39)throw new RuntimeException('A01-T5 missing published card photographs');
                $export=ygo_data_export_files($scratch,$inventory,$activated,$files,[]);
                $exportedEnvelope=json_decode($export[$packagePath]??'',true,128,JSON_THROW_ON_ERROR);
                if(($exportedEnvelope['record']['profile']??null)!==$imported['profile']
                    ||($exportedEnvelope['record']['player_guide']??null)!==$player)
                    throw new RuntimeException('A01-T5 export lost profile or player guide');
                if(($exportedEnvelope['deck_id']??0)!==671
                    ||($exportedEnvelope['record']['format']??'')!=='tcg_advanced'
                    ||count($exportedEnvelope['record']['routes']??[])!==12)
                    throw new RuntimeException('A01-T5 export altered identity, format or routes');
                $reimport=ygo_data_prepare($scratch,$export,$inventory,$activated,$files);
                if(($reimport['profile']??null)!==$imported['profile']
                    ||($reimport['player_guide']??null)!==$player
                    ||count($reimport['cards']??[])!==39
                    ||count($reimport['routes']??[])!==12)
                    throw new RuntimeException('A01-T5 reimport lost canonical data');
                $a01T5=[
                    'status'=>'ISOLATED_IMPORT_EXPORT_REIMPORT_PASSED',
                    'deck_id'=>671,'fields'=>count($expectedProfile),
                    'featured'=>count($player['featured_cards']),
                    'phases'=>count($player['phases']),
                    'negative_cases'=>$negativeCount,
                    'published_cards'=>count($activated['cards']),
                    'published_photos'=>$photos,
                    'published_routes'=>count($activated['routes']),
                    'version_has_native_timestamp'=>true,
                    'other_decks_unchanged'=>true,
                    'source_inventory_unchanged'=>true,
                    'external_html_reader_absent'=>true,
                    'reimport_profile_exact'=>true,
                    'production_imported'=>false,
                ];
            } finally {
                $filesToRemove=new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($scratch,FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST);
                foreach($filesToRemove as $item){
                    if($item->isDir()&&!$item->isLink())rmdir($item->getPathname());
                    else unlink($item->getPathname());
                }
                rmdir($scratch);
            }
        }
        $reports[]=['deck_id'=>$id,'files'=>$filesAdded,'routes'=>$expected['routes'],
            'steps'=>$steps,'illustrated_steps'=>$illustrated,'illustrative_endboards'=>$quality['illustrative_endboards'],
            'exact_endboards'=>$quality['full_endboards'],'preview_ready'=>true,
            'reimport_consistent'=>true,'a01_t5'=>$a01T5];
    }finally{unlink($tmp);}
}
echo json_encode(['ok'=>true,'type'=>'isolated-real-GPT-ZIP-into-Station-X-importer',
    'database_write'=>false,'decks'=>$reports],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
