<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface StandingSnapshotWriter
{
    public function clear(int $competitionId, string $snapshotKey): void;

    public function upsert(int $seasonId, int $competitionId, string $snapshotKey, int $round, iterable $rows): array;

    public function deleteAbsent(int $competitionId, string $snapshotKey, array $seenDeckIds): void;
}
