<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Calendar;

interface MatchScheduleValidationReader
{
    public function settings(): array;

    public function participants(int $matchId, array $explicitIds): array;

    public function freeForAll(string $date): array;

    public function availableFrom(array $deckIds): string;

    public function busyMatches(string $date, int $matchId): array;

    public function pendingCount(string $date, int $matchId): int;

    public function canDisplaceQualifier(int $matchId, string $date): bool;
}
