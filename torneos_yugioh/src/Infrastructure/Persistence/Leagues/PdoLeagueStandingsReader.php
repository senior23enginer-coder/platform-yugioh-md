<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Leagues;

use PDO;
use StationX\Yugioh\Application\Leagues\LeagueStandingsReader;

final readonly class PdoLeagueStandingsReader implements LeagueStandingsReader
{
    public function __construct(private PDO $pdo) {}

    public function activeDecks(): array
    {
        return $this->pdo
            ->query(
                "
            SELECT d.id, d.name, d.seed_rank, dv.code AS division_code
            FROM sx_ygo_decks d
            JOIN sx_ygo_divisions dv ON dv.id = d.division_id
            WHERE d.active = 1
            ORDER BY d.seed_rank, d.id
        ",
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function finalizedMatches(string $phase): array
    {
        $stmt = $this->pdo->prepare("
            SELECT m.deck_a_id, m.deck_b_id, r.result, r.score
            FROM sx_ygo_matches m
            JOIN sx_ygo_match_results r ON r.match_id = m.id
            WHERE m.phase = ? AND m.status = 'FINALIZADA'
        ");
        $stmt->execute([$phase]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
