<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Statistics;

use LogicException;
use PDO;

final class StatisticsSchema
{
    public static function ensure(PDO $p): void
    {
        if ($p->inTransaction()) {
            throw new LogicException(
                'La instalacion estadistica debe ejecutarse fuera de una transaccion deportiva.',
            );
        }
        $p->exec("CREATE TABLE IF NOT EXISTS sx_ygo_statistics_revisions (
            id BIGINT AUTO_INCREMENT PRIMARY KEY, source_hash CHAR(64) NOT NULL, algorithm_version VARCHAR(60) NOT NULL,
            payload LONGTEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_stats_source (source_hash,algorithm_version)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        \ygo_add_column($p, 'sx_ygo_statistics_revisions', 'previous_revision_id', 'BIGINT NULL');
        \ygo_add_column($p, 'sx_ygo_statistics_revisions', 'change_reason', 'VARCHAR(255) NULL');
        $p->exec("CREATE TABLE IF NOT EXISTS sx_ygo_statistics_publications (
            id BIGINT AUTO_INCREMENT PRIMARY KEY, kind VARCHAR(20) NOT NULL, scope_key VARCHAR(160) NOT NULL,
            cut_at DATETIME NOT NULL, source_hash CHAR(64) NOT NULL, algorithm_version VARCHAR(60) NOT NULL,
            replaces_id BIGINT NULL, reason VARCHAR(255) NOT NULL, payload LONGTEXT NOT NULL,
            published_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_stats_publication (kind,scope_key,cut_at,source_hash,algorithm_version)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        if (!\ygo_column_exists($p, 'sx_ygo_statistics_publications', 'revision_number')) {
            $p->exec(
                'ALTER TABLE sx_ygo_statistics_publications ADD COLUMN revision_number INT NULL',
            );
            $p->exec(
                'ALTER TABLE sx_ygo_statistics_publications DROP INDEX ux_stats_publication, ADD UNIQUE KEY ux_stats_publication(kind,scope_key,cut_at,revision_number)',
            );
        }
        $p->exec("CREATE TABLE IF NOT EXISTS sx_ygo_statistics_policy (
            scope_key VARCHAR(160) PRIMARY KEY, version VARCHAR(60) NOT NULL, payload LONGTEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $p->exec(
            'CREATE TABLE IF NOT EXISTS sx_ygo_statistics_current (id INT PRIMARY KEY, revision_id BIGINT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        );
        $p->exec(
            'CREATE TABLE IF NOT EXISTS sx_ygo_statistics_medals (event_id INT PRIMARY KEY, bonus_points INT NOT NULL, bonus_version VARCHAR(60) NOT NULL, definition_json LONGTEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        );
    }
}
