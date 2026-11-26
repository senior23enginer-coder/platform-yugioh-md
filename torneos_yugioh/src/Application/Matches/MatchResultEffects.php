<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Matches;

interface MatchResultEffects
{
    public function setStatus(int $matchId, string $status, string $now): void;

    public function persistStandings(int $competitionId): void;

    public function syncStatistics(int $competitionId, int $matchId): void;

    public function liveMatch(int $matchId): array;

    public function initialLiveState(array $match): array;
}
