<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface CompetitionDeletionStore
{
    public function currentSeasonId(): int;

    public function competition(int $seasonId, int $competitionId): array;

    public function delete(int $seasonId, int $competitionId, string $status): void;
}
