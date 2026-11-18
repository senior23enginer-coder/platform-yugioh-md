<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Seasons;

use StationX\Yugioh\Domain\Seasons\Season;

/** Read port: adapters must return the newest season first; the use case never sees PDO. */
interface SeasonRepository
{
    /** @return iterable<Season> */
    public function newestFirst(): iterable;
}
