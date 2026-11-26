<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Leagues;

interface DivisionMovementWriter
{
    public function saveConfiguration(int $competitionId, array $rows): void;

    public function apply(int $competitionId, array $moves): void;
}
