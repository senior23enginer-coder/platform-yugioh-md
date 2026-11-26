<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Calendar;

interface LegacyQualifierCalendarReader
{
    public function currentSeasonId(): int;

    public function firstDivisionId(): int;

    public function selectedQualifierId(): ?int;

    public function activeDeckIds(): array;
}
