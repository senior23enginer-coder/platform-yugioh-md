<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\DependencyWriteStore;

final readonly class PdoDependencyWriteStore implements DependencyWriteStore
{
    public function __construct(private PDO $pdo) {}

    public function currentSeasonId(): int
    {
        return \ygo_current_season_id($this->pdo);
    }

    public function save(int $seasonId, array $input): void
    {
        $this->pdo->prepare("
            INSERT INTO sx_ygo_competition_dependencies (season_id, competition_id, depends_on_competition_id, condition_type, take_count, active)
            VALUES (?, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE condition_type=VALUES(condition_type), take_count=VALUES(take_count), active=1
        ")->execute([$seasonId, $input['competitionId'], $input['dependsOnId'], $input['condition'], $input['takeCount']]);
    }

    public function remove(int $id): void
    {
        $this->pdo->prepare('UPDATE sx_ygo_competition_dependencies SET active=0 WHERE id=?')->execute([$id]);
    }

    public function audit(string $entity, int $id, string $action, array $payload): void
    {
        $this->pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
            ->execute([$entity, $id, $action, json_encode((object) $payload, JSON_UNESCAPED_UNICODE)]);
    }
}
