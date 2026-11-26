<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Statistics;

use StationX\Yugioh\Domain\Statistics\StatisticsSourceProjection;

final readonly class ReadStatisticsSource
{
    public function __construct(private StatisticsSourceReader $reader) {}

    public function execute(): array
    {
        return StatisticsSourceProjection::build(
            $this->reader->events(),
            $this->reader->fixedMedals(),
            $this->reader->matches(),
            $this->reader->snapshots(),
            $this->reader->collective(),
            $this->reader->honors(),
            $this->reader->seasons(),
            $this->reader->freeForAll(),
        );
    }
}
