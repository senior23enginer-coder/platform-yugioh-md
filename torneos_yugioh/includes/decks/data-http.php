<?php
declare(strict_types=1);
require_once __DIR__.'/data-package.php';
require_once __DIR__.'/data-folder.php';
require_once __DIR__.'/combo-creator.php';

function ygo_data_current(string $app,int $id): array {
    $state=ygo_data_read($app.'/assets/editorial/strategy-content-state.json');$entry=$state['checked_studies'][(string)$id]??null;
    $path=$entry?ygo_data_local($app,$entry['path']):null;
    return [$entry,$path?ygo_data_read($path):null];
}
function ygo_data_sources(string $app,int $id,?array $entry): array {
    $path=isset($entry['source_zip'])?ygo_data_local($app,$entry['source_zip']):null;
    if(!$path){$idx=$app.'/assets/editorial/deck-data-sources/index.json';$index=is_file($idx)?ygo_data_read($idx):[];$path=isset($index[(string)$id])?ygo_data_local($app,$index[(string)$id]['zip']):null;}
    return $path?ygo_data_normalize_files(ygo_data_archive($path)):[];
}
function ygo_data_saved(PDO $pdo,int $id): array {
    $q=$pdo->prepare('SELECT id,revision,document_json,inventory_json,updated_at FROM sx_ygo_created_combos WHERE deck_id=? ORDER BY id');$q->execute([$id]);return $q->fetchAll(PDO::FETCH_ASSOC);
}

/** Translate local images in author Markdown to inert text for the native reader.
 * The original Markdown and HTML guide remain intact inside the archived ZIP.
 * Remote sources, event handlers and unrecognized tags fail closed.
 */
function ygo_data_safe_markdown_images(string $markdown, array $files): string {
    return \StationX\Yugioh\Domain\Decks\Packages\SafeMarkdownImages::normalize($markdown, $files);
}


/**
 * GPT portable deliveries use the same station-x-deck-package/1 schema.
 * No hashes are forged: validate ALL inventory row IDs/zones/quantities against
 * the current native database inventory, and block extra/missing/duplicate rows.
 * Historical TCG decks can have real `reserve` slots. Accept the exact section
 * only when native and portable row IDs/quantities agree; never convert it to Side.
 */
function ygo_data_validate_portable_inventory(array $envelope,array $inventory,string $folder,?array $current): void {
    \StationX\Yugioh\Domain\Decks\Packages\PortableInventory::validate($envelope, $inventory, $folder, $current);
}

function ygo_data_prepare(string $app,array $files,array $inventory,?array $current,array $sources): array {
    return \StationX\Yugioh\Infrastructure\Composition\DeckPackageModule::prepare($app)->execute($files, $inventory, $current, $sources);
}
function ygo_data_http(?string $appOverride=null): never {
    header('Cache-Control: no-store');$temp=null;
    try{
        if(isset($_GET['public_season'])||isset($_GET['public_live']))throw new InvalidArgumentException('Abre esta herramienta desde la plataforma.');
        sx_start();$method=$_SERVER['REQUEST_METHOD']??'GET';
        if(!in_array($method,['GET','POST'],true))throw new InvalidArgumentException('Método no admitido.');
        if($method==='POST'&&!hash_equals((string)($_SESSION['sx_csrf']??''),(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'')))throw new InvalidArgumentException('La sesión cambió. Recarga la plataforma.');
        if(!class_exists('ZipArchive'))throw new RuntimeException('Activa la extensión ZIP de PHP en el alojamiento.');
        $id=filter_var($_GET['deck_id']??0,FILTER_VALIDATE_INT);if(!$id||$id<1)throw new InvalidArgumentException('Deck no válido.');
        $prefix='';$entity=1;$pdo=connect_store_common($prefix,$entity);$inventory=ygo_combo_inventory($pdo,$id,false,false);
        $app=$appOverride??dirname(__DIR__,2);[$entry,$current]=ygo_data_current($app,$id);$op=(string)($_GET['op']??'status');
        if($method==='GET'&&$op==='status'){
            $result=['token'=>$_SESSION['sx_csrf'],'has_data'=>(bool)$current,'cards'=>count($current['cards']??[]),'routes'=>count($current['routes']??[]),'updated_at'=>is_string($current['folder_delivery']['imported_at']??null)?$current['folder_delivery']['imported_at']:null];
        }elseif($method==='GET'&&$op==='export'){
            $files=ygo_data_export_files($app,$inventory,$current,ygo_data_sources($app,$id,$entry),ygo_data_saved($pdo,$id));
            $temp=tempnam(sys_get_temp_dir(),'ygo-export-');$zip=new ZipArchive();if($zip->open($temp,ZipArchive::OVERWRITE)!==true)throw new RuntimeException('No se pudo preparar el ZIP.');
            foreach($files as $name=>$bytes)$zip->addFromString($name,$bytes);if(!$zip->close())throw new RuntimeException('No se pudo cerrar el ZIP.');
            $filename=preg_replace('/[^\pL\pN _.-]/u','_',$inventory['deck']['name']).'-Data.zip';
            header('Content-Type: application/zip');header("Content-Disposition: attachment; filename=\"deck-$id-Data.zip\"; filename*=UTF-8''".rawurlencode($filename));header('Content-Length: '.filesize($temp));readfile($temp);unlink($temp);exit;
        }elseif($method==='POST'&&$op==='preview'){
            $upload=$_FILES['package']??null;if(!$upload||$upload['error']!==UPLOAD_ERR_OK)throw new InvalidArgumentException('No se recibió el ZIP. Comprueba el tamaño máximo de subida del servidor.');
            if($upload['size']>48*1024*1024)throw new InvalidArgumentException('El ZIP debe pesar menos de 48 MB.');
            $files=ygo_data_normalize_files(ygo_data_archive($upload['tmp_name']));$sources=ygo_data_sources($app,$id,$entry);$record=ygo_data_prepare($app,$files,$inventory,$current,$sources);
            $hasStructuredPackage=false;
            foreach(array_keys($files) as $name)if(basename($name)==='stationx-deck.json'){$hasStructuredPackage=true;break;}
            $temp=tempnam(sys_get_temp_dir(),'ygo-import-');if(!copy($upload['tmp_name'],$temp))throw new RuntimeException('No se pudo preparar la carga.');
            $old=$_SESSION['ygo_deck_data_pending']??null;if($old&&is_file($old['path']))unlink($old['path']);
            $ticket=bin2hex(random_bytes(24));$saved=ygo_data_saved($pdo,$id);
            $_SESSION['ygo_deck_data_pending']=['id'=>$id,'path'=>$temp,'ticket'=>$ticket,'time'=>time(),'registry'=>hash('sha256',ygo_data_json($entry)),'inventory'=>$inventory['inventory_sha256'],'saved'=>hash('sha256',ygo_data_json($saved))];$temp=null;
            $before=array_column($current['routes']??[],null,'id');$new=0;$changed=0;
            foreach($record['routes'] as $r){if(!isset($before[$r['id']]))$new++;elseif(ygo_data_json($r)!==ygo_data_json($before[$r['id']]))$changed++;}
            $result=['ticket'=>$ticket,'deck'=>$inventory['deck']['name'],'cards'=>count($record['cards']),'routes'=>count($record['routes']),'new_routes'=>$new,'changed_routes'=>$changed,'removed_routes'=>count(array_diff(array_keys($before),array_column($record['routes'],'id'))),'saved_revisions'=>count($saved),'inventory_changed'=>false,'candidate'=>$record,'native_cards'=>$inventory['cards'],'skills'=>$inventory['skills']??[],
                'compatibility'=>[
                    'package'=>$hasStructuredPackage?'station-x-deck-package/1':'legacy-markdown-folder',
                    'import_mode'=>$hasStructuredPackage?'estructurado':'documental',
                    'warning'=>$hasStructuredPackage?null:'Este ZIP de carpeta GPT no contiene datos/stationx-deck.json. Conserva el Markdown y las rutas estructuradas existentes, pero las novedades A01–A10 no se transfieren íntegramente al editor visual. Exporta Data desde Station X para preparar un paquete estructurado completo.',
                    'editorial'=>$record['schema'],
                    'visual_routes'=>count($record['routes']),
                    'visual_quality'=>ygo_data_presentation_quality($record),
                    'player_guide_status'=>(($record['player_guide']['schema']??null)==='station-x-player-guide/1')?'PERFIL_PEDAGOGICO_VALIDADO':'LEGADO_SIN_GUIA_PEDAGOGICA',
                    'strategy_status'=>(($record['player_guide']['strategy']['schema']??null)==='station-x-player-strategy/1')?'ESTRUCTURA_OCHO_LECCIONES':'SIN_ESTRATEGIA_REVISADA',
                    'semantic_acceptance'=>false,
                    'calculator_schema'=>$record['calculator']['schema']??null,
                    'calculator_rules'=>count($record['calculator']['rules']??[]),
                    'calculator_status'=>empty($record['calculator']['rules'])?'SIN_REGLAS_DECLARATIVAS':'REQUIERE_VALIDACION_DEL_MOTOR',
                ]];
        }elseif($method==='POST'&&$op==='commit'){
            $pending=$_SESSION['ygo_deck_data_pending']??[];
            if(($pending['id']??0)!==$id||!hash_equals($pending['ticket']??'',(string)($_POST['ticket']??''))||time()-($pending['time']??0)>1800)throw new InvalidArgumentException('La previsualización venció. Selecciona otra vez el ZIP.');
            if($pending['inventory']!==$inventory['inventory_sha256']||$pending['saved']!==hash('sha256',ygo_data_json(ygo_data_saved($pdo,$id))))throw new DomainException('Las cartas o una edición guardada cambiaron. Previsualiza de nuevo.');
            $files=ygo_data_normalize_files(ygo_data_archive($pending['path']));$record=ygo_data_prepare($app,$files,$inventory,$current,ygo_data_sources($app,$id,$entry));
            $result=['entry'=>ygo_data_publish($app,$id,$record,$files,$pending['registry']),'deck'=>$inventory['deck']['name']];
            unlink($pending['path']);unset($_SESSION['ygo_deck_data_pending']);
        }else throw new InvalidArgumentException('Operación no admitida.');
        header('Content-Type: application/json; charset=utf-8');echo ygo_data_json(['ok'=>true]+$result);
    }catch(Throwable $e){
        if($temp&&is_file($temp))unlink($temp);$public=$e instanceof InvalidArgumentException||$e instanceof DomainException||$e instanceof JsonException;
        http_response_code($e instanceof DomainException?409:($public?422:503));header('Content-Type: application/json; charset=utf-8');
        if(!$public)error_log('Station X deck data: '.$e->getMessage());
        echo ygo_data_json(['ok'=>false,'message'=>$public?$e->getMessage():'No se pudo completar la operación. Revisa la extensión ZIP, el espacio y los permisos de escritura del alojamiento.']);
    }exit;
}
