<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\CompetitionCodeStore;

final readonly class PdoCompetitionCodeStore implements CompetitionCodeStore
{
    public function __construct(private PDO $pdo) {}

    public function codes(int $seasonId, int $ignoreId): array
    {
        $rows = $this->pdo->prepare("SELECT code FROM sx_ygo_competitions WHERE season_id = ? AND code LIKE 'YU-%' AND id <> ?");
        $rows->execute([$seasonId, $ignoreId]);
        return $rows->fetchAll(PDO::FETCH_COLUMN);
    }

    public function exists(int $seasonId, string $code, int $ignoreId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM sx_ygo_competitions WHERE season_id = ? AND code = ? AND id <> ? LIMIT 1',
        );
        $stmt->execute([$seasonId, $code, $ignoreId]);
        return (bool) $stmt->fetchColumn();
    }

    public function competitions(int $seasonId): array
    {
        $rows = $this->pdo->prepare('SELECT id, code FROM sx_ygo_competitions WHERE season_id = ? ORDER BY id');
        $rows->execute([$seasonId]);
        return $rows->fetchAll(PDO::FETCH_ASSOC);
    }

    public function update(int $seasonId, int $competitionId, string $code): void
    {
        $this->pdo->prepare('UPDATE sx_ygo_competitions SET code = ? WHERE id = ? AND season_id = ?')
            ->execute([$code, $competitionId, $seasonId]);
    }
}
