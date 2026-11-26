<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\CompetitionMaintenanceStore;

final readonly class PdoCompetitionMaintenanceStore implements CompetitionMaintenanceStore
{
    public function __construct(private PDO $pdo) {}

    public function currentSeasonId(): int
    {
        return \ygo_current_season_id($this->pdo);
    }

    public function archive(int $id): void
    {
        $this->pdo->prepare("UPDATE sx_ygo_competitions SET active=0, status='archived' WHERE id=?")->execute([$id]);
    }

    public function qualifierStatus(int $id, int $seasonId): string
    {
        $stmt = $this->pdo->prepare("SELECT status FROM sx_ygo_competitions WHERE id=? AND season_id=? AND type='qualifier' AND active=1 LIMIT 1");
        $stmt->execute([$id, $seasonId]);
        return (string) ($stmt->fetchColumn() ?: '');
    }

    public function setQualifierStatus(int $id, int $seasonId, string $status): void
    {
        $this->pdo->prepare("UPDATE sx_ygo_competitions SET status=? WHERE id=? AND season_id=? AND type='qualifier' AND active=1")->execute([$status, $id, $seasonId]);
    }

    public function rankingExists(int $id, int $seasonId): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM sx_ygo_competitions WHERE id = ? AND season_id = ? AND active = 1 AND type NOT IN ('cup', 'final') LIMIT 1");
        $stmt->execute([$id, $seasonId]);
        return (int) ($stmt->fetchColumn() ?: 0) > 0;
    }

    public function typeExists(int $id, int $seasonId, string $type): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM sx_ygo_competitions WHERE id = ? AND season_id = ? AND type = ? AND active = 1');
        $stmt->execute([$id, $seasonId, $type]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function qualifierDivisions(int $id, int $seasonId): array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM sx_ygo_divisions WHERE season_id = ? AND source_qualifier_id = ? AND active = 1');
        $stmt->execute([$seasonId, $id]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function competition(int $id, int $seasonId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, status FROM sx_ygo_competitions WHERE id = ? AND season_id = ? AND active = 1 LIMIT 1');
        $stmt->execute([$id, $seasonId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function complete(int $id, int $seasonId): void
    {
        $this->pdo->prepare("UPDATE sx_ygo_competitions SET status='completed' WHERE id=? AND season_id=?")->execute([$id, $seasonId]);
    }

    public function audit(int $id, string $action, array $payload): void
    {
        $this->pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
            ->execute(['competition', $id, $action, json_encode($payload, JSON_UNESCAPED_UNICODE)]);
    }
}
