<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence;

use PDO;

/** Sporting read queries use the existing dedicated read-only MCP transaction. */
final class McpScheduleReader
{
    public function __construct(private PDO $pdo) {}

    private function rows(string $sql, array $args = []): array
    {
        $q = $this->pdo->prepare($sql);
        $q->execute($args);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function leagues(?int $season, int $offset): array
    {
        $filter = "c.type='league' AND c.active=1";
        $args = [];
        if ($season !== null) {
            $filter .= ' AND c.season_id=?';
            $args[] = $season;
        }
        $total = (int) $this->rows("SELECT COUNT(*) AS n FROM sx_ygo_competitions c WHERE $filter", $args)[0]['n'];
        $leagues = $this->rows("SELECT c.id,c.name,c.season_id,s.name AS season_name,c.status,c.starts_on,c.ends_on FROM sx_ygo_competitions c LEFT JOIN sx_ygo_seasons s ON s.id=c.season_id WHERE $filter ORDER BY c.season_id DESC,c.id LIMIT 30 OFFSET ".(int)$offset, $args);
        foreach ($leagues as &$league) {
            $id = (int) $league['id'];
            $league['id'] = $id;
            $league['season_id'] = (int) $league['season_id'];
            $league['divisions'] = $this->rows('SELECT id,code,name,tier_order FROM sx_ygo_divisions WHERE league_competition_id=? AND active=1 ORDER BY tier_order,id', [$id]);
            foreach ($league['divisions'] as &$division) {
                $division['id'] = (int) $division['id'];
                $division['decks'] = $this->rows('SELECT id,name,created_at FROM sx_ygo_decks WHERE division_id=? AND active=1 ORDER BY name,id', [$division['id']]);
                $division['deck_count'] = count($division['decks']);
            }
            unset($division);
            $league['division_count'] = count($league['divisions']);
        }
        unset($league);
        return ['leagues'=>$leagues,'total'=>$total,'next_offset'=>$offset+30<$total?$offset+30:null,'retrieved_at'=>gmdate(DATE_ATOM)];
    }

    public function matches(?int $league, ?int $division, ?int $deck, ?int $season, int $offset): array
    {
        $where = ["c.type='league'",'c.active=1'];
        $args = [];
        foreach (['c.id'=>$league,'m.division_id'=>$division,'c.season_id'=>$season] as $field=>$value) {
            if ($value !== null) {
                $where[] = "$field=?";
                $args[] = $value;
            }
        }
        if ($deck !== null) {
            $where[] = '(m.deck_a_id=? OR m.deck_b_id=? OR m.deck_c_id=?)';
            array_push($args,$deck,$deck,$deck);
        }
        $from = ' FROM sx_ygo_matches m JOIN sx_ygo_competitions c ON c.id=m.competition_id LEFT JOIN sx_ygo_divisions d ON d.id=m.division_id LEFT JOIN sx_ygo_decks a ON a.id=m.deck_a_id LEFT JOIN sx_ygo_decks b ON b.id=m.deck_b_id LEFT JOIN sx_ygo_decks z ON z.id=m.deck_c_id WHERE '.implode(' AND ',$where);
        $counts = $this->rows("SELECT COUNT(*) AS total,SUM(CASE WHEN m.scheduled_on IS NULL THEN 1 ELSE 0 END) AS unscheduled,SUM(CASE WHEN m.scheduled_on IS NOT NULL AND m.starts_at IS NULL THEN 1 ELSE 0 END) AS missing_time".$from,$args)[0];
        $rows = $this->rows('SELECT m.id,m.competition_id,c.name AS league_name,c.season_id,m.division_id,d.name AS division_name,m.phase,m.round_number,m.table_number,m.deck_a_id,a.name AS deck_a,m.deck_b_id,b.name AS deck_b,m.deck_c_id,z.name AS deck_c,m.status,m.scheduled_on,m.starts_at,m.venue'.$from.' ORDER BY CASE WHEN m.scheduled_on IS NULL THEN 1 ELSE 0 END,m.scheduled_on,m.starts_at,m.id LIMIT 50 OFFSET '.(int)$offset,$args);
        return ['matches'=>$rows,'total'=>(int)$counts['total'],'unscheduled'=>(int)$counts['unscheduled'],'scheduled'=>(int)$counts['total']-(int)$counts['unscheduled'],'missing_time'=>(int)$counts['missing_time'],'next_offset'=>$offset+50<(int)$counts['total']?$offset+50:null,'retrieved_at'=>gmdate(DATE_ATOM),'timezone'=>'America/Bogota','note'=>'Solo partidas de ligas activas registradas con participantes directos A/B/C. Las fechas nulas no se infieren.'];
    }

    public function deckSchedule(int $deck, ?int $season, int $offset): array
    {
        $identity = $this->rows('SELECT id,name,created_at,active,division_id FROM sx_ygo_decks WHERE id=?',[$deck])[0]??null;
        if (!$identity) {
            throw new \InvalidArgumentException('El deck no existe.');
        }
        $args = [$deck];
        $filter = "d.id=? AND v.active=1 AND c.type='league' AND c.active=1";
        if ($season !== null) {
            $filter .= ' AND c.season_id=?';
            $args[] = $season;
        }
        $memberships = $this->rows("SELECT c.id AS league_id,c.name AS league_name,c.season_id,v.id AS division_id,v.name AS division_name FROM sx_ygo_decks d JOIN sx_ygo_divisions v ON v.id=d.division_id JOIN sx_ygo_competitions c ON c.id=v.league_competition_id WHERE $filter ORDER BY c.id,v.id",$args);
        return ['deck'=>$identity,'memberships'=>$memberships,'schedule'=>$this->matches(null,null,$deck,$season,$offset)];
    }
}
