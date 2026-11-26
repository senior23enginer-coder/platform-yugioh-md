<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Calendar;

interface ResultDateReader
{
    public function settings(): array;

    public function match(int $matchId): ?array;

    public function gate(int $competitionId): array;
}
