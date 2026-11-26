<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Decks;

use PDO;
use StationX\Yugioh\Application\Decks\MatchRosterReader;

final readonly class PdoMatchRosterReader implements MatchRosterReader
{
    public function __construct(private PDO $pdo) {}

    public function activeDeckRows(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT id, division_id, team_id FROM sx_ygo_decks WHERE id IN ($placeholders) AND active = 1");
        $stmt->execute($ids);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function activeTeamRows(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT id, team_id FROM sx_ygo_decks WHERE id IN ($placeholders) AND active = 1 AND team_id IS NOT NULL");
        $stmt->execute($ids);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
