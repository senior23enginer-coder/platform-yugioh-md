<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\News;

use PDO;
use StationX\Yugioh\Application\News\NewsReaderActions;

final readonly class PdoNewsReaderActions implements NewsReaderActions
{
    public function __construct(private PDO $pdo) {}
    public function read(int $id, bool $value): void
    {
        $this->pdo
            ->prepare(
                'UPDATE sx_ygo_news_items SET read_at=' .
                    ($value ? 'UTC_TIMESTAMP()' : 'NULL') .
                    ' WHERE id=?',
            )
            ->execute([$id]);
    }

    public function bookmark(int $id, bool $value): void
    {
        $this->pdo
            ->prepare(
                'UPDATE sx_ygo_news_items SET bookmarked=' . ($value ? '1' : '0') . ' WHERE id=?',
            )
            ->execute([$id]);
    }

    public function readAll(): void
    {
        $this->pdo->exec(
            'UPDATE sx_ygo_news_items SET read_at=UTC_TIMESTAMP() WHERE read_at IS NULL',
        );
    }
}
