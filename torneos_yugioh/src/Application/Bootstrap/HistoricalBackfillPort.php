<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Bootstrap;

interface HistoricalBackfillPort
{
    public function completed(string $version): bool;
    public function record(string $version, array $counts): void;
    public function syncMedals(): int;
    public function backfillSnapshots(): int;
    public function syncMeta(): int;
    public function syncElo(): int;
    public function syncRanking(): void;
    public function syncSummaries(): void;
}
