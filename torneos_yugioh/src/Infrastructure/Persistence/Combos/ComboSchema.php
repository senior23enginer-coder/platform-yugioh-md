<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Combos;

use PDO;

/** Explicit schema initialization, outside domain construction and application use cases. */
final class ComboSchema
{
    public static function ensure(PDO $pdo): void
    {
        $text = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'TEXT' : 'LONGTEXT';
        $engine =
            $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
                ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
                : '';
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS sx_ygo_created_combos (
        id VARCHAR(64) PRIMARY KEY, deck_id INT NOT NULL, title VARCHAR(160) NOT NULL,
        revision INT NOT NULL, inventory_sha256 VARCHAR(64) NOT NULL,
        document_json $text NOT NULL, inventory_json $text NOT NULL, created_at VARCHAR(19) NOT NULL, updated_at VARCHAR(19) NOT NULL
    )" . $engine,
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS sx_ygo_created_combo_revisions (
        combo_id VARCHAR(64) NOT NULL, revision INT NOT NULL, snapshot_json $text NOT NULL,
        created_at VARCHAR(19) NOT NULL, PRIMARY KEY(combo_id, revision)
    )" . $engine,
        );
    }
}
