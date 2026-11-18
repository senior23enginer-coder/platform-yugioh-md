<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Calendar;

use StationX\Yugioh\Domain\Calendar\PendingSchedulePlanner;

/** Preserves native read/write order and the caller's lock and transaction boundaries. */
final readonly class ReflowPendingSchedule
{
    public function __construct(
        private ScheduleReader $reader,
        private ScheduleWriter $writer,
        private ScheduleCapacity $capacity,
        private ScheduleEvents $events,
        private CalendarTime $time,
    ) {}

    public function execute(
        int $competitionId,
        string $startDate,
        string $pattern,
        int $eventDailyLimit,
    ): array {
        $competition = $this->reader->activeCompetition($competitionId);
        if (!$competition) {
            return ['scheduled' => 0, 'start' => null, 'end' => null];
        }
        $matchIds = $this->reader->pendingMatchIds($competitionId);
        if (!$matchIds) {
            return ['scheduled' => 0, 'start' => null, 'end' => null];
        }
        $this->writer->clearPendingDates($competitionId);
        $limit = $this->capacity->limit();
        $occupancy = $this->reader->occupancy($competitionId);
        $blackouts = $this->reader->blackouts($competitionId);
        $start = $this->time->parse($startDate);
        $plan = PendingSchedulePlanner::automatic(
            $matchIds,
            $start,
            $pattern,
            $limit,
            $eventDailyLimit,
            $occupancy,
            $blackouts,
            $this->time->evening(),
        );
        foreach ($plan as $slot) {
            $this->writer->assign($competitionId, $slot['match_id'], $slot['date'], $slot['time']);
        }
        $summary = $plan->getReturn();
        if ($summary['end'] !== null) {
            $this->writer->setEndDate($competitionId, $summary['end']);
        }
        $this->events->rebuild($competitionId, (string) ($competition['type'] ?? 'match'));
        return $summary;
    }
}
