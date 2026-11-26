<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Leagues;

use PDO;
use StationX\Yugioh\Application\Leagues\LeagueRosterReader;

final readonly class PdoLeagueRosterReader implements LeagueRosterReader
{
    public function __construct(private PDO $pdo) {}

    public function competition(int $competitionId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id,season_id,type FROM sx_ygo_competitions WHERE id=?');
        $stmt->execute([$competitionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function assignments(): array
    {
        return $this->pdo
            ->query(
                "SELECT DISTINCT c.id competition_id,c.season_id,c.name competition_name,d.id deck_id
            FROM sx_ygo_competitions c JOIN sx_ygo_decks d ON d.active=1 AND (
                EXISTS(SELECT 1 FROM sx_ygo_divisions dv WHERE dv.id=d.division_id AND dv.active=1 AND dv.season_id=c.season_id AND dv.league_competition_id=c.id)
                OR EXISTS(SELECT 1 FROM sx_ygo_competition_decks cd WHERE cd.competition_id=c.id AND cd.deck_id=d.id AND cd.active=1))
            WHERE c.active=1 AND c.type='league' AND LOWER(c.status) NOT IN ('completed','closed','cancelled','canceled','archived','finalized','finalizado','finalizada','anulado','anulada')
            ORDER BY d.id,c.id",
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function lockDecks(array $sortedIds): void
    {
        if (!$sortedIds) {
            return;
        }
        $lock = $this->pdo->prepare(
            'SELECT id FROM sx_ygo_decks WHERE id IN (' .
                implode(',', array_fill(0, count($sortedIds), '?')) .
                ') ORDER BY id FOR UPDATE',
        );
        $lock->execute($sortedIds);
        $lock->fetchAll(PDO::FETCH_COLUMN);
    }
}
