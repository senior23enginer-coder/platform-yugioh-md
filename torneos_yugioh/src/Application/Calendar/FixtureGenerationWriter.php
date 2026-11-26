<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Calendar;

interface FixtureGenerationWriter
{
    public function clearPending(int $competitionId): void;

    public function insertCup(iterable $rows): int;

    public function insertModel(iterable $rows): int;
}
