<?php
declare(strict_types=1);
require_once __DIR__.'/completion_times.php';

function ygo_match_editor_snapshot(PDO $p,int $id,bool $lock=false): array {
    $q=$p->prepare('SELECT * FROM sx_ygo_matches WHERE id=?'.($lock?' FOR UPDATE':''));$q->execute([$id]);$m=$q->fetch(PDO::FETCH_ASSOC);
    if(!$m)throw new InvalidArgumentException('La partida no existe.');
    $q=$p->prepare('SELECT * FROM sx_ygo_match_results WHERE match_id=?'.($lock?' FOR UPDATE':''));$q->execute([$id]);$result=$q->fetch(PDO::FETCH_ASSOC)?:[];
    $q=$p->prepare('SELECT * FROM sx_ygo_live_duel_state WHERE match_id=?'.($lock?' FOR UPDATE':''));$q->execute([$id]);$live=$q->fetch(PDO::FETCH_ASSOC)?:[];
    $q=$p->prepare('SELECT * FROM sx_ygo_turn_audit WHERE match_id=? ORDER BY duel_number,turn_number');$q->execute([$id]);$turns=$q->fetchAll(PDO::FETCH_ASSOC);
    return ['match'=>$m,'result'=>$result,'live'=>$live,'turns'=>$turns,'version'=>hash('sha256',json_encode([$m,$result,$live,$turns],JSON_THROW_ON_ERROR))];
}

function ygo_match_editor_require_open(PDO $p,array $m,bool $playing=false):array {
    $c=ygo_competition_for_match($p,(int)$m['id']);$season=$c?(int)$c['season_id']:ygo_current_season_id($p);
    $s=ygo_season_require_open($p,$season,$playing,$p->inTransaction());
    if($c&&in_array(ygo_normalize_status_key($c['status']),['completed','closed','cancelled','archived'],true))throw new InvalidArgumentException('El evento está cerrado y solo permite consulta.');
    return $s;
}

function ygo_match_editor_schedule_guard(PDO $p,array $m,array $input):array {
    $season=ygo_match_editor_require_open($p,$m);$date=trim((string)($input['scheduled_on']??''));$time=trim((string)($input['starts_at']??''));
    if($date===''||!preg_match('/^\d{2}:\d{2}(:\d{2})?$/',$time)||substr($time,0,5)>'23:59'||substr($time,3,2)>'59')return ['ok'=>false,'message'=>'Selecciona una fecha y una hora válidas.'];
    $guard=ygo_validate_match_schedule($p,(int)$m['id'],$date,$time,trim((string)($input['venue']??'')));if(!$guard['ok'])return $guard;
    foreach([[$season['starts_on']??null,$season['ends_on']??null,'temporada'],[(ygo_competition_for_match($p,(int)$m['id'])['starts_on']??null),(ygo_competition_for_match($p,(int)$m['id'])['ends_on']??null),'evento']] as [$start,$end,$label])if(($start&&$date<$start)||($end&&$date>$end))return ['ok'=>false,'message'=>'La fecha debe estar dentro del período de la '.$label.'.'];
    if($date!==$m['scheduled_on']||substr($time,0,5)!==substr((string)$m['starts_at'],0,5)){
        if(in_array(strtoupper($m['status']),['FINALIZADA','FINALIZADO','COMPLETED'],true))return ['ok'=>false,'message'=>'La fecha de una partida finalizada se conserva como dato histórico.'];
    }
    return ['ok'=>true,'message'=>'Fecha y hora disponibles según el calendario, los participantes y la capacidad configurada.'];
}

function ygo_match_editor_int(mixed $value,int $min,int $max,string $label):int {
    $n=filter_var($value,FILTER_VALIDATE_INT);if($n===false||$n<$min||$n>$max)throw new InvalidArgumentException($label.' debe estar entre '.$min.' y '.$max.'.');return $n;
}

function ygo_match_editor_verified_start(PDO $p,int $id,int $duel,string $starts):bool {
    if(!in_array($starts,['A','B'],true))return false;$q=$p->prepare('SELECT duel_details FROM sx_ygo_match_results WHERE match_id=?');$q->execute([$id]);
    foreach(json_decode((string)($q->fetchColumn()?:'[]'),true)?:[] as $d)if((int)($d['duel']??0)===$duel&&!empty($d['manual_correction_at_ms'])&&($d['starts_player']??'')===$starts)return true;
    return false;
}

/** Explicit corrections replace the effective turn state; the original evidence is retained in the audit payload. */
function ygo_match_editor_details(array $rows,array $before):array {
    if(!array_is_list($rows)||count($rows)!==5)throw new InvalidArgumentException('La edición debe incluir D1 a D5, sin duplicados.');
    $stored=[];foreach(json_decode($before['result']['duel_details']??'[]',true)?:[] as $row)$stored[(int)$row['duel']]=$row;
    $details=[];$values=[];
    foreach($rows as $i=>$row){$n=$i+1;if(!is_array($row)||(int)($row['duel']??0)!==$n)throw new InvalidArgumentException('Los duelos deben estar ordenados de D1 a D5.');
        $winner=(string)($row['winner']??'');if(!in_array($winner,['','A','B','E'],true))throw new InvalidArgumentException('Resultado de D'.$n.' inválido.');$values[]=$winner;
        $d=array_replace($stored[$n]??[],['duel'=>$n,'winner'=>$winner]);
        foreach(['lp_a','lp_b'] as $key)$d[$key]=ygo_match_editor_int($row[$key]??8000,0,99999,'LP de D'.$n);
        $number=ygo_match_editor_int($row['current_turn']??1,1,999,'Número de turno de D'.$n);$d['turn_count']=$number-1;
        $start=(string)($row['starts_player']??'');$active=(string)($row['active_side']??'');
        if(!in_array($start,['','A','B'],true)||!in_array($active,['','A','B'],true))throw new InvalidArgumentException('Jugador de turno inválido.');
        if($winner!==''&&$start==='')throw new InvalidArgumentException('Indica quién empezó D'.$n.'.');
        $expected=$start===''?'':($d['turn_count']%2===0?$start:($start==='A'?'B':'A'));
        if($active!==$expected)throw new InvalidArgumentException('El turno de D'.$n.' no coincide con su número y el jugador inicial.');
        $d['starts_player']=$start;$d['active_side']=$active;$d['turn_history']=array_values(array_filter(ygo_normalize_turn_history($d['turn_history']??[]),static fn($t)=>$t['turn']<=$d['turn_count']&&$t['side']===($t['turn']%2===1?$start:($start==='A'?'B':'A'))));
        $method=(string)($row['defeat_method']??'');if(!in_array($method,['','life_points','surrender','time_limit','deck_out','effect_win'],true))throw new InvalidArgumentException('Motivo de cierre no válido.');$d['defeat_method']=$method;
        if($winner!==''&&$method==='life_points'&&(($winner==='A'&&$d['lp_b']!==0)||($winner==='B'&&$d['lp_a']!==0)))throw new InvalidArgumentException('Una derrota por LP requiere cero LP para el lado derrotado.');
        if($method==='surrender'&&$winner!==''){if(!in_array($winner,['A','B'],true)||$active=== $winner)throw new InvalidArgumentException('La rendición debe corresponder al turno del lado que pierde.');$d['surrendered_by']=$active;}else{$d['surrendered_by']='';$d['surrendered_at']='';}
        $d['duration_seconds']=ygo_match_editor_int($row['duration_seconds']??0,0,7200,'Duración de D'.$n);$d['notes']=mb_substr(trim((string)($row['notes']??'')),0,500);
        $d['brick_a']=!empty($row['brick_a']);$d['brick_b']=!empty($row['brick_b']);$d['duel_started']=$winner!==''||!empty($row['duel_started']);$d['timer_running']=false;$d['timer_started_at']=null;
        $d['data_origin']=$d['data_origin']??'added';$d['manual_correction_at']=date(DATE_ATOM);$d['manual_correction_at_ms']=(int)floor(microtime(true)*1000);$d['timer_updated_at']=$d['manual_correction_at_ms'];$details[]=$d;
    }return ['details'=>$details,'values'=>$values];
}

/** Editing a row never changes which unfinished game must resume. */
function ygo_match_editor_current(array $values,string $status): int {
    if($status==='FINALIZADA')return max(1,count(array_filter($values)));
    foreach($values as $i=>$value)if(!in_array($value,['A','B','E'],true))return $i+1;
    return 5;
}

function ygo_match_editor_plan(PDO $p,int $id,array $input,array $before,bool $checkWinner=true): array {
    ygo_match_editor_int($input['active_duel']??1,1,5,'Duelo a modificar');
    $prepared=ygo_match_editor_details($input['duels']??[],$before);$details=$prepared['details'];$values=$prepared['values'];
    $evaluation=ygo_apply_event_model_to_result($p,$id,ygo_evaluate_duel_with_rule($p,$id,$values),$values);
    if(($evaluation['status']??'')==='INVALID'||($evaluation['validation']??'OK')!=='OK')throw new InvalidArgumentException($evaluation['validation']??'Secuencia invalida.');
    if($checkWinner&&(string)($input['winner']??'')!==(string)($evaluation['winner']??''))throw new InvalidArgumentException('El ganador debe coincidir con los resultados registrados.');
    $active=ygo_match_editor_current($values,$evaluation['status']);$d=$details[$active-1];
    if($evaluation['status']!=='FINALIZADA'&&$d['duel_started']&&$d['starts_player']==='')throw new InvalidArgumentException('Indica quien empezo el duelo actual.');
    return ['details'=>$details,'values'=>$values,'evaluation'=>$evaluation,'active'=>$active,'status'=>$evaluation['status']==='PENDIENTE'&&$d['duel_started']?'EN CURSO':$evaluation['status']];
}

function ygo_match_editor_save(PDO $p,int $id,array $input):array {
    if(($input['confirmed']??false)!==true)throw new InvalidArgumentException('Confirma que estás seguro antes de guardar.');
    return ygo_with_event_write_lock($p,static function()use($p,$id,$input){$owns=!$p->inTransaction();if($owns)$p->beginTransaction();try{
        $before=ygo_match_editor_snapshot($p,$id,true);if(!hash_equals($before['version'],(string)($input['version']??'')))throw new InvalidArgumentException('La partida cambió mientras la editabas. Recarga los datos antes de confirmar.');
        $m=$before['match'];ygo_match_editor_require_open($p,$m);$schedule=$input['schedule']??[];
        if(!is_array($schedule))throw new InvalidArgumentException('Agenda inválida.');
        $changed=($schedule['scheduled_on']??'')!==($m['scheduled_on']??'')||substr((string)($schedule['starts_at']??''),0,5)!==substr((string)$m['starts_at'],0,5)||trim((string)($schedule['venue']??''))!==trim((string)$m['venue']);
        if($changed||($input['mode']??'')==='schedule'){$guard=ygo_match_editor_schedule_guard($p,$m,$schedule);if(!$guard['ok'])throw new InvalidArgumentException($guard['message']);}
        $p->prepare('UPDATE sx_ygo_matches SET scheduled_on=?,starts_at=?,venue=?,judge=?,notes=? WHERE id=?')->execute([$schedule['scheduled_on']?:null,$schedule['starts_at']?:null,mb_substr(trim((string)($schedule['venue']??'')),0,255),mb_substr(trim((string)($schedule['judge']??'')),0,255),mb_substr(trim((string)($schedule['notes']??'')),0,2000),$id]);
        if(($input['mode']??'')==='full'){
            if(ygo_team_kind(ygo_competition_for_match($p,$id))!=='')throw new InvalidArgumentException('Edita los resultados de equipos desde sus puestos o serie compartida en la ficha del evento.');
            ygo_match_editor_require_open($p,$m,true);ygo_admission_assert_result_editable($p,$id);ygo_workflow_assert_result_editable($p,$id);
            $dateGuard=ygo_validate_match_result_date($p,$id);if(!$dateGuard['ok'])throw new InvalidArgumentException($dateGuard['message']??'La fecha no permite editar resultados.');
            $plan=ygo_match_editor_plan($p,$id,$input,$before);
            $details=$plan['details'];$values=$plan['values'];$evaluation=$plan['evaluation'];$active=$plan['active'];$status=$plan['status'];$d=$details[$active-1];
            $completionNow=ygo_completion_now();
            $details=ygo_completion_games($details,json_decode($before['result']['duel_details']??'[]',true)?:[],array_map(fn($n)=>$before['result']['d'.$n]??'',range(1,5)),$values,$completionNow,!in_array(strtoupper($before['match']['status']),['FINALIZADA','FINALIZADO','COMPLETED','FINISHED'],true));
            $p->prepare('INSERT INTO sx_ygo_match_results(match_id,d1,d2,d3,d4,d5,result,score,validation_status,duel_details,notes,starts_player) VALUES(?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE d1=VALUES(d1),d2=VALUES(d2),d3=VALUES(d3),d4=VALUES(d4),d5=VALUES(d5),result=VALUES(result),score=VALUES(score),validation_status=VALUES(validation_status),duel_details=VALUES(duel_details),notes=VALUES(notes),starts_player=VALUES(starts_player)')->execute([$id,...array_map(fn($v)=>$v?:null,$values),$evaluation['winner'],$evaluation['score'],'OK',json_encode($details,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),mb_substr(trim((string)($input['result_notes']??'')),0,2000),$details[0]['starts_player']?:null]);
            ygo_completion_set_status($p,$id,$status,$completionNow);
            $p->prepare('DELETE FROM sx_ygo_turn_audit WHERE match_id=?')->execute([$id]);
            foreach($details as $row)foreach($row['turn_history'] as $t)$p->prepare('INSERT INTO sx_ygo_turn_audit(match_id,duel_number,turn_number,side,ended_at,elapsed_seconds) VALUES(?,?,?,?,?,?)')->execute([$id,$row['duel'],$t['turn'],$t['side'],$t['ended_at']?:null,$t['elapsed_seconds']]);
            $state=ygo_live_duel_initial_state(ygo_live_duel_match($p,$id));$state=array_replace($state,['duel_number'=>$active,'turn_count'=>$d['turn_count'],'turn_history'=>$d['turn_history'],'starts_player'=>$d['starts_player'],'active_side'=>$d['active_side'],'lp_a'=>$d['lp_a'],'lp_b'=>$d['lp_b'],'timer_running'=>false,'timer_started_at'=>0,'elapsed_seconds'=>$d['duration_seconds'],'status'=>$evaluation['status']==='FINALIZADA'?'completed':($d['duel_started']?'paused':($d['starts_player']?'ready':'setup')),'surrendered_by'=>$d['surrendered_by'],'surrendered_at'=>$d['surrendered_at']]);if($state['status']==='completed')$state['active_side']='';
            $p->prepare("INSERT INTO sx_ygo_live_duel_state(match_id,duel_number,revision,state_json,updated_by) VALUES(?,?,1,?,'judge') ON DUPLICATE KEY UPDATE duel_number=VALUES(duel_number),revision=revision+1,state_json=VALUES(state_json),updated_by='judge'")->execute([$id,$active,json_encode(ygo_live_duel_normalize_state($state),JSON_UNESCAPED_UNICODE)]);
            if($m['competition_id'])ygo_persist_competition_standings($p,(int)(ygo_competition_for_match($p,$id)['season_id']),(int)$m['competition_id'],'manual_match_correction');
        }elseif(($input['mode']??'')!=='schedule')throw new InvalidArgumentException('Modo de edición no válido.');
        $after=ygo_match_editor_snapshot($p,$id);$p->prepare('INSERT INTO sx_ygo_audit_log(entity_type,entity_id,action,payload) VALUES(?,?,?,?)')->execute(['match',$id,'manual_match_correction',json_encode(['before'=>$before,'after'=>$after,'mode'=>$input['mode'],'confirmed'=>true],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
        ygo_sync_meta_history($p);ygo_sync_elo_history($p);ygo_sync_global_ranking_history($p);ygo_sync_event_summaries($p,(int)$m['competition_id']?:null);ygo_sync_medal_unlocks($p,$id);
        if($owns)$p->commit();return ['ok'=>true,'message'=>'Cambios confirmados y guardados.','version'=>$after['version']];
    }catch(Throwable $e){if($owns&&$p->inTransaction())$p->rollBack();throw $e;}});
}

function ygo_match_editor_api(PDO $p):never {
    header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
    try{if(!ygo_can_edit())throw new InvalidArgumentException('No tienes permiso para modificar la partida.');
        $id=(int)($_GET['match_id']??0);$snapshot=ygo_match_editor_snapshot($p,$id);ygo_match_editor_require_open($p,$snapshot['match']);
        $_SESSION['match_editor_token']??=bin2hex(random_bytes(24));
        if($_SERVER['REQUEST_METHOD']==='POST'){$raw=file_get_contents('php://input');if(strlen($raw)>150000)throw new InvalidArgumentException('La edición supera el tamaño permitido.');$input=json_decode($raw,true,64,JSON_THROW_ON_ERROR);if(!hash_equals($_SESSION['match_editor_token'],(string)($input['token']??'')))throw new InvalidArgumentException('La sesión cambió. Recarga la pantalla.');if(($input['preview']??false)===true){if(!hash_equals($snapshot['version'],(string)($input['version']??'')))throw new InvalidArgumentException('La partida cambio mientras la editabas. Recarga antes de confirmar.');$plan=ygo_match_editor_plan($p,$id,$input,$snapshot,false);$answer=['ok'=>true,'winner'=>$plan['evaluation']['winner'],'score'=>$plan['evaluation']['score'],'status'=>$plan['status'],'active_duel'=>$plan['active']];}else $answer=ygo_match_editor_save($p,$id,$input);}
        elseif(($_GET['check']??'')==='schedule')$answer=ygo_match_editor_schedule_guard($p,$snapshot['match'],$_GET);
        else{$match=ygo_live_duel_match($p,$id);$answer=['ok'=>true,'snapshot'=>$snapshot,'label'=>($match['deck_a']??'A').' vs '.($match['deck_b']??'B'),'token'=>$_SESSION['match_editor_token']];}
        echo json_encode($answer,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    }catch(Throwable $e){if($p->inTransaction())$p->rollBack();http_response_code(422);echo json_encode(['ok'=>false,'message'=>$e instanceof JsonException?'Los datos de edición no son válidos.':$e->getMessage()],JSON_UNESCAPED_UNICODE);}
    exit;
}
