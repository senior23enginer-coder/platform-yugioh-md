<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\LegacyEventFixtureReader;

final readonly class PdoLegacyEventFixtureReader implements LegacyEventFixtureReader
{
    public function __construct(private PDO $pdo) {}

    public function currentSeasonId(): int
    {
        return \ygo_current_season_id($this->pdo);
    }

    public function firstDivisionId(): int
    {
        $seasonId = $this->currentSeasonId();
        return (int) $this->pdo->query("SELECT id FROM sx_ygo_divisions WHERE season_id = $seasonId ORDER BY tier_order, id LIMIT 1")->fetchColumn();
    }

    public function divisions(int $seasonId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, code FROM sx_ygo_divisions WHERE season_id = ? ORDER BY tier_order, id');
        $stmt->execute([$seasonId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function leagueDecks(int $divisionId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, seed_rank, name, reason FROM sx_ygo_decks WHERE division_id = ? AND active = 1 ORDER BY seed_rank, name');
        $stmt->execute([$divisionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function decks(?int $divisionId = null): array
    {
        if ($divisionId) {
            $stmt = $this->pdo->prepare('SELECT id, name, seed_rank FROM sx_ygo_decks WHERE active = 1 AND division_id = ? ORDER BY seed_rank, name');
            $stmt->execute([$divisionId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $this->pdo->query('SELECT id, name, seed_rank FROM sx_ygo_decks WHERE active = 1 ORDER BY seed_rank, name')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function leagueMatchCount(): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM sx_ygo_matches WHERE phase = 'league'")->fetchColumn();
    }
}
