<?php
declare(strict_types=1);
require_once __DIR__.'/attributes.php';
function ygo_deck_attributes_http(): never {
    header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
    try {
        sx_start();$method=$_SERVER['REQUEST_METHOD']??'GET';
        if(!in_array($method,['GET','POST'],true)){http_response_code(405);header('Allow: GET, POST');echo json_encode(['ok'=>false,'message'=>'Método no permitido.']);exit;}
        $canEdit=ygo_can_edit()&&!isset($_GET['public_season'])&&!isset($_GET['public_live']);
        if($method==='POST'&&(!$canEdit||empty($_SERVER['HTTP_X_CSRF_TOKEN'])||!hash_equals((string)$_SESSION['sx_csrf'],(string)$_SERVER['HTTP_X_CSRF_TOKEN']))){http_response_code(403);echo json_encode(['ok'=>false,'message'=>'No se pudo autorizar el guardado. Recarga la plataforma.']);exit;}
        if((int)($_SERVER['CONTENT_LENGTH']??0)>2000)throw new InvalidArgumentException('Datos demasiado grandes.');
        $prefix='';$entity=1;$pdo=connect_store_common($prefix,$entity);ygo_deck_attributes_schema($pdo);
        if($method==='POST'){
            $body=file_get_contents('php://input',false,null,0,2001);if(strlen($body)>2000)throw new InvalidArgumentException('Datos demasiado grandes.');
            $input=json_decode($body,true,8,JSON_THROW_ON_ERROR);if(!is_array($input))throw new InvalidArgumentException('Datos no válidos.');
            $record=ygo_deck_attributes_save($pdo,$input);$answer=['record'=>$record,'history'=>ygo_deck_attributes_history($pdo,$record['deck_id'])];
        }else {
            $eligible=ygo_deck_world_eligible_ids($pdo);$id=(int)($_GET['deck_id']??0);
            if($id&&!in_array($id,$eligible,true))throw new InvalidArgumentException('Mundos solo está disponible para decks TCG.');
            $answer=['catalog'=>ygo_deck_attribute_catalog(),'records'=>ygo_deck_attributes_records($pdo),'eligible_deck_ids'=>$eligible,'history'=>$id?ygo_deck_attributes_history($pdo,$id):[],'can_edit'=>$canEdit,'token'=>$canEdit?$_SESSION['sx_csrf']:null];
        }
        echo json_encode(['ok'=>true]+$answer,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
    }catch(Throwable $e){$valid=$e instanceof InvalidArgumentException||$e instanceof JsonException;$conflict=$e instanceof DomainException;http_response_code($conflict?409:($valid?422:503));if(!$valid&&!$conflict)error_log('Station X deck worlds: '.$e->getMessage());echo json_encode(['ok'=>false,'message'=>$valid||$conflict?$e->getMessage():'No se pudieron consultar o guardar los mundos. Tu selección permanece abierta.'],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);}
    exit;
}
