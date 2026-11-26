<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Decks;

use PDO;
use StationX\Yugioh\Application\Decks\LegacyFormatNormalizationStore;

final readonly class PdoLegacyFormatNormalizationStore implements LegacyFormatNormalizationStore
{
    public function __construct(private PDO $pdo) {}

    public function alreadyNormalized(): bool
    {
        return (int) $this->pdo
            ->query(
                "SELECT COUNT(*) FROM sx_ygo_audit_log WHERE entity_type = 'deck' AND action = 'normalize_existing_decks_tcg_advanced'",
            )
            ->fetchColumn() > 0;
    }

    public function currentTcgDivision(): int
    {
        $seasonId = \ygo_current_season_id($this->pdo);
        $stmt = $this->pdo->prepare(
            "SELECT id FROM sx_ygo_divisions WHERE season_id = ? AND code = 'TCG_AVANZADO' AND active = 1 LIMIT 1",
        );
        $stmt->execute([$seasonId]);
        return (int) $stmt->fetchColumn();
    }

    public function normalize(int $divisionId): int
    {
        $update = $this->pdo->prepare(
            'UPDATE sx_ygo_decks SET division_id = ? WHERE active = 1 AND (division_id IS NULL OR division_id <> ?)',
        );
        $update->execute([$divisionId, $divisionId]);
        return $update->rowCount();
    }

    public function record(int $divisionId, int $updated): void
    {
        $this->pdo
            ->prepare(
                'INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)',
            )
            ->execute([
                'deck',
                null,
                'normalize_existing_decks_tcg_advanced',
                json_encode(
                    ['division_id' => $divisionId, 'updated' => $updated],
                    JSON_UNESCAPED_UNICODE,
                ),
            ]);
    }
}
