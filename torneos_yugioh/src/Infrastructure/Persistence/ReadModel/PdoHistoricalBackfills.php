<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\ReadModel;

use PDO;
use StationX\Yugioh\Application\Bootstrap\HistoricalBackfillPort;

final readonly class PdoHistoricalBackfills implements HistoricalBackfillPort
{
    public function __construct(private PDO $pdo) {}

    public function completed(string $version): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM sx_ygo_audit_log WHERE action = ?');
        $stmt->execute([$version]);
        return (int) $stmt->fetchColumn() !== 0;
    }

    public function record(string $version, array $counts): void
    {
        $this->pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
            ->execute(['system', 0, $version, json_encode($counts, JSON_UNESCAPED_UNICODE)]);
    }

    public function syncMedals(): int
    {
        return \ygo_sync_medal_unlocks($this->pdo);
    }

    public function backfillSnapshots(): int
    {
        return \ygo_backfill_match_snapshots($this->pdo);
    }

    public function syncMeta(): int
    {
        return \ygo_sync_meta_history($this->pdo);
    }

    public function syncElo(): int
    {
        return \ygo_sync_elo_history($this->pdo);
    }

    public function syncRanking(): void
    {
        \ygo_sync_global_ranking_history($this->pdo);
    }

    public function syncSummaries(): void
    {
        \ygo_sync_event_summaries($this->pdo);
    }
}
