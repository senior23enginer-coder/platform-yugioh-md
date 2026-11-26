<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface KnockoutRoundStore
{
    public function exists(int $competitionId, string $phase, int $round): bool;

    public function currentSeasonId(): int;

    public function insert(iterable $rows): int;

    public function auditBye(int $competitionId, int $round, int $deckId): void;
}
