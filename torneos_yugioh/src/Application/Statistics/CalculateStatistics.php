<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Statistics;

use DateTimeZone;
use StationX\Yugioh\Application\Shared\Clock;
use StationX\Yugioh\Domain\Statistics\FreeForAllStatistics;
use StationX\Yugioh\Domain\Statistics\StatisticsReplay;

final readonly class CalculateStatistics
{
    public function __construct(private Clock $clock) {}

    public function execute(
        array $matches,
        array $events = [],
        array $honors = [],
        array $policy = [],
        ?string $cutoff = null,
        array $publishedTiers = [],
        array $ffaMatches = [],
    ): array {
        return StatisticsReplay::calculate($this->cutoff($cutoff), $matches, $events, $honors, $policy, $publishedTiers, $ffaMatches);
    }

    public function freeForAll(array $matches, array $policy = [], ?string $cutoff = null): array
    {
        return FreeForAllStatistics::calculate($this->cutoff($cutoff), $matches, $policy);
    }

    private function cutoff(?string $cutoff): string
    {
        return $cutoff ?? $this->clock->now()->setTimezone(new DateTimeZone('America/Bogota'))->format('Y-m-d H:i:s');
    }
}
