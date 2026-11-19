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
    return preg_replace_callback('~<img\b[^>]*>~iu', static function(array $match) use($files):string {
        $tag=$match[0];
        if(!preg_match('~^<img\s+src="(imagenes/[a-zA-Z0-9_.-]+\.(?:png|jpe?g|webp|gif))"(?:\s+width="[0-9]{1,3}")?(?:\s+alt="[^"<>]*")?\s*/?>$~iuD',$tag,$parts)) {
            throw new InvalidArgumentException('Etiqueta HTML de imagen no admitida. Usa imágenes locales sin atributos ejecutables.');
        }
        $source=ygo_data_source_image($files,$parts[1]);
        if($source===null)throw new InvalidArgumentException('La imagen del documento no existe en el ZIP: '.$parts[1]);
        return '[Imagen local: '.$parts[1].']';
    },$markdown) ?? throw new InvalidArgumentException('No se pudo procesar el Markdown.');
}

function ygo_data_prepare(string $app,array $files,array $inventory,?array $current,array $sources): array {
    ygo_data_check_folder($files,$sources,$inventory);
    $id=(int)$inventory['deck']['id'];$envelope=null;$envelopeName=null;
    foreach($files as $name=>$raw)if(basename($name)==='stationx-deck.json'){
        if($envelope)throw new InvalidArgumentException('El ZIP contiene varios decks. Exporta e importa un deck a la vez.');
        $envelope=json_decode($raw,true,128,JSON_THROW_ON_ERROR);$envelopeName=$name;
    }
    if($envelope){
        if(($envelope['schema']??'')!=='station-x-deck-package/1'||(int)($envelope['deck_id']??0)!==$id)throw new InvalidArgumentException('Este ZIP pertenece a otro deck.');
        if(($envelope['inventory_sha256']??'')!==$inventory['inventory_sha256'])throw new InvalidArgumentException('La lista de cartas cambió. Exporta la data actual y pide a ChatGPT actualizar ese paquete.');
        $record=$envelope['record']??null;if(!is_array($record))throw new InvalidArgumentException('El paquete no contiene data del deck.');
        // Explicitly choose the structured document or Markdown as authority, never guess between conflicting edits.
        $mode=$envelope['content_mode']??'structured';
        if($mode==='auto'){
            $source=$envelope['source_document']??'';$baseline=$envelope['baseline']??[];
            if(!isset($files[$source],$baseline['record_sha256'],$baseline['source_sha256']))throw new InvalidArgumentException('Falta el documento o la referencia de la exportación automática. Exporta de nuevo la carpeta del deck.');
            $changedRecord=hash('sha256',ygo_data_json($record))!==$baseline['record_sha256'];$changedSource=hash('sha256',$files[$source])!==$baseline['source_sha256'];
            if($changedRecord&&$changedSource)throw new InvalidArgumentException('El JSON y el Markdown fueron modificados. Pide a ChatGPT indicar content_mode=structured o markdown para elegir la fuente principal.');
            $mode=$changedSource?'markdown':'structured';
        }
        if(!in_array($mode,['structured','markdown'],true))throw new InvalidArgumentException('content_mode debe ser auto, structured o markdown.');
        if($mode==='markdown'){
            $source=$envelope['source_document']??'';
            if(!is_string($source)||!isset($files[$source])||!str_ends_with($source,'.md'))throw new InvalidArgumentException('Falta el documento Markdown declarado.');
            $record=ygo_data_markdown(ygo_data_safe_markdown_images($files[$source],$files),$record,$id);
        }
        $source=$envelope['source_document']??'';
        if(is_string($source)&&isset($files[$source]))$record['imported_source_text']=ygo_data_safe_markdown_images($files[$source],$files);
    }else{
        // Existing ChatGPT folder deliveries: resolve exact source filename, including owner-confirmed aliases.
        $expected=[];foreach($sources as $name=>$bytes)if(preg_match('/CONTENIDO_CURADO\.md$/i',$name))$expected[]=basename($name);
        $matches=[];foreach($files as $name=>$raw)if(in_array(basename($name),$expected,true))$matches[$name]=$raw;
        if(count($matches)!==1||!$current)throw new InvalidArgumentException('No se identificó inequívocamente este deck. Usa el ZIP de «Exportar data» como base para ChatGPT y conserva stationx-deck.json.');
        $record=ygo_data_markdown(ygo_data_safe_markdown_images(reset($matches),$files),$current,$id);
    }
    $record['name']=$inventory['deck']['name'];
    // A source package cannot replace a manually saved editor revision. The creator continues to use its native history.
    foreach($record['routes'] as &$route)foreach(['native_editor_document','native_editor_cards','native_editor_revision'] as $k)unset($route[$k]);unset($route);
    ygo_data_validate($record,$inventory);
    array_walk_recursive($record,static function(&$v,$k)use($files,$app){
        if(!is_string($v)||!in_array($k,['image','full_image'],true)||$v==='')return;
        $source=ygo_data_source_image($files,$v);if($source)$v=$source;
        if(isset($files[$v])){if(!preg_match('/\.(png|jpe?g|webp|gif)$/i',$v))throw new InvalidArgumentException('Una fotografía no corresponde a una imagen admitida.');return;}
        if(!ygo_data_local($app,$v))throw new InvalidArgumentException('Falta una imagen del paquete: '.$v);
    });
    return $record;
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
            $result=['token'=>$_SESSION['sx_csrf'],'has_data'=>(bool)$current,'cards'=>count($current['cards']??[]),'routes'=>count($current['routes']??[])];
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
