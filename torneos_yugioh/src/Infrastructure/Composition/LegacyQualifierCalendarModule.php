<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use DateTimeZone;
use PDO;
use StationX\Yugioh\Application\Calendar\RebuildLegacyQualifierCalendar;
use StationX\Yugioh\Infrastructure\Persistence\Calendar\PdoLegacyQualifierCalendarReader;
use StationX\Yugioh\Infrastructure\Persistence\Calendar\PdoLegacyQualifierCalendarWriter;
use StationX\Yugioh\Infrastructure\Time\SystemClock;

final class LegacyQualifierCalendarModule
{
    public static function create(PDO $pdo): RebuildLegacyQualifierCalendar
    {
        return new RebuildLegacyQualifierCalendar(
            new PdoLegacyQualifierCalendarReader($pdo),
            new PdoLegacyQualifierCalendarWriter($pdo),
            new SystemClock(new DateTimeZone(date_default_timezone_get())),
        );
    }
}
