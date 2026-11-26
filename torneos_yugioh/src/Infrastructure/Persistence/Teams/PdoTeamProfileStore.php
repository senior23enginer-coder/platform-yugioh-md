<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Teams;

use PDO;
use StationX\Yugioh\Application\Teams\TeamProfileStore;

final readonly class PdoTeamProfileStore implements TeamProfileStore
{
    public function __construct(private PDO $pdo) {}

    public function logo(int $id): string
    {
        $stmt = $this->pdo->prepare('SELECT logo_url FROM sx_ygo_teams WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return (string) ($stmt->fetchColumn() ?: '');
    }

    public function save(int $id, array $profile): int
    {
        $values = [
            $profile['name'],
            $profile['city'],
            $profile['logo'],
            $profile['code'],
            $profile['symbol'],
        ];
        if ($id > 0) {
            $this->pdo
                ->prepare(
                    'UPDATE sx_ygo_teams SET name=?, city=?, logo_url=?, short_code=?, symbol=? WHERE id=?',
                )
                ->execute([...$values, $id]);
            return $id;
        }
        $this->pdo
            ->prepare(
                'INSERT INTO sx_ygo_teams (name, city, logo_url, short_code, symbol, active) VALUES (?, ?, ?, ?, ?, 1)',
            )
            ->execute($values);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateLogo(int $id, string $url): void
    {
        $this->pdo->prepare('UPDATE sx_ygo_teams SET logo_url=? WHERE id=?')->execute([$url, $id]);
    }
}
