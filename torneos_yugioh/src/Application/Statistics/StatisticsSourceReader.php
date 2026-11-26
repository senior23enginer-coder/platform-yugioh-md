<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Statistics;

interface StatisticsSourceReader
{
    public function events(): array;

    public function fixedMedals(): array;

    public function matches(): array;

    public function snapshots(): array;

    public function collective(): array;

    public function honors(): array;

    public function seasons(): array;

    public function freeForAll(): array;
}
