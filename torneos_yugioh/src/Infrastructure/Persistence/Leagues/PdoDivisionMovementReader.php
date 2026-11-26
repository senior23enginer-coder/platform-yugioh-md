<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Leagues;

use PDO;
use StationX\Yugioh\Application\Leagues\DivisionMovementReader;

final readonly class PdoDivisionMovementReader implements DivisionMovementReader
{
    public function __construct(private PDO $pdo) {}

    public function divisions(int $competitionId): array
    {
        $stmt = $this->pdo->prepare("SELECT d.*, (SELECT COUNT(*) FROM sx_ygo_decks dk WHERE dk.division_id=d.id AND dk.active=1) actual_decks
            FROM sx_ygo_divisions d JOIN sx_ygo_competitions c ON c.id=d.league_competition_id AND c.season_id=d.season_id
            WHERE c.id=? AND c.active=1 AND c.type='league' AND d.active=1 ORDER BY d.tier_order,d.id");
        $stmt->execute([$competitionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function lockCompetition(int $competitionId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM sx_ygo_competitions WHERE id=? AND active=1 FOR UPDATE',
        );
        $stmt->execute([$competitionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function alreadyApplied(int $competitionId): bool
    {
        $done = $this->pdo->prepare(
            "SELECT COUNT(*) FROM sx_ygo_audit_log WHERE entity_type='competition' AND entity_id=? AND action='division_movements_applied'",
        );
        $done->execute([$competitionId]);
        return (int) $done->fetchColumn() > 0;
    }

    public function hasPendingMatches(int $competitionId): bool
    {
        $pending = $this->pdo->prepare(
            "SELECT COUNT(*) FROM sx_ygo_matches WHERE competition_id=? AND status<>'FINALIZADA'",
        );
        $pending->execute([$competitionId]);
        return (int) $pending->fetchColumn() > 0;
    }

    public function standings(int $competitionId): array
    {
        return \ygo_competition_standings($this->pdo, $competitionId);
    }

    public function originalDivisions(): array
    {
        $original = [];
        $stmt = $this->pdo->query('SELECT id,division_id FROM sx_ygo_decks WHERE active=1');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $deck) {
            $original[(int) $deck['id']] = (int) $deck['division_id'];
        }
        return $original;
    }
}
