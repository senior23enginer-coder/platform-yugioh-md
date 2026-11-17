<?php
declare(strict_types=1);
require_once __DIR__.'/data-package.php';

function ygo_data_normalize_files(array $files): array {
    $out=[];foreach($files as $path=>$bytes){
        $path=preg_replace('~^(?:01_INFORMACION_PARA_IMPORTAR/decks/|decks/)~','',$path);
        if(isset($out[$path]))throw new InvalidArgumentException('Dos archivos del ZIP tienen la misma ruta de deck.');$out[$path]=$bytes;
    }return $out;
}

/** One self-contained deck folder, never a collection of loose files. */
function ygo_data_folder(array $files,int $id,string $name): string {
    $folders=[];
    foreach(array_keys($files) as $path)foreach(explode('/',$path) as $part)if(str_starts_with($part,'DECK_')){$folders[$part]=true;break;}
    if(count($folders)>1)throw new InvalidArgumentException('El ZIP debe contener una sola carpeta de deck.');
    if($folders)return array_key_first($folders);
    $slug=strtoupper(trim(preg_replace('/[^a-zA-Z0-9]+/','_',$name),'_'));
    return 'DECK_'.$id.'_'.($slug?:'DECK');
}
function ygo_data_check_folder(array $files,array $sources,array $inventory): string {
    $id=(int)$inventory['deck']['id'];$name=$inventory['deck']['name'];
    $expected=ygo_data_folder($sources,$id,$name);$found=ygo_data_folder($files,$id,$name);
    if($found!==$expected)throw new InvalidArgumentException('La carpeta del ZIP no corresponde a esta ficha. Se esperaba '.$expected.'.');
    return $found;
}
function ygo_data_export_files(string $app,array $inventory,?array $record,array $sources,array $saved): array {
    $id=(int)$inventory['deck']['id'];$folder=ygo_data_folder($sources,$id,$inventory['deck']['name']);$files=[];
    foreach($sources as $path=>$bytes){
        if(in_array(basename($path),['stationx-deck.json','editor-revisions.json','LEEME_CHATGPT.txt'],true))continue;
        $parts=explode('/',$path);$offset=array_search($folder,$parts,true);
        $relative=$offset!==false?implode('/',array_slice($parts,$offset)):$folder.'/'.$path;
        $files[$relative]=$bytes;
    }
    if(!$record){
        $record=['schema'=>'station-x-curated-v62','deck_id'=>$id,'name'=>$inventory['deck']['name'],'format'=>match($inventory['format']){'rush'=>'rush_duel','goat'=>'goat','tcg'=>'tcg_advanced',default=>'unspecified'},'cards'=>[],'skills'=>[],'profile'=>[],'summary'=>[],'engine_maps'=>[],'routes'=>[],'decisions'=>[],'practice'=>[]];
        foreach($inventory['cards'] as $c)$record['cards'][]=['id'=>$id.':CARD:'.$c['id'],'name'=>$c['card_name'],'slots'=>[['card_id'=>$c['id'],'section'=>$c['section'],'quantity'=>$c['quantity']]],'image'=>'','full_image'=>'','fields'=>[],'sources'=>[],'synergy'=>['labels'=>[],'resource'=>'','relation'=>''],'related_routes'=>[],'text'=>''];
    }
    $sourceDocument='';foreach($files as $path=>$bytes)if(preg_match('/CONTENIDO_CURADO\.md$/i',$path))$sourceDocument=$path;
    if($sourceDocument===''){
        $sourceDocument=$folder.'/'.$folder.'_CONTENIDO_CURADO.md';
        $files[$sourceDocument]='# '.$record['name']."\n\nID: ".$id."\n\nActualizar los datos estructurados en datos/stationx-deck.json.\n";
    }
    array_walk_recursive($record,static function(&$value,$key)use($app,&$files,$folder){
        if(!is_string($value)||!in_array($key,['image','full_image'],true)||$value==='')return;
        $source=ygo_data_source_image($files,$value);if($source){$value=$source;return;}
        $path=ygo_data_local($app,$value);if(!$path)throw new RuntimeException('No se pudo localizar una fotografía del deck.');
        $target=$folder.'/imagenes/importados/'.hash_file('sha256',$path).'.'.strtolower(pathinfo($path,PATHINFO_EXTENSION));$files[$target]=(string)file_get_contents($path);$value=$target;
    });
    $package=['schema'=>'station-x-deck-package/1','deck_id'=>$id,'deck_name'=>$inventory['deck']['name'],'package_root'=>$folder,
        'structure_reference'=>['deck'=>'Kashtira Pure','deck_id'=>665,'scope'=>'Structure only; never copy its cards, combos or rules to another deck.','sections'=>['profile','cards','skills','engine_maps','routes','decisions','practice'],'document_sections'=>'APARTADO_01 through APARTADO_10; preserve the full authored content.'],
        'content_mode'=>'auto','source_document'=>$sourceDocument,'inventory_sha256'=>$inventory['inventory_sha256'],'inventory'=>$inventory['cards'],'record'=>$record,
        'baseline'=>['record_sha256'=>hash('sha256',ygo_data_json($record)),'source_sha256'=>hash('sha256',$files[$sourceDocument])],
        'instructions'=>'Usa la estructura de Kashtira Pure como referencia de organización y exhaustividad: perfil, fichas de cartas, función y sinergias, planes y decisiones, práctica, combos específicos con propósito, requisitos, pasos, costes, materiales, interrupciones y tablero final documentado; conserva los apartados 01 a 10. No copies sus cartas, cantidades, combos o mecánicas a otro deck ni inventes datos para llenar secciones. Devuelve un ZIP con la única carpeta de ESTE deck, conservando su nombre y estructura: ficha curada, imagenes/, guias/ y datos/stationx-deck.json. Actualiza record para integrar todas las secciones interactivas. Conserva IDs, CID, inventario y fotografías. content_mode=auto detecta cambios solo en record o solo en el Markdown. Si actualizas ambos declara content_mode=structured (record completo es la autoridad) o markdown (secuencias numeradas y texto completo). No generar scripts, reportes, QA, manifiestos adicionales ni archivos por versión. No aplicar listas de prohibidas/limitadas. No modificar inventario ni resultados. Las ediciones guardadas del editor mantienen prioridad. La plataforma importa y activa el paquete sin Codex ni procesos locales.'];
    if($saved)$package['editor_revisions']=$saved;
    $files[$folder.'/datos/stationx-deck.json']=ygo_data_json($package);
    foreach(array_keys($files) as $path)if(!str_starts_with($path,$folder.'/'))throw new RuntimeException('El ZIP no quedó contenido en una sola carpeta.');
    return $files;
}
