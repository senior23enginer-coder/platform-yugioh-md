<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\DeckWorlds;

use PDO;

/** Existing idempotent DDL; called explicitly by bootstrap, never by domain construction. */
final class DeckWorldsSchema
{
    public static function ensure(PDO $pdo): void
    {
        $engine =
            $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
                ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
                : '';
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS sx_ygo_deck_attributes (deck_id INT PRIMARY KEY, attributes_json TEXT NOT NULL, revision INT NOT NULL, updated_at VARCHAR(19) NOT NULL)' .
                $engine,
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS sx_ygo_deck_attribute_revisions (deck_id INT NOT NULL, revision INT NOT NULL, attributes_json TEXT NOT NULL, changed_at VARCHAR(19) NOT NULL, PRIMARY KEY(deck_id,revision))' .
                $engine,
        );
    }
}
