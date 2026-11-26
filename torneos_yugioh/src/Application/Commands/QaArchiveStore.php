<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Commands;

interface QaArchiveStore
{
    public function currentSeasonId(): int;
    public function archive(int $seasonId): array;
    public function audit(int $seasonId, array $counts): void;
}
