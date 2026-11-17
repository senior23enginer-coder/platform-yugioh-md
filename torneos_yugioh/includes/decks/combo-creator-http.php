<?php
declare(strict_types=1);
require_once __DIR__.'/combo-creator.php';

function ygo_combo_http(): never
{
    header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
    try {
        if (isset($_GET['public_season']) || isset($_GET['public_live'])) throw new InvalidArgumentException('Esta herramienta se abre desde la plataforma.');
        sx_start(); $method=$_SERVER['REQUEST_METHOD']??'GET';
        if (!in_array($method,['GET','POST'],true)) throw new InvalidArgumentException('Método no válido.');
        if ($method==='POST' && !hash_equals((string)($_SESSION['sx_csrf']??''),(string)($_SERVER['HTTP_X_CSRF_TOKEN']??''))) { http_response_code(403);echo json_encode(['ok'=>false,'message'=>'La sesión cambió. Recarga la plataforma; conserva tu edición abierta.']);exit; }
        if ((int)($_SERVER['CONTENT_LENGTH']??0)>150000) throw new InvalidArgumentException('El combo supera el tamaño admitido.');
        $prefix='';$entity=1;$pdo=connect_store_common($prefix,$entity);ygo_combo_schema($pdo);
        if($method==='GET'&&isset($_GET['source_prompts']))ygo_combo_source_feed($pdo);
        if ($method==='POST') {
            $body=file_get_contents('php://input',false,null,0,150001);
            if (strlen($body)>150000) throw new InvalidArgumentException('El combo supera el tamaño admitido.');
            $input=json_decode($body,true,128,JSON_THROW_ON_ERROR);
            if (!is_array($input)) throw new InvalidArgumentException('Datos no válidos.');
            $answer=['record'=>ygo_combo_save($pdo,$input)];
        } else {
            $inventory=ygo_combo_inventory($pdo,(int)($_GET['deck_id']??0),false,false);
            $q=$pdo->prepare('SELECT * FROM sx_ygo_created_combos WHERE deck_id=? ORDER BY updated_at DESC,id');$q->execute([(int)$_GET['deck_id']]);
            $rows=array_map(static fn($r)=>ygo_combo_unpack($r,$inventory['inventory_sha256']),$q->fetchAll(PDO::FETCH_ASSOC));
            $answer=$inventory+['records'=>$rows,'token'=>$_SESSION['sx_csrf']];
            if(isset($_GET['history'])) {
                $id=(string)$_GET['history'];
                if(!preg_match('/^[a-zA-Z0-9-]{16,64}$/D',$id)||!array_filter($rows,static fn($r)=>$r['id']===$id))throw new InvalidArgumentException('El combo no pertenece a este deck.');
                $q=$pdo->prepare('SELECT revision,snapshot_json,created_at FROM sx_ygo_created_combo_revisions WHERE combo_id=? ORDER BY revision DESC');$q->execute([$id]);
                $answer['history']=array_map(static fn($r)=>['revision'=>(int)$r['revision'],'created_at'=>$r['created_at'],'record'=>ygo_combo_unpack(json_decode($r['snapshot_json'],true,128,JSON_THROW_ON_ERROR),$inventory['inventory_sha256'])],$q->fetchAll(PDO::FETCH_ASSOC));
            }
        }
        echo json_encode(['ok'=>true]+$answer,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        $validation=$e instanceof InvalidArgumentException || $e instanceof JsonException;
        http_response_code($e instanceof DomainException?409:($validation?422:503));
        if (!$validation && !($e instanceof DomainException)) error_log('Station X combo creator: '.$e->getMessage());
        echo json_encode(['ok'=>false,'message'=>$validation || $e instanceof DomainException?$e->getMessage():'No se pudo guardar o consultar el combo. Tu edición permanece abierta.'],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
    }
    exit;
}
