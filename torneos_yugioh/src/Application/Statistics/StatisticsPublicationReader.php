<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Statistics;

interface StatisticsPublicationReader
{
    public function find(int $id): array;
}
