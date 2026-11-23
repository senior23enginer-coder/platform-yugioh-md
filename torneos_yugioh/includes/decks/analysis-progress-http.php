<?php
declare(strict_types=1);
require_once __DIR__.'/analysis-progress.php';

/** Authenticated, same-origin tracking endpoint. It never invokes an LLM. */
function ygo_analysis_progress_http(): never {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    try {
        sx_start();
        if(isset($_GET['public_season'])||isset($_GET['public_live'])||!ygo_can_edit()){
            http_response_code(403);throw new DomainException('El seguimiento requiere permisos de edición.');
        }
        $method=$_SERVER['REQUEST_METHOD']??'GET';
        if(!in_array($method,['GET','POST'],true))throw new InvalidArgumentException('Método no admitido.');
        $deckId=filter_var($_GET['deck_id']??null,FILTER_VALIDATE_INT);
        if(!$deckId||$deckId<1)throw new InvalidArgumentException('Selecciona un deck válido.');
        $op=(string)($_GET['op']??'status');
        if($method==='POST'){
            $token=(string)($_SESSION['sx_csrf']??'');
            if($token===''||!hash_equals($token,(string)($_SERVER['HTTP_X_CSRF_TOKEN']??''))){
                http_response_code(403);throw new DomainException('La sesión cambió. Recarga la plataforma.');
            }
            if((int)($_SERVER['CONTENT_LENGTH']??0)>6000)
                throw new InvalidArgumentException('La solicitud supera el tamaño permitido.');
            $raw=(string)file_get_contents('php://input',false,null,0,6001);
            if(strlen($raw)>6000)throw new InvalidArgumentException('Solicitud demasiado grande.');
            $input=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
            if(!is_array($input))throw new InvalidArgumentException('Solicitud no válida.');
            if(($input['deck_id']??null)!==$deckId)
                throw new DomainException('No se puede editar el seguimiento de otro deck.');
        }
        $prefix='';$entity=1;$pdo=connect_store_common($prefix,$entity);
        $inventory=ygo_combo_inventory($pdo,$deckId,false,false);
        $counts=ygo_progress_inventory_counts($inventory);
        $fingerprint=(string)($inventory['inventory_sha256']??'');
        if($fingerprint==='')throw new DomainException('Inventario sin huella auténtica. Exporta Data.');
        $format=trim((string)($inventory['format']??''));
        if($format==='')$format='unspecified';
        // Tracking can be prepared before A02 resolves the format. It is NOT
        // permission to execute a prompt without that mandatory verification.
        $manifest=ygo_progress_manifest(dirname(__DIR__,2));
        ygo_progress_schema($pdo);
        $saved=ygo_progress_saved($pdo,$deckId);
        $eligible=($counts['main']>0&&$counts['extra']>0);
        $now=gmdate('Y-m-d\TH:i:s\Z');
        if($method==='GET'&&$op==='status'){
            $ledger=$saved['ledger']??null;
            $changed=$saved!==null&&($saved['fingerprint']!==$fingerprint||$saved['format']!==$format);
            $result=['assigned'=>$ledger!==null,'eligible'=>$eligible,'format_pending'=>$format==='unspecified','inventory_changed'=>$changed,
              'inventory_counts'=>$counts,'ledger'=>$ledger,'manifest'=>$manifest,
              'summary'=>$ledger?ygo_progress_summary($ledger,$changed?[]:($format==='unspecified'?[]:ygo_progress_history($pdo,$ledger,$format))):null,
              'token'=>(string)($_SESSION['sx_csrf']??'')];
        }elseif($method==='POST'){
            if($op==='assign'){
                if(($input['explicit_user_assignment']??null)!==true)
                    throw new DomainException('La asignación exige confirmación expresa del usuario.');
                ygo_progress_require_assigned($deckId,true,$counts);
                if($saved!==null)throw new DomainException('Este deck ya tiene seguimiento: no se reinicia.');
                $ledger=ygo_progress_new_ledger($deckId,$inventory,$manifest,true,$now,false);
                ygo_progress_write($pdo,$ledger,$fingerprint,$format,null);
            }else{
                if($saved===null)throw new DomainException('Primero asigna expresamente este deck.');
                if($saved['fingerprint']!==$fingerprint||$saved['format']!==$format)
                    throw new DomainException('Cambió el inventario: bloqueado para evitar inconsistencias.');
                if(($input['revision']??null)!==$saved['revision'])
                    throw new DomainException('Hay una versión más reciente del seguimiento. Actualiza la vista.');
                $ledger=$saved['ledger'];
                if($op==='enable_calculator'){
                    if(($input['explicit_a10b_request']??null)!==true)
                        throw new DomainException('A10B requiere autorización explícita.');
                    $ledger['calculator_requested']=true;
                    $ledger['updated_at']=$now;$ledger['revision']++;
                }elseif($op==='transition'){
                    $action=(string)($input['action']??'');
                    $taskId=(string)($input['task_id']??'');
                    if(strlen($taskId)>40||strlen((string)($input['evidence']??''))>3000||
                       strlen((string)($input['reason']??''))>3000)
                        throw new InvalidArgumentException('Evidencia o identificador demasiado largo.');
                    $ledger=ygo_progress_transition($ledger,$taskId,$action,$now,
                       isset($input['evidence'])?(string)$input['evidence']:null,
                       isset($input['reason'])?(string)$input['reason']:null);
                }else throw new InvalidArgumentException('Operación no admitida.');
                ygo_progress_write($pdo,$ledger,$fingerprint,$format,$saved['revision']);
            }
            $result=['assigned'=>true,'eligible'=>true,'format_pending'=>$format==='unspecified','inventory_changed'=>false,'inventory_counts'=>$counts,
              'ledger'=>$ledger,'manifest'=>$manifest,
              'summary'=>ygo_progress_summary($ledger,($format==='unspecified'?[]:ygo_progress_history($pdo,$ledger,$format))),
              'token'=>(string)($_SESSION['sx_csrf']??'')];
        }else throw new InvalidArgumentException('Operación no admitida.');
        echo json_encode(['ok'=>true]+$result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    }catch(Throwable $e){
        $public=$e instanceof InvalidArgumentException||$e instanceof DomainException||$e instanceof JsonException;
        if(http_response_code()===200)http_response_code($public?422:503);
        if(!$public)error_log('Station X deck analysis progress: '.$e->getMessage());
        echo json_encode(['ok'=>false,'message'=>$public?$e->getMessage():
          'No fue posible consultar o guardar el seguimiento del deck.'],
          JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    }
    exit;
}
