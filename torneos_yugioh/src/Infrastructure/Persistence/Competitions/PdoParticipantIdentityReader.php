<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\ParticipantIdentityReader;

final class PdoParticipantIdentityReader implements ParticipantIdentityReader
{
    public function __construct(private readonly PDO $pdo) {}

    public function activeDecks(array $ids): array
    {
        $q = $this->pdo->prepare(
            'SELECT id,team_id FROM sx_ygo_decks WHERE active=1 AND id IN (' .
                implode(',', array_fill(0, count($ids), '?')) .
                ')',
        );
        $q->execute($ids);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }
}
