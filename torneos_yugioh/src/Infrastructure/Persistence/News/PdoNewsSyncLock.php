<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\News;

use PDO;
use StationX\Yugioh\Application\News\NewsSyncLock;

/** Same zero-wait connection-scoped MySQL lock as the native synchronizer. */
final readonly class PdoNewsSyncLock implements NewsSyncLock
{
    public function __construct(private PDO $pdo) {}
    public function acquire(): bool
    {
        return (bool) $this->pdo->query("SELECT GET_LOCK('sx_ygo_news_sync',0)")->fetchColumn();
    }

    public function release(): void
    {
        $this->pdo->query("SELECT RELEASE_LOCK('sx_ygo_news_sync')");
    }
}
