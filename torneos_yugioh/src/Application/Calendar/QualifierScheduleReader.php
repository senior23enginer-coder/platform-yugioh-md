<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Calendar;

interface QualifierScheduleReader
{
    /** Active qualifiers in native order and the currently selected season. */
    public function activeQualifiers(): array;
    public function competitionConfiguration(int $competitionId): ?array;
}
