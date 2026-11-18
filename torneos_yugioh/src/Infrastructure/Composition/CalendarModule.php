<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\Calendar\AssignExplicitSchedule;
use StationX\Yugioh\Application\Calendar\CalendarServices;
use StationX\Yugioh\Application\Calendar\CalendarTime;
use StationX\Yugioh\Application\Calendar\RebalanceSchedule;
use StationX\Yugioh\Application\Calendar\ReflowActiveQualifiers;
use StationX\Yugioh\Application\Calendar\ReflowPendingSchedule;
use StationX\Yugioh\Application\Calendar\ScheduleEvents;
use StationX\Yugioh\Infrastructure\Persistence\Calendar\NativeScheduleCapacity;
use StationX\Yugioh\Infrastructure\Persistence\Calendar\NativeScheduleEvents;
use StationX\Yugioh\Infrastructure\Persistence\Calendar\PdoQualifierSchedules;
use StationX\Yugioh\Infrastructure\Persistence\Calendar\PdoScheduleReader;
use StationX\Yugioh\Infrastructure\Persistence\Calendar\PdoScheduleWriter;
use StationX\Yugioh\Infrastructure\Time\NativeCalendarTime;

/** Composition only: no query, schema creation, write lock or transaction begins here. */
final class CalendarModule
{
    public static function create(
        PDO $pdo,
        ?ScheduleEvents $events = null,
        ?CalendarTime $time = null,
    ): CalendarServices {
        $reader = new PdoScheduleReader($pdo);
        $writer = new PdoScheduleWriter($pdo);
        $capacity = new NativeScheduleCapacity($pdo);
        $events ??= new NativeScheduleEvents($pdo);
        $time ??= new NativeCalendarTime();
        $reflow = new ReflowPendingSchedule($reader, $writer, $capacity, $events, $time);
        $qualifierStore = new PdoQualifierSchedules($pdo);
        $qualifiers = new ReflowActiveQualifiers($qualifierStore, $reflow, $time);
        return new CalendarServices(
            $reflow,
            new AssignExplicitSchedule($reader, $writer, $capacity, $events, $time),
            $reader,
            $capacity,
            $qualifiers,
            new RebalanceSchedule($qualifierStore, $qualifierStore, $reflow, $qualifiers, $time),
        );
    }
}
