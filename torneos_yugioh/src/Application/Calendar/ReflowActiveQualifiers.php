<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Calendar;

/** Qualifiers keep their configured minimum start; earlier dates are raised to the supplied minimum. */
final readonly class ReflowActiveQualifiers
{
    public function __construct(
        private QualifierScheduleReader $reader,
        private ReflowPendingSchedule $reflow,
        private CalendarTime $time,
    ) {}

    public function execute(?string $minimumStart = null): array
    {
        $competitions = $this->reader->activeQualifiers();
        $today = $this->time->parse($minimumStart ?: 'today');
        $results = [];
        foreach ($competitions as $competition) {
            $configured = !empty($competition['starts_on'])
                ? $this->time->parse((string) $competition['starts_on'])
                : $today;
            $start = $configured > $today ? $configured : $today;
            $results[(int) $competition['id']] = $this->reflow->execute(
                (int) $competition['id'],
                $start->format('Y-m-d'),
                (string) ($competition['schedule_pattern'] ?? '' ?: 'consecutive'),
                max(1, (int) ($competition['qualifier_matches_per_day'] ?? 2)),
            );
        }
        return $results;
    }
}
