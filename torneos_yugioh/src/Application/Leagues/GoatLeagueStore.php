<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Leagues;

interface GoatLeagueStore
{
    public function legacySeasonId(): int;

    public function division(int $seasonId): array;

    public function ensureDivision(int $seasonId): void;

    public function leagueId(int $seasonId): int;

    public function link(int $seasonId, int $divisionId, int $leagueId): int;

    public function qualifierIds(int $seasonId): array;

    public function disableQualifiers(array $ids): void;

    public function audit(int $divisionId, int $leagueId, array $qualifierIds): void;
}
