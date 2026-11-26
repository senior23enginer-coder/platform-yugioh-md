<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface CompetitionCodeStore
{
    public function codes(int $seasonId, int $ignoreId): array;

    public function exists(int $seasonId, string $code, int $ignoreId): bool;

    public function competitions(int $seasonId): array;

    public function update(int $seasonId, int $competitionId, string $code): void;
}
