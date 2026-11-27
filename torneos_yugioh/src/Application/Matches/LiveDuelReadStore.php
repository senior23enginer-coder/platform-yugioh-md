<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Matches;

interface LiveDuelReadStore
{
    public function match(int $matchId): array;

    public function state(int $matchId, bool $forUpdate): array;

    public function insertInitial(int $matchId, array $state): void;
}
