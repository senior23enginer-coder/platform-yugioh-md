<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\QualifierDivisionStore;

final readonly class PdoQualifierDivisionStore implements QualifierDivisionStore
{
    public function __construct(private PDO $pdo) {}

    public function division(int $divisionId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sx_ygo_divisions WHERE id = ? AND active = 1 LIMIT 1');
        $stmt->execute([$divisionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function qualifierStatus(int $qualifierId): string
    {
        $stmt = $this->pdo->prepare("SELECT status FROM sx_ygo_competitions WHERE id = ? AND active = 1 LIMIT 1");
        $stmt->execute([$qualifierId]);
        return (string) ($stmt->fetchColumn() ?: '');
    }

    public function standings(int $qualifierId): array
    {
        return \ygo_competition_standings($this->pdo, $qualifierId);
    }

    public function assign(int $divisionId, iterable $assignments): int
    {
        $update = $this->pdo->prepare('UPDATE sx_ygo_decks SET division_id = ?, seed_rank = ? WHERE id = ?');
        $count = 0;
        foreach ($assignments as [$deckId, $seedRank]) {
            $update->execute([$divisionId, $seedRank, $deckId]);
            $count++;
        }
        return $count;
    }

    public function record(int $divisionId, int $qualifierId, int $rankFrom, int $rankTo, int $count): void
    {
        $this->pdo->prepare('UPDATE sx_ygo_divisions SET deck_count = ? WHERE id = ?')->execute([$count, $divisionId]);
        $this->pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
            ->execute(['division', $divisionId, 'apply_from_qualifier', json_encode(['qualifier_id' => $qualifierId, 'rank_from' => $rankFrom, 'rank_to' => $rankTo, 'moved' => $count], JSON_UNESCAPED_UNICODE)]);
    }
}
