<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Teams;

interface TeamResultServices
{
    public function competitionForMatch(int $matchId): array;

    public function kind(array $competition): string;

    public function requireOpen(int $seasonId): void;

    public function assertEditable(int $matchId): void;

    public function validateDate(int $matchId): array;

    public function workflow(array $competition): ?array;

    public function evaluate(int $matchId, array $sequence): array;

    public function finalize(int $matchId, string $now): void;

    public function standings(int $seasonId, int $competitionId): void;

    public function sync(int $matchId): void;
}
