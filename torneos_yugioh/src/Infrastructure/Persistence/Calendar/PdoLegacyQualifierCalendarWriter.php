<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Calendar;

use PDO;
use StationX\Yugioh\Application\Calendar\LegacyQualifierCalendarWriter;

final readonly class PdoLegacyQualifierCalendarWriter implements LegacyQualifierCalendarWriter
{
    public function __construct(private PDO $pdo) {}

    public function updateDailyLimit(int $competitionId, int $matchesPerDay): void
    {
        $this->pdo->prepare('UPDATE sx_ygo_competitions SET qualifier_matches_per_day = ? WHERE id = ?')
            ->execute([$matchesPerDay, $competitionId]);
    }

    public function clearPending(int $seasonId, ?int $competitionId): void
    {
        $this->pdo->prepare("DELETE r FROM sx_ygo_match_results r INNER JOIN sx_ygo_matches m ON m.id = r.match_id WHERE m.phase = 'qualifier' AND m.status <> 'FINALIZADA' AND (? IS NULL OR m.competition_id = ? OR m.competition_id IS NULL)")->execute([$competitionId, $competitionId]);
        $this->pdo->prepare("DELETE FROM sx_ygo_matches WHERE phase = 'qualifier' AND status <> 'FINALIZADA' AND (? IS NULL OR competition_id = ? OR competition_id IS NULL)")->execute([$competitionId, $competitionId]);
        $this->pdo->prepare("DELETE FROM sx_ygo_events WHERE season_id=? AND event_type='qualifier' AND status <> 'completed' AND (? IS NULL OR competition_id = ?)")->execute([$seasonId, $competitionId, $competitionId]);
    }

    public function insertFixtures(int $divisionId, ?int $competitionId, iterable $rows): int
    {
        $insert = $this->pdo->prepare("
            INSERT INTO sx_ygo_matches (division_id, competition_id, phase, round_number, table_number, deck_a_id, deck_b_id, status, scheduled_on, starts_at, venue, notes)
            VALUES (?, ?, 'qualifier', ?, ?, ?, ?, 'PENDIENTE', ?, ?, ?, ?)
        ");
        $created = 0;
        foreach ($rows as $row) {
            $insert->execute([
                $divisionId,
                $competitionId,
                $row['round'],
                $row['table'],
                $row['deckA'],
                $row['deckB'],
                $row['date'],
                $row['time'],
                'Station X Tulua',
                'Clasificatorio reprogramado desde hoy, con minimo 4 combates por jornada.',
            ]);
            $created++;
        }
        return $created;
    }

    public function insertJourneys(
        int $seasonId,
        int $divisionId,
        ?int $competitionId,
        array $days,
        string $startTime,
    ): void {
        $eventStmt = $this->pdo->prepare("
            INSERT INTO sx_ygo_events (season_id, competition_id, division_id, code, title, event_type, status, event_date, starts_at, venue, notes)
            VALUES (?, ?, ?, ?, ?, 'qualifier', 'scheduled', ?, ?, 'Station X Tulua', ?)
        ");
        $journey = 1;
        foreach ($days as $day => $count) {
            $eventStmt->execute([
                $seasonId,
                $competitionId,
                $divisionId,
                'clasificatorio-' . ($competitionId ?: 'general') . '-jornada-' . $journey,
                'Clasificatorio - Jornada ' . $journey,
                $day,
                $startTime . ':00',
                $count . ' combates programados. Registro habilitado desde Clasificatorio.',
            ]);
            $journey++;
        }
    }

    public function activate(int $competitionId, string $startDate, string $endDate): void
    {
        $this->pdo->prepare("UPDATE sx_ygo_competitions SET status='active', starts_on=?, ends_on=? WHERE id=?")
            ->execute([$startDate, $endDate, $competitionId]);
    }

    public function audit(?int $competitionId, array $payload): void
    {
        $this->pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
            ->execute(['competition', $competitionId, 'rebuild_qualifier_calendar', json_encode($payload, JSON_UNESCAPED_UNICODE)]);
    }
}
