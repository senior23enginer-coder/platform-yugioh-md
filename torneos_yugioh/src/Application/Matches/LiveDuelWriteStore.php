<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Matches;

/** Write operations run within the transaction opened by the native request adapter. */
interface LiveDuelWriteStore
{
    public function requireOpen(int $matchId, bool $forUpdate): void;

    public function update(int $matchId, array $state, int $revision, string $actor): void;

    public function recordSurrender(int $matchId, array $state, string $actor): void;
}
