<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Matches;

use StationX\Yugioh\Application\Shared\Clock;
use StationX\Yugioh\Domain\Matches\LiveMatchProjection;

final readonly class ReadLiveMatches
{
    public function __construct(private LiveQueryReader $reader, private Clock $clock) {}

    public function execute(array $fixtures, array $medals): array
    {
        $matches = LiveMatchProjection::matches($fixtures);
        return [
            'server_time' => $this->clock->now()->format(DATE_ATOM),
            'revision' => $this->reader->revision(),
            'matches' => $matches,
            'medal_unlocks' => array_slice($medals, 0, 100),
        ];
    }

    public function ffaHistory(int $id): array
    {
        return ['actions' => $this->reader->ffaHistory($id)];
    }
}
