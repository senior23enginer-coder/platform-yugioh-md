<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Calendar;

use StationX\Yugioh\Application\Shared\Clock;
use StationX\Yugioh\Domain\Calendar\LegacyQualifierCalendar;
use StationX\Yugioh\Domain\Calendar\LegacyQualifierPairings;

final readonly class RebuildLegacyQualifierCalendar
{
    public function __construct(
        private LegacyQualifierCalendarReader $reader,
        private LegacyQualifierCalendarWriter $writer,
        private Clock $clock,
    ) {}

    public function execute(array $input): int
    {
        $calendar = LegacyQualifierCalendar::fromInput(
            $input,
            $this->clock->now()->format('Y-m-d'),
        );
        $seasonId = $this->reader->currentSeasonId();
        $divisionId = $this->reader->firstDivisionId();
        $competitionId = $this->reader->selectedQualifierId();
        if ($competitionId) {
            $this->writer->updateDailyLimit($competitionId, $calendar->matchesPerDay);
        }
        $deckIds = $this->reader->activeDeckIds();
        if (!$divisionId || count($deckIds) < 2) {
            return 0;
        }
        $this->writer->clearPending($seasonId, $competitionId);
        $pairs = LegacyQualifierPairings::build($deckIds);
        $created = $this->writer->insertFixtures(
            $divisionId,
            $competitionId,
            $calendar->fixtures($pairs),
        );
        $days = $calendar->days(count($pairs));
        $this->writer->insertJourneys(
            $seasonId,
            $divisionId,
            $competitionId,
            $days,
            $calendar->startTime,
        );
        $endDate = $calendar->endDate($days, count($pairs));
        if ($competitionId) {
            $this->writer->activate($competitionId, $calendar->startDate, $endDate);
        }
        $this->writer->audit($competitionId, [
            'created' => $created,
            'start' => $calendar->startDate,
            'interval_days' => $calendar->dayInterval,
            'matches_per_day' => $calendar->matchesPerDay,
        ]);
        return $created;
    }
}
