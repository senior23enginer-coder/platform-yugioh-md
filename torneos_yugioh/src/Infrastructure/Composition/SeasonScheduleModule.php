<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use DateTimeZone;
use PDO;
use StationX\Yugioh\Application\Seasons\ValidateSeasonSchedule;
use StationX\Yugioh\Infrastructure\Persistence\Seasons\PdoSeasonScheduleReader;
use StationX\Yugioh\Infrastructure\Time\SystemClock;

final class SeasonScheduleModule
{
    public static function create(PDO $pdo): ValidateSeasonSchedule
    {
        return new ValidateSeasonSchedule(
            new PdoSeasonScheduleReader($pdo),
            new SystemClock(new DateTimeZone(date_default_timezone_get())),
        );
    }
}
