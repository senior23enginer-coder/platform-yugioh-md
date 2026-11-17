<?php
declare(strict_types=1);

function ygo_event_audit_snapshot(PDO $pdo, int $id): array
{
    $q=$pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id=?');$q->execute([$id]);
    $event=$q->fetch(PDO::FETCH_ASSOC)?:[];
    $q=$pdo->prepare('SELECT id,name,promotion_slots,relegation_slots,promotion_to_division_id,relegation_to_division_id FROM sx_ygo_divisions WHERE league_competition_id=? AND active=1 ORDER BY tier_order,id');$q->execute([$id]);$event['divisions']=$q->fetchAll(PDO::FETCH_ASSOC);
    $q=$pdo->prepare('SELECT depends_on_competition_id,condition_type,take_count FROM sx_ygo_competition_dependencies WHERE competition_id=? AND active=1 ORDER BY id');$q->execute([$id]);$event['dependencies']=$q->fetchAll(PDO::FETCH_ASSOC);
    $q=$pdo->prepare('SELECT deck_id FROM sx_ygo_competition_decks WHERE competition_id=? AND active=1 ORDER BY sort_order,id');$q->execute([$id]);
    return ['event'=>$event,'participants'=>array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN))];
}

/** Query the persisted event history, independently of the global dashboard limit. */
function ygo_event_audit_page(PDO $pdo, int $id, int $before=0, string $search=''): array
{
    $q=$pdo->prepare('SELECT id,name FROM sx_ygo_competitions WHERE id=?');$q->execute([$id]);$event=$q->fetch(PDO::FETCH_ASSOC);
    if(!$event)throw new InvalidArgumentException('El evento no existe.');
    $where="entity_type='competition' AND entity_id=?";$args=[$id];
    if($before>0){$where.=' AND id<?';$args[]=$before;}
    $search=mb_substr(trim($search),0,150);
    if($search!==''){$where.=" AND (action LIKE ? ESCAPE '!' OR payload LIKE ? ESCAPE '!' OR created_at LIKE ? ESCAPE '!')";$term='%'.strtr($search,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';array_push($args,$term,$term,$term);}
    $q=$pdo->prepare("SELECT id,action,payload,created_at FROM sx_ygo_audit_log WHERE $where ORDER BY id DESC LIMIT 26");$q->execute($args);$rows=$q->fetchAll(PDO::FETCH_ASSOC);$more=count($rows)>25;$rows=array_slice($rows,0,25);
    foreach($rows as &$row){$row['label']=ygo_audit_label((string)$row['action']);$row['detail']=json_decode((string)$row['payload'],true)??['detalle'=>(string)$row['payload']];unset($row['payload']);}unset($row);
    return ['ok'=>true,'event'=>$event,'rows'=>$rows,'next_cursor'=>$more?(int)end($rows)['id']:null];
}

function ygo_event_audit_api(PDO $pdo): never
{
    header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
    try{
        if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);header('Allow: GET');throw new InvalidArgumentException('Este historial solo permite consulta.');}
        if(!ygo_can_edit()){http_response_code(403);throw new InvalidArgumentException('No tienes permiso para consultar la auditoría.');}
        echo json_encode(ygo_event_audit_page($pdo,(int)($_GET['event_id']??0),max(0,(int)($_GET['before']??0)),(string)($_GET['search']??'')),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    }catch(Throwable $e){if(http_response_code()<400)http_response_code(422);echo json_encode(['ok'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}
    exit;
}
