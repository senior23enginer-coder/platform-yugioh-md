<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\QualifierFinalizationWriter;

final readonly class PdoQualifierFinalizationWriter implements QualifierFinalizationWriter
{
    public function __construct(private PDO $pdo) {}

    public function assign(int $divisionId, int $deckId, int $rank): void
    {
        $this->pdo->prepare('UPDATE sx_ygo_decks SET division_id = ?, seed_rank = ? WHERE id = ?')
            ->execute([$divisionId, $rank, $deckId]);
    }

    public function updateDivisionCount(int $divisionId, int $size): void
    {
        $this->pdo->prepare('UPDATE sx_ygo_divisions SET deck_count = ? WHERE id = ?')->execute([$size, $divisionId]);
    }

    public function regenerateLeagues(int $seasonId): void
    {
        \ygo_regenerate_league_matches($this->pdo, $seasonId);
    }

    public function audit(int $competitionId, int $total): void
    {
        $this->pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
            ->execute(['competition', $competitionId, 'finalize_qualifier', json_encode(['total_decks' => $total], JSON_UNESCAPED_UNICODE)]);
    }
}
