<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/autoload.php';

function ygo_event_audit_snapshot(PDO $pdo, int $id): array
{
    return (new \StationX\Yugioh\Application\Competitions\ReadEventAudit(
        new \StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoEventAuditReader($pdo),
    ))->snapshot($id);
}

function ygo_event_audit_page(PDO $pdo, int $id, int $before=0, string $search=''): array
{
    return (new \StationX\Yugioh\Application\Competitions\ReadEventAudit(
        new \StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoEventAuditReader($pdo),
    ))->page($id, $before, $search, 'ygo_audit_label');
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
