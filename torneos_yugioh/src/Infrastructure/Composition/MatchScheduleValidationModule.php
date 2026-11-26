<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use DateTimeZone;
use PDO;
use StationX\Yugioh\Application\Calendar\ValidateMatchSchedule;
use StationX\Yugioh\Application\Calendar\ValidateResultDate;
use StationX\Yugioh\Infrastructure\Persistence\Calendar\PdoMatchScheduleValidationReader;
use StationX\Yugioh\Infrastructure\Time\SystemClock;

final class MatchScheduleValidationModule
{
    public static function result(PDO $pdo): ValidateResultDate
    {
        return new ValidateResultDate(
            new PdoMatchScheduleValidationReader($pdo),
            new SystemClock(new DateTimeZone(date_default_timezone_get())),
        );
    }

    public static function schedule(PDO $pdo): ValidateMatchSchedule
    {
        return new ValidateMatchSchedule(new PdoMatchScheduleValidationReader($pdo));
    }
}
