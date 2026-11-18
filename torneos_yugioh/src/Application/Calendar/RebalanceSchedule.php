<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Calendar;

/** Preserves priority: clear qualifier dates, reflow the requested event, then reflow qualifiers. */
final readonly class RebalanceSchedule
{
    public function __construct(
        private QualifierScheduleReader $reader,
        private QualifierScheduleWriter $writer,
        private ReflowPendingSchedule $reflow,
        private ReflowActiveQualifiers $qualifiers,
        private CalendarTime $time,
    ) {}

    public function execute(int $competitionId): void
    {
        $competition = $this->reader->competitionConfiguration($competitionId);
        if (!$competition) {
            return;
        }
        $type = (string) ($competition['type'] ?? '');
        $start =
            (string) ($competition['starts_on'] ?? '' ?:
            $this->time->parse('today')->format('Y-m-d'));
        $pattern = (string) ($competition['schedule_pattern'] ?? '' ?: 'consecutive');
        $daily = max(1, (int) ($competition['qualifier_matches_per_day'] ?? 1));
        if ($type !== 'qualifier') {
            $this->writer->clearPendingDatesExcept(0);
        }
        $this->reflow->execute($competitionId, $start, $pattern, $daily);
        if ($type !== 'qualifier') {
            $this->qualifiers->execute();
        }
    }
}
