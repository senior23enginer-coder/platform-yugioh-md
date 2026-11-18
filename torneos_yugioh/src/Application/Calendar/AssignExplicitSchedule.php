<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Calendar;

use StationX\Yugioh\Domain\Calendar\PendingSchedulePlanner;

/** Keeps quota order and partial-failure semantics without independently controlling a transaction. */
final readonly class AssignExplicitSchedule
{
    public function __construct(
        private ScheduleReader $reader,
        private ScheduleWriter $writer,
        private ScheduleCapacity $capacity,
        private ScheduleEvents $events,
        private CalendarTime $time,
    ) {}

    public function execute(int $competitionId, array $dateQuotas): array
    {
        $matchIds = $this->reader->pendingMatchIds($competitionId);
        $this->writer->clearPendingDates($competitionId);
        $occupancy = $this->reader->occupancy($competitionId);
        $limit = $this->capacity->limit();
        $plan = PendingSchedulePlanner::explicit(
            $matchIds,
            $dateQuotas,
            $limit,
            $occupancy,
            $this->time->evening(),
        );
        foreach ($plan as $slot) {
            $this->writer->assign($competitionId, $slot['match_id'], $slot['date'], $slot['time']);
        }
        $summary = $plan->getReturn();
        if ($summary['end'] !== null) {
            $this->writer->setEndDate($competitionId, $summary['end']);
        }
        $this->events->rebuild($competitionId, $this->reader->competitionType($competitionId));
        return $summary;
    }
}
