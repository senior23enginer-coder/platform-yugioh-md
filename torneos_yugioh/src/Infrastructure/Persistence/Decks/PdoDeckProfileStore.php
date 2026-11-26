<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Decks;

use PDO;
use StationX\Yugioh\Application\Decks\DeckProfileStore;

final readonly class PdoDeckProfileStore implements DeckProfileStore
{
    public function __construct(private PDO $pdo) {}

    public function team(int $id): ?int
    {
        $stmt = $this->pdo->prepare('SELECT team_id FROM sx_ygo_decks WHERE id=? AND active=1 LIMIT 1');
        $stmt->execute([$id]);
        $value = $stmt->fetchColumn();
        return $value === false || $value === null ? null : (int) $value;
    }

    public function cover(int $id): string
    {
        $stmt = $this->pdo->prepare('SELECT cover_url FROM sx_ygo_decks WHERE id=? AND active=1 LIMIT 1');
        $stmt->execute([$id]);
        return (string) ($stmt->fetchColumn() ?: '');
    }

    public function save(int $id, array $profile): int
    {
        $values = [
            $profile['divisionId'], $profile['teamId'], $profile['rank'], $profile['name'],
            $profile['archetype'], $profile['style'], $profile['reason'], $profile['keyCards'],
            $profile['variations'], $profile['notes'], $profile['coverUrl'],
        ];
        if ($id > 0) {
            $this->pdo->prepare('UPDATE sx_ygo_decks SET division_id=?, team_id=?, seed_rank=?, name=?, archetype=?, style=?, reason=?, key_cards=?, variations=?, notes=?, cover_url=?, active=1 WHERE id=?')
                ->execute([...$values, $id]);
            return $id;
        }
        $this->pdo->prepare('INSERT INTO sx_ygo_decks (division_id, team_id, seed_rank, name, archetype, style, reason, key_cards, variations, notes, cover_url, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)')
            ->execute($values);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateCover(int $id, string $url): void
    {
        $this->pdo->prepare('UPDATE sx_ygo_decks SET cover_url=? WHERE id=?')->execute([$url, $id]);
    }
}
