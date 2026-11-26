<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Calendar;

use StationX\Yugioh\Domain\Calendar\CompetitionCalendarProjection;

final readonly class PublishCompetitionCalendar
{
    public function __construct(
        private CompetitionCalendarReader $reader,
        private CompetitionCalendarWriter $writer,
    ) {}

    public function rebuild(int $seasonId, int $competitionId, string $eventType): int
    {
        if ($competitionId <= 0) {
            return 0;
        }
        $competition = $this->reader->competition($seasonId, $competitionId);
        if (!$competition) {
            return 0;
        }
        $this->writer->deactivateType($seasonId, $competitionId, $eventType);
        $days = $this->reader->scheduledDays($competitionId);
        return $this->writer->upsertJourneys(
            $seasonId,
            $competitionId,
            CompetitionCalendarProjection::journeys(
                $competitionId,
                $eventType,
                $competition,
                $days,
            ),
        );
    }

    public function removeStart(int $seasonId, int $competitionId): void
    {
        if ($competitionId <= 0) {
            return;
        }
        $this->writer->deactivateCode(
            $seasonId,
            $competitionId,
            CompetitionCalendarProjection::startCode($competitionId),
        );
    }
}
