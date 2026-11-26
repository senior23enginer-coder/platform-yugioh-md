<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Calendar;

use DateTimeImmutable;
use StationX\Yugioh\Application\Competitions\FinalizeQualifier;
use StationX\Yugioh\Application\Shared\Clock;
use StationX\Yugioh\Domain\Calendar\QualifierAutoSchedulePlan;

final readonly class AutoScheduleQualifier
{
    public function __construct(
        private QualifierAutoScheduleStore $store,
        private FinalizeQualifier $selector,
        private Clock $clock,
    ) {}

    public function execute(array $input): int
    {
        $startDate = (string) ($input['qualifier_schedule_start'] ?? $this->clock->now()->format('Y-m-d'));
        $startTime = (string) ($input['qualifier_schedule_time'] ?? '19:00');
        $matchesPerDay = max(1, min(5, (int) ($input['qualifier_matches_per_day'] ?? 1)));
        $minutes = max(20, min(180, (int) ($input['qualifier_schedule_minutes'] ?? 45)));
        $base = DateTimeImmutable::createFromFormat('Y-m-d H:i', $startDate . ' ' . $startTime) ?: $this->clock->now();
        $competitionId = $this->selector->select((int) ($input['qualifier_competition_id'] ?? 0));
        $updated = 0;
        foreach (QualifierAutoSchedulePlan::rows($this->store->pending($competitionId), $base, $matchesPerDay, $minutes) as $row) {
            $this->store->update($competitionId, $row);
            $updated++;
        }
        if ($competitionId) {
            $this->store->updateDailyLimit($competitionId, $matchesPerDay);
        }
        $this->store->audit($competitionId, ['updated' => $updated, 'start' => $startDate, 'time' => $startTime, 'matches_per_day' => $matchesPerDay]);
        return $updated;
    }
}
