<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\News;

use PDO;
use PDOException;

/** Explicit native schema initialization; composition and constructors never create tables. */
final class NewsSchema
{
    public static function ensure(PDO $pdo): void
    {
        try {
            if (
                (int) $pdo->query('SELECT COUNT(*) FROM sx_ygo_news_sources')->fetchColumn() ===
                count(
                    (new \StationX\Yugioh\Infrastructure\Configuration\News\NativeNewsProviders())->all(),
                )
            ) {
                return;
            }
        } catch (PDOException $e) {
            if ($e->getCode() !== '42S02') {
                throw $e;
            }
        }
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS sx_ygo_news_sources (source_key VARCHAR(32) PRIMARY KEY,last_attempt_at DATETIME NULL,last_success_at DATETIME NULL,next_attempt_at DATETIME NULL,last_error VARCHAR(300) NULL,last_imported INT NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS sx_ygo_news_items (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,source_key VARCHAR(32) NOT NULL,url_hash CHAR(64) NOT NULL UNIQUE,url TEXT NOT NULL,title VARCHAR(350) NOT NULL,excerpt TEXT NOT NULL,image_url TEXT NULL,published_at DATETIME NULL,date_precision VARCHAR(12) NOT NULL DEFAULT 'unknown',format_code VARCHAR(24) NOT NULL,topic VARCHAR(24) NOT NULL,content_hash CHAR(64) NOT NULL,first_seen_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,read_at DATETIME NULL,bookmarked TINYINT NOT NULL DEFAULT 0,INDEX(source_key,published_at),INDEX(format_code,topic),INDEX(first_seen_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        );
        foreach (
            array_keys(
                (new \StationX\Yugioh\Infrastructure\Configuration\News\NativeNewsProviders())->all(),
            )
            as $key
        ) {
            $s = $pdo->prepare('INSERT IGNORE INTO sx_ygo_news_sources(source_key) VALUES(?)');
            $s->execute([$key]);
        }
    }
}
