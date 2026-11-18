<?php
declare(strict_types=1);
namespace StationX\Yugioh\Application\Seasons;

use StationX\Yugioh\Domain\Seasons\Season;

/** Select an active season, rather than mistaking an event name for the season. */
final readonly class GetCurrentSeason
{
    public function __construct(private SeasonRepository $seasons) {}

    public function execute(): ?Season
    {
        foreach ($this->seasons->newestFirst() as $season) {
            if ($season->isActive()) return $season;
        }
        return null;
    }
}
