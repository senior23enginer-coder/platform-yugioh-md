<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\ReadModel;

interface HistoricalReadModelReader
{
    public function statusHistory(): array;

    public function standingSnapshots(): array;

    public function matchSnapshots(): array;

    public function medalUnlocks(): array;

    public function checkins(): array;

    public function metaHistory(): array;

    public function eloHistory(): array;

    public function medalProgress(): array;

    public function rankingHistory(): array;

    public function eventSummaries(): array;

    public function honors(): array;

    public function audit(): array;
}
