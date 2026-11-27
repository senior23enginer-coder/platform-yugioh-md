<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

use StationX\Yugioh\Application\Shared\Clock;

final readonly class ScheduleAdmissionStage
{
    public function __construct(
        private AdmissionConfigurationStore $configuration,
        private AdmissionStageStore $store,
        private Clock $clock,
    ) {}

    public function execute(
        array $c,
        string $start,
        string $pattern,
        int $perDay,
        ?string $phase = null,
        ?int $round = null,
    ): void {
        if ($this->configuration->load($c) === null) {
            return;
        }
        $ids = $this->store->pendingIds($c, $phase, $round);
        if (!$ids) {
            return;
        }
        $this->store->clearDates($ids);
        $last = $this->store->lastDate($c);
        $base = max(
            $start,
            $this->clock->now()->format('Y-m-d'),
            $last ? $this->store->nextDate($last, $pattern, 1, 1) : '',
        );
        foreach ($ids as $i => $mid) {
            $date = $this->store->nextDate($base, $pattern, $i, $perDay);
            [$date, $time] = $this->store->slot($c, $mid, $date, $pattern, $perDay);
            $this->store->updateSlot($mid, $date, $time);
        }
        $this->store->validateSeason((int) $c['id']);
    }
}
