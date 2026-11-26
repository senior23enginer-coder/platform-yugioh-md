<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Teams;

use PDO;
use StationX\Yugioh\Application\Teams\TeamResultStore;

final readonly class PdoTeamResultStore implements TeamResultStore
{
    public function __construct(private PDO $pdo) {}

    public function lockCompetition(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id=? FOR UPDATE');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function lockMatch(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sx_ygo_matches WHERE id=? FOR UPDATE');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function load(int $season, int $matchId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT setting_value FROM sx_ygo_settings WHERE season_id=? AND setting_key=?',
        );
        $stmt->execute([$season, 'event_team_result_' . $matchId]);
        $value = $stmt->fetchColumn();
        return $value === false
            ? null
            : json_decode((string) $value, true, 64, JSON_THROW_ON_ERROR);
    }

    public function save(int $season, int $matchId, array $record): void
    {
        $this->pdo
            ->prepare(
                'INSERT INTO sx_ygo_settings(season_id,setting_key,setting_value) VALUES(?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',
            )
            ->execute([
                $season,
                'event_team_result_' . $matchId,
                json_encode($record, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]);
    }

    public function saveCollective(int $matchId, array $duels, array $aggregate): void
    {
        $this->pdo
            ->prepare(
                "INSERT INTO sx_ygo_match_results(match_id,d1,d2,d3,d4,d5,result,score,validation_status,notes) VALUES(?,?,?,?,?,?,?,?,'OK',?) ON DUPLICATE KEY UPDATE d1=VALUES(d1),d2=VALUES(d2),d3=VALUES(d3),d4=VALUES(d4),d5=VALUES(d5),result=VALUES(result),score=VALUES(score),validation_status='OK',notes=VALUES(notes)",
            )
            ->execute([
                $matchId,
                ...$duels,
                $aggregate['winner'],
                $aggregate['score'],
                'Resultado colectivo STATION_X_TEAMS_V1; detalle por puesto en la ficha del evento.',
            ]);
    }

    public function audit(int $matchId, array $record): void
    {
        $this->pdo
            ->prepare(
                'INSERT INTO sx_ygo_audit_log(entity_type,entity_id,action,payload) VALUES(?,?,?,?)',
            )
            ->execute([
                'match',
                $matchId,
                'save_team_result',
                json_encode($record, JSON_UNESCAPED_UNICODE),
            ]);
    }
}
