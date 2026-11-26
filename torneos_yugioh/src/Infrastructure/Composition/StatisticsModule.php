<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use StationX\Yugioh\Application\Statistics\CalculateStatistics;
use StationX\Yugioh\Application\Shared\Clock;
use StationX\Yugioh\Infrastructure\Time\SystemClock;

final class StatisticsModule
{
    public static function replay(?Clock $clock = null): CalculateStatistics
    {
        return new CalculateStatistics($clock ?? new SystemClock());
    }
}
