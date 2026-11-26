<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface CompetitionStandingsReader
{
    public function competition(int $competitionId): array;

    public function workflowRanking(array $competition, ?string $phaseFilter): array;

    public function decks(int $competitionId): array;

    public function matches(int $competitionId, ?string $phaseFilter): array;

    public function teamConfiguration(array $competition): ?array;
}
