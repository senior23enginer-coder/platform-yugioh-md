<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Seasons;

use InvalidArgumentException;
use StationX\Yugioh\Application\Shared\Clock;
use StationX\Yugioh\Domain\Seasons\SeasonSchedulePolicy;

final readonly class ValidateSeasonSchedule
{
    public function __construct(private SeasonScheduleReader $seasons, private Clock $clock) {}

    public function requireOpen(int $id, bool $playing = false, bool $lock = false): array
    {
        $season = $this->seasons->find($id, $lock);
        return SeasonSchedulePolicy::requireOpen(
            $season,
            $playing,
            $this->clock->now()->format('Y-m-d'),
        );
    }

    public function validateCompetition(int $competitionId): void
    {
        $season = $this->seasons->forCompetition($competitionId);
        if (!$season) {
            throw new InvalidArgumentException(
                'El evento debe pertenecer a una temporada existente.',
            );
        }
        $range = $this->seasons->matchDateRange($competitionId);
        SeasonSchedulePolicy::validateEventDates(
            $season,
            $range['first_date'],
            $range['last_date'],
        );
    }
}
