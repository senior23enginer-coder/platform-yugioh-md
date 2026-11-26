<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Teams;

use PDO;
use StationX\Yugioh\Application\Teams\TeamStore;

final readonly class PdoTeamStore implements TeamStore
{
    public function __construct(private PDO $pdo) {}

    public function findId(string $name): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM sx_ygo_teams WHERE name = ? LIMIT 1');
        $stmt->execute([$name]);
        return (int) $stmt->fetchColumn();
    }

    public function activate(int $id): void
    {
        $this->pdo->prepare('UPDATE sx_ygo_teams SET active = 1 WHERE id = ?')->execute([$id]);
    }

    public function updateProfile(int $id, array $profile): void
    {
        $this->pdo
            ->prepare('UPDATE sx_ygo_teams SET short_code=?, primary_color=?, secondary_color=?, symbol=? WHERE id=?')
            ->execute([$profile['code'], $profile['primary'], $profile['secondary'], $profile['symbol'], $id]);
    }

    public function create(string $name, ?array $profile): int
    {
        $this->pdo
            ->prepare("INSERT INTO sx_ygo_teams (name, city, short_code, primary_color, secondary_color, symbol, active) VALUES (?, 'Tulua', ?, ?, ?, ?, 1)")
            ->execute([$name, $profile['code'] ?? null, $profile['primary'] ?? null, $profile['secondary'] ?? null, $profile['symbol'] ?? null]);
        return (int) $this->pdo->lastInsertId();
    }
}
