<?php
declare(strict_types=1);
require_once __DIR__.'/data-package.php';
require_once __DIR__.'/data-folder.php';
require_once __DIR__.'/combo-creator.php';

function ygo_data_current(string $app,int $id): array {
    return \StationX\Yugioh\Infrastructure\Composition\DeckPackageModule::sources($app)->current($id);
}
function ygo_data_sources(string $app,int $id,?array $entry): array {
    return \StationX\Yugioh\Infrastructure\Composition\DeckPackageModule::sources($app)->sources($id, $entry);
}
function ygo_data_saved(PDO $pdo,int $id): array {
    return (new \StationX\Yugioh\Infrastructure\Persistence\Decks\PdoDeckPackageInventory($pdo))->saved($id);
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
        $prefix='';$entity=1;$pdo=connect_store_common($prefix,$entity);
        $app=$appOverride??dirname(__DIR__,2);
        $workflow=\StationX\Yugioh\Infrastructure\Composition\DeckPackageModule::workflow($app,$pdo);
        $context=$workflow->open($id);$inventory=$context['inventory'];$op=(string)($_GET['op']??'status');
        if($method==='GET'&&$op==='status'){
            $result=['token'=>$_SESSION['sx_csrf']]+$workflow->status($context);
        }elseif($method==='GET'&&$op==='export'){
            $files=$workflow->export($context);
            $temp=tempnam(sys_get_temp_dir(),'ygo-export-');
            (new \StationX\Yugioh\Infrastructure\Editorial\ZipDeckPackageArchive())->write($temp,$files,false);
            $filename=preg_replace('/[^\pL\pN _.-]/u','_',$inventory['deck']['name']).'-Data.zip';
            header('Content-Type: application/zip');header("Content-Disposition: attachment; filename=\"deck-$id-Data.zip\"; filename*=UTF-8''".rawurlencode($filename));header('Content-Length: '.filesize($temp));readfile($temp);unlink($temp);exit;
        }elseif($method==='POST'&&$op==='preview'){
            $upload=$_FILES['package']??null;if(!$upload||$upload['error']!==UPLOAD_ERR_OK)throw new InvalidArgumentException('No se recibió el ZIP. Comprueba el tamaño máximo de subida del servidor.');
            if($upload['size']>48*1024*1024)throw new InvalidArgumentException('El ZIP debe pesar menos de 48 MB.');
            $result=$workflow->preview($context,$upload['tmp_name']);
        }elseif($method==='POST'&&$op==='commit'){
            $result=$workflow->commit($context,(string)($_POST['ticket']??''));
        }else throw new InvalidArgumentException('Operación no admitida.');
        header('Content-Type: application/json; charset=utf-8');echo ygo_data_json(['ok'=>true]+$result);
    }catch(Throwable $e){
        if($temp&&is_file($temp))unlink($temp);$public=$e instanceof InvalidArgumentException||$e instanceof DomainException||$e instanceof JsonException;
        http_response_code($e instanceof DomainException?409:($public?422:503));header('Content-Type: application/json; charset=utf-8');
        if(!$public)error_log('Station X deck data: '.$e->getMessage());
        echo ygo_data_json(['ok'=>false,'message'=>$public?$e->getMessage():'No se pudo completar la operación. Revisa la extensión ZIP, el espacio y los permisos de escritura del alojamiento.']);
    }exit;
}
