<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface AdmissionStageStore
{
    public function pendingIds(array $competition, ?string $phase, ?int $round): array;

    public function clearDates(array $ids): void;

    public function lastDate(array $competition): string|false|null;

    public function nextDate(string $base, string $pattern, int $index, int $perDay): string;

    public function slot(
        array $competition,
        int $matchId,
        string $date,
        string $pattern,
        int $perDay,
    ): array;

    public function updateSlot(int $matchId, string $date, string $time): void;

    public function validateSeason(int $competitionId): void;
}
