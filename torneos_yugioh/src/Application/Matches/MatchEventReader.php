<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Matches;

interface MatchEventReader
{
    public function competition(int $matchId): array;

    public function phase(int $matchId): string;

    public function workflow(array $competition): ?array;
}
