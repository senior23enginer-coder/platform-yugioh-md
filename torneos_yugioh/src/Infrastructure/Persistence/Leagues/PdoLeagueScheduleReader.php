<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Leagues;

use PDO;
use StationX\Yugioh\Application\Leagues\LeagueScheduleReader;

final readonly class PdoLeagueScheduleReader implements LeagueScheduleReader
{
    public function __construct(private PDO $pdo) {}

    public function divisions(int $competitionId): array
    {
        return (new PdoDivisionMovementReader($this->pdo))->divisions($competitionId);
    }

    public function deckDivisions(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT id,division_id FROM sx_ygo_decks WHERE active=1 AND id IN (' .
                implode(',', array_fill(0, count($ids), '?')) .
                ')',
        );
        $stmt->execute($ids);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function rosterGroups(array $ids, int $participantsPerSide): array
    {
        return \ygo_roster_groups_for_match($this->pdo, $ids, $participantsPerSide);
    }
}
