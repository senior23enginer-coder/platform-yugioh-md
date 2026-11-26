<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface QualifierFinalizationReader
{
    public function postedQualifier(int $id): int;

    public function defaultQualifier(int $seasonId): int;

    public function currentSeasonId(): int;

    public function requiredMatches(int $competitionId): mixed;

    public function matches(int $competitionId): array;

    public function divisions(int $seasonId): array;

    public function standings(int $competitionId): array;
}
