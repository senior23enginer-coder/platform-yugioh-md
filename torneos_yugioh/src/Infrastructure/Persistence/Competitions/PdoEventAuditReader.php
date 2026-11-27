<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\EventAuditReader;

final class PdoEventAuditReader implements EventAuditReader
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function snapshot(int $id): array
    {
        $pdo = $this->pdo;
        $q=$pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id=?');$q->execute([$id]);
        $event=$q->fetch(PDO::FETCH_ASSOC)?:[];
        $q=$pdo->prepare('SELECT id,name,promotion_slots,relegation_slots,promotion_to_division_id,relegation_to_division_id FROM sx_ygo_divisions WHERE league_competition_id=? AND active=1 ORDER BY tier_order,id');$q->execute([$id]);$event['divisions']=$q->fetchAll(PDO::FETCH_ASSOC);
        $q=$pdo->prepare('SELECT depends_on_competition_id,condition_type,take_count FROM sx_ygo_competition_dependencies WHERE competition_id=? AND active=1 ORDER BY id');$q->execute([$id]);$event['dependencies']=$q->fetchAll(PDO::FETCH_ASSOC);
        $q=$pdo->prepare('SELECT deck_id FROM sx_ygo_competition_decks WHERE competition_id=? AND active=1 ORDER BY sort_order,id');$q->execute([$id]);
        return ['event'=>$event,'participants'=>array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN))];
    }

    public function event(int $id): array|false
    {
        $pdo = $this->pdo;
        $q=$pdo->prepare('SELECT id,name FROM sx_ygo_competitions WHERE id=?');$q->execute([$id]);$event=$q->fetch(PDO::FETCH_ASSOC);
        return $event;
    }

    public function history(int $id, int $before, string $search): array
    {
        $pdo = $this->pdo;
        $where="entity_type='competition' AND entity_id=?";$args=[$id];
        if($before>0){$where.=' AND id<?';$args[]=$before;}
        if($search!==''){$where.=" AND (action LIKE ? ESCAPE '!' OR payload LIKE ? ESCAPE '!' OR created_at LIKE ? ESCAPE '!')";$term='%'.strtr($search,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';array_push($args,$term,$term,$term);}
        $q=$pdo->prepare("SELECT id,action,payload,created_at FROM sx_ygo_audit_log WHERE $where ORDER BY id DESC LIMIT 26");$q->execute($args);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
        return $rows;
    }
}
