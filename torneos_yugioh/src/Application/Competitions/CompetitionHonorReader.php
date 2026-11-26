<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface CompetitionHonorReader
{
    public function exists(int $competitionId): bool;

    public function competition(int $competitionId): array;

    public function workflow(array $competition): ?array;

    public function finalResult(int $competitionId): array;

    public function standings(int $competitionId): array;

    public function teamId(int $deckId): int;
}
