<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Calendar;

use PDO;
use StationX\Yugioh\Application\Calendar\MatchScheduleStore;

final readonly class PdoMatchScheduleStore implements MatchScheduleStore
{
    public function __construct(private PDO $pdo) {}

    public function find(int $matchId): array
    {
        $stmt = $this->pdo->prepare('SELECT competition_id, phase, status, scheduled_on, starts_at, venue, judge, notes FROM sx_ygo_matches WHERE id = ? LIMIT 1');
        $stmt->execute([$matchId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function update(int $matchId, array $values): void
    {
        $this->pdo->prepare('UPDATE sx_ygo_matches SET scheduled_on=?, starts_at=?, venue=?, judge=?, notes=? WHERE id=?')
            ->execute([$values['scheduled_on'], $values['starts_at'], $values['venue'], $values['judge'], $values['notes'], $matchId]);
    }

    public function audit(int $matchId, string $action, array $payload): void
    {
        $this->pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
            ->execute(['match', $matchId, $action, json_encode($payload, JSON_UNESCAPED_UNICODE)]);
    }

    public function competitionType(int $competitionId): string
    {
        $stmt = $this->pdo->prepare('SELECT type FROM sx_ygo_competitions WHERE id=? LIMIT 1');
        $stmt->execute([$competitionId]);
        return (string) $stmt->fetchColumn();
    }
}
