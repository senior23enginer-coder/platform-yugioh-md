<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Calendar;

interface FixtureGenerationReader
{
    public function lockCompetition(array $competition): array;

    public function currentSeasonId(): int;

    public function firstDivisionId(): int;

    public function generation(int $competitionId, bool $cup): int;

    public function hasFinalized(int $competitionId): bool;
}
