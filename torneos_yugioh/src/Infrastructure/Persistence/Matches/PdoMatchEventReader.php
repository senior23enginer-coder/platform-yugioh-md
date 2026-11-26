<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Matches;

use PDO;
use StationX\Yugioh\Application\Matches\MatchEventReader;

final readonly class PdoMatchEventReader implements MatchEventReader
{
    public function __construct(private PDO $pdo) {}

    public function competition(int $matchId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT c.*
            FROM sx_ygo_matches m
            JOIN sx_ygo_competitions c ON c.id = m.competition_id AND c.active = 1
            WHERE m.id = ?
            LIMIT 1
        ');
        $stmt->execute([$matchId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function phase(int $matchId): string
    {
        $stmt = $this->pdo->prepare('SELECT phase FROM sx_ygo_matches WHERE id = ? LIMIT 1');
        $stmt->execute([$matchId]);
        return (string) ($stmt->fetchColumn() ?: '');
    }

    public function workflow(array $competition): ?array
    {
        return \ygo_workflow_load($this->pdo, $competition);
    }
}
