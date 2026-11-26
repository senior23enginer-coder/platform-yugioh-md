<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Decks;

use PDO;
use InvalidArgumentException;
use StationX\Yugioh\Application\Commands\LogoHistoryStore;

final readonly class PdoLogoHistoryStore implements LogoHistoryStore
{
    private const TARGETS = [
        'deck' => ['sx_ygo_decks', 'cover_url'],
        'team' => ['sx_ygo_teams', 'logo_url'],
        'competition' => ['sx_ygo_competitions', 'logo_url'],
    ];

    public function __construct(private PDO $pdo) {}

    public function contains(string $entity, int $id, string $url): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM sx_ygo_logo_history WHERE entity_type=? AND entity_id=? AND logo_url=? LIMIT 1');
        $stmt->execute([$entity, $id, $url]);
        return (bool) $stmt->fetchColumn();
    }

    public function select(string $entity, int $id, string $url): void
    {
        [$table, $column] = self::TARGETS[$entity] ?? throw new InvalidArgumentException('Entidad de logo no admitida.');
        $this->pdo->prepare("UPDATE {$table} SET {$column}=? WHERE id=?")->execute([$url, $id]);
    }

    public function record(string $entity, int $id, string $url): void
    {
        \ygo_record_logo_history($this->pdo, $entity, $id, $url, 'history');
    }
}
