<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Calendar;

/** Application entry points without transport or PDO types. */
final readonly class CalendarServices
{
    public function __construct(
        public ReflowPendingSchedule $reflow,
        public AssignExplicitSchedule $explicit,
        public ScheduleReader $reader,
        public ScheduleCapacity $capacity,
        public ReflowActiveQualifiers $qualifiers,
        public RebalanceSchedule $rebalance,
    ) {}
}
