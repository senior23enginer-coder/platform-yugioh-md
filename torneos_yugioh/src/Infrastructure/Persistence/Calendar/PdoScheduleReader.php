<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Calendar;

use PDO;
use StationX\Yugioh\Application\Calendar\ScheduleReader;

/** Retains native SQL predicates/order; reading does not initialize a transaction. */
final readonly class PdoScheduleReader implements ScheduleReader
{
    public function __construct(private PDO $pdo) {}

    public function activeCompetition(int $competitionId): ?array
    {
        $query = $this->pdo->prepare(
            'SELECT id,type FROM sx_ygo_competitions WHERE id=? AND active=1 LIMIT 1',
        );
        $query->execute([$competitionId]);
        return $query->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function pendingMatchIds(int $competitionId): array
    {
        $query = $this->pdo->prepare(
            "SELECT id FROM sx_ygo_matches WHERE competition_id=? AND status <> 'FINALIZADA' ORDER BY COALESCE(display_order, 999999), round_number, table_number, id",
        );
        $query->execute([$competitionId]);
        return array_map('intval', $query->fetchAll(PDO::FETCH_COLUMN));
    }

    public function occupancy(int $excludeCompetitionId): array
    {
        $stmt = $this->pdo->prepare("
        SELECT m.scheduled_on, m.starts_at
        FROM sx_ygo_matches m
        LEFT JOIN sx_ygo_competitions c ON c.id=m.competition_id
        WHERE m.status <> 'FINALIZADA' AND m.scheduled_on IS NOT NULL
          AND (
            m.competition_id IS NULL
            OR (
              m.competition_id<>?
              AND c.active=1
              AND LOWER(REPLACE(c.status, ' ', '_')) NOT IN ('cancelled','completed','archived','finalizado','finalizada')
            )
          )
        ORDER BY m.scheduled_on, m.starts_at, m.id
    ");
        $stmt->execute([$excludeCompetitionId]);
        $occupancy = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $date = (string) ($row['scheduled_on'] ?? '');
            if ($date === '') {
                continue;
            }
            $occupancy[$date]['count'] = (int) ($occupancy[$date]['count'] ?? 0) + 1;
            $time = substr((string) ($row['starts_at'] ?? ''), 0, 5);
            if ($time !== '') {
                $occupancy[$date]['times'][$time] = true;
            }
        }
        return $occupancy;
    }

    public function blackouts(int $competitionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT blackout_date FROM sx_ygo_schedule_blackouts WHERE competition_id=? AND active=1',
        );
        $stmt->execute([$competitionId]);
        return array_fill_keys(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)), true);
    }

    public function competitionType(int $competitionId): string
    {
        $query = $this->pdo->prepare('SELECT type FROM sx_ygo_competitions WHERE id=?');
        $query->execute([$competitionId]);
        return (string) ($query->fetchColumn() ?: 'match');
    }
}
