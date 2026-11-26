<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface CompetitionGateReader
{
    public function season(int $competitionId): ?array;

    public function competition(int $competitionId): ?array;

    public function admission(array $competition): ?array;

    public function dependencies(): array;
}
