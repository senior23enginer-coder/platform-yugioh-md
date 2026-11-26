<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Calendar;

use PDO;
use StationX\Yugioh\Application\Calendar\QualifierAutoScheduleStore;

final readonly class PdoQualifierAutoScheduleStore implements QualifierAutoScheduleStore
{
    public function __construct(private PDO $pdo) {}

    public function pending(?int $competitionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, round_number, table_number
            FROM sx_ygo_matches
            WHERE phase = 'qualifier'
              AND (? IS NULL OR competition_id = ? OR competition_id IS NULL)
              AND status <> 'FINALIZADA'
            ORDER BY round_number, table_number, id
        ");
        $stmt->execute([$competitionId, $competitionId]);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function update(?int $competitionId, array $row): void
    {
        $this->pdo->prepare('UPDATE sx_ygo_matches SET competition_id=COALESCE(competition_id, ?), scheduled_on=?, starts_at=?, venue=COALESCE(NULLIF(venue, ""), ?), notes=COALESCE(NULLIF(notes, ""), ?) WHERE id=?')
            ->execute([$competitionId, $row['date'], $row['time'], 'Station X Tulua', 'Programado automaticamente desde Gestion.', $row['id']]);
    }

    public function updateDailyLimit(int $competitionId, int $limit): void
    {
        $this->pdo->prepare('UPDATE sx_ygo_competitions SET qualifier_matches_per_day = ? WHERE id = ?')
            ->execute([$limit, $competitionId]);
    }

    public function audit(?int $competitionId, array $payload): void
    {
        $this->pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
            ->execute(['competition', $competitionId, 'auto_schedule_qualifier', json_encode($payload, JSON_UNESCAPED_UNICODE)]);
    }
}
