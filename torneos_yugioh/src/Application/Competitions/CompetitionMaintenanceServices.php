<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface CompetitionMaintenanceServices
{
    public function selectedQualifier(int $postedId): ?int;
    public function finalizeQualifier(?int $id, int $postedId): bool;
    public function history(int $seasonId, int $id, string $from, string $to, string $action, array $payload = []): void;
    public function standings(int $seasonId, int $id, string $reason): void;
    public function applyDivision(int $id): int;
    public function leagueMovements(int $id): int;
    public function honor(int $id): int;
    public function readiness(int $id): array;
}
