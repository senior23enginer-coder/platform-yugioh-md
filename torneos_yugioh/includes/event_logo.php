<?php
declare(strict_types=1);

function ygo_event_logo_version(array $event): string
{
    return hash('sha256',json_encode([(int)$event['id'],(string)($event['logo_url']??'')],JSON_THROW_ON_ERROR));
}

function ygo_event_logo_event(PDO $pdo,int $id,bool $lock=false): array
{
    $q=$pdo->prepare('SELECT id,season_id,type,logo_url FROM sx_ygo_competitions WHERE id=? AND active=1'.($lock?' FOR UPDATE':''));$q->execute([$id]);$event=$q->fetch(PDO::FETCH_ASSOC);
    if(!$event)throw new InvalidArgumentException('El evento no existe.');
    ygo_season_require_open($pdo,(int)$event['season_id'],false,$lock);
    return $event;
}

function ygo_event_logo_save(PDO $pdo,int $id,array $input): array
{
    return ygo_with_event_write_lock($pdo,function()use($pdo,$id,$input){
        $pdo->beginTransaction();$uploaded='';
        try{
            $event=ygo_event_logo_event($pdo,$id,true);
            if(!hash_equals(ygo_event_logo_version($event),(string)($input['version']??'')))throw new InvalidArgumentException('El logo cambió desde que abriste la ventana. Ciérrala y vuelve a abrirla.');
            $remove=!empty($input['competition_remove_logo']);$gallery=trim((string)($input['competition_gallery_logo']??''));
            if($gallery!==''&&ygo_valid_event_gallery_logo($gallery,$event['type'])==='')throw new InvalidArgumentException('Selecciona un logo válido de la galería de este evento.');
            $hasUpload=isset($_FILES['competition_logo'])&&(int)($_FILES['competition_logo']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE;
            if(!$remove&&$hasUpload){$uploaded=ygo_store_event_logo_upload();if($uploaded==='')throw new InvalidArgumentException('No se pudo guardar la imagen. Usa un archivo PNG, JPG, WEBP o GIF válido.');}
            $url=$remove?(string)(ygo_event_logo_gallery()[$event['type']][0]['url']??''):($uploaded!==''?$uploaded:($gallery!==''?$gallery:(string)$event['logo_url']));
            if($url!==(string)$event['logo_url']){
                $pdo->prepare('UPDATE sx_ygo_competitions SET logo_url=? WHERE id=?')->execute([$url,$id]);
                $source=$remove?'default':($uploaded!==''?'upload':'gallery');ygo_record_logo_history($pdo,'competition',$id,$url,$source);
                $pdo->prepare('INSERT INTO sx_ygo_audit_log(entity_type,entity_id,action,payload) VALUES(?,?,?,?)')->execute(['competition',$id,'save_event_logo',json_encode(['source'=>$source,'before'=>['event'=>['logo_url'=>$event['logo_url']]],'after'=>['event'=>['logo_url'=>$url]]],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
            }
            $event['logo_url']=$url;$pdo->commit();return ['ok'=>true,'logo_url'=>$url,'version'=>ygo_event_logo_version($event)];
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if($uploaded!==''&&preg_match('#^\./assets/uploads/events/event-[a-zA-Z0-9.-]+$#',$uploaded)){ $file=dirname(__DIR__).'/'.substr($uploaded,2);if(is_file($file))unlink($file); }throw $e;}
    });
}

function ygo_event_logo_api(PDO $pdo): never
{
    header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
    try{
        if(!ygo_can_edit())throw new InvalidArgumentException('No tienes permiso para cambiar el logo.');
        $_SESSION['event_logo_token']??=bin2hex(random_bytes(24));$id=(int)($_GET['event_id']??0);
        if($_SERVER['REQUEST_METHOD']==='POST'){
            if(!hash_equals($_SESSION['event_logo_token'],(string)($_POST['token']??'')))throw new InvalidArgumentException('La sesión cambió. Vuelve a abrir la ventana.');
            $answer=ygo_event_logo_save($pdo,$id,$_POST);
        }elseif($_SERVER['REQUEST_METHOD']==='GET'){$event=ygo_event_logo_event($pdo,$id);$answer=['ok'=>true,'token'=>$_SESSION['event_logo_token'],'version'=>ygo_event_logo_version($event),'logo_url'=>$event['logo_url']];}
        else throw new InvalidArgumentException('Operación no permitida.');
        echo json_encode($answer,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    }catch(Throwable $e){http_response_code(422);echo json_encode(['ok'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}
    exit;
}
