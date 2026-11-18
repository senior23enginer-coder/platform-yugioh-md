<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\News;

use PDO;
use StationX\Yugioh\Application\News\NewsSourceState;

/** Due-source order, 30-minute refresh and native failure/retry windows remain database-owned. */
final readonly class PdoNewsSourceState implements NewsSourceState
{
    public function __construct(private PDO $pdo) {}
    public function nextDue(bool $retryFailed): ?array
    {
        $retry = $retryFailed
            ? ' OR (last_error IS NOT NULL AND last_attempt_at<=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 MINUTE))'
            : '';
        return $this->pdo
            ->query(
                'SELECT * FROM sx_ygo_news_sources WHERE (next_attempt_at IS NULL OR next_attempt_at<=UTC_TIMESTAMP()' .
                    $retry .
                    ") ORDER BY COALESCE(last_attempt_at,'2000-01-01'),source_key LIMIT 1",
            )
            ->fetch(PDO::FETCH_ASSOC) ?:
            null;
    }

    public function attempted(string $source): void
    {
        $this->pdo
            ->prepare(
                'UPDATE sx_ygo_news_sources SET last_attempt_at=UTC_TIMESTAMP(),next_attempt_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 MINUTE) WHERE source_key=?',
            )
            ->execute([$source]);
    }

    public function succeeded(string $source, int $imported): void
    {
        $this->pdo
            ->prepare(
                'UPDATE sx_ygo_news_sources SET last_success_at=UTC_TIMESTAMP(),last_error=NULL,last_imported=? WHERE source_key=?',
            )
            ->execute([$imported, $source]);
    }

    public function failed(string $source, string $message): void
    {
        $this->pdo
            ->prepare(
                'UPDATE sx_ygo_news_sources SET last_error=?,next_attempt_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 5 MINUTE) WHERE source_key=?',
            )
            ->execute([$message, $source]);
    }
}
