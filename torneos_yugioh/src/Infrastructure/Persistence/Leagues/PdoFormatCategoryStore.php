<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Leagues;

use PDO;
use StationX\Yugioh\Application\Leagues\FormatCategoryStore;

final readonly class PdoFormatCategoryStore implements FormatCategoryStore
{
    public function __construct(private PDO $pdo) {}

    public function managed(int $seasonId): bool
    {
        $managed = $this->pdo->prepare(
            "SELECT setting_value FROM sx_ygo_settings WHERE season_id=? AND setting_key='league_structure_managed' LIMIT 1",
        );
        $managed->execute([$seasonId]);
        return (string) $managed->fetchColumn() === '1';
    }

    public function rename(
        int $seasonId,
        string $oldCode,
        string $newCode,
        array $definition,
        int $tier,
    ): void {
        $this->pdo->prepare('
            UPDATE sx_ygo_divisions
            SET code=?, name=?, cup_name=?, tier_order=?, deck_count=?, accent=?, description=?
            WHERE season_id=? AND code=?
        ')->execute([
            $newCode,
            $definition['name'],
            $definition['cup'],
            $tier,
            $definition['size'],
            $definition['accent'],
            $definition['description'],
            $seasonId,
            $oldCode,
        ]);
    }

    public function codes(int $seasonId): array
    {
        $stmt = $this->pdo->prepare('SELECT code FROM sx_ygo_divisions WHERE season_id = ?');
        $stmt->execute([$seasonId]);
        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function update(int $seasonId, string $code, array $definition, int $tier): void
    {
        $this->pdo->prepare('
            UPDATE sx_ygo_divisions
            SET name=?, cup_name=?, tier_order=?, deck_count=?, accent=?, description=?
            WHERE season_id=? AND code=?
        ')->execute([
            $definition['name'],
            $definition['cup'],
            $tier,
            $definition['size'],
            $definition['accent'],
            $definition['description'],
            $seasonId,
            $code,
        ]);
    }
}
