<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface LegacyEventFixtureWriter
{
    public function clearPhase(int $divisionId, string $phase): void;

    public function insert(int $divisionId, string $phase, iterable $rows): int;

    public function insertKnockout(int $divisionId, string $phase, iterable $rows): int;
}
