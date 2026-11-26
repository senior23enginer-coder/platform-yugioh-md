<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface CompetitionMaintenanceStore
{
    public function currentSeasonId(): int;
    public function archive(int $id): void;
    public function qualifierStatus(int $id, int $seasonId): string;
    public function setQualifierStatus(int $id, int $seasonId, string $status): void;
    public function rankingExists(int $id, int $seasonId): bool;
    public function typeExists(int $id, int $seasonId, string $type): bool;
    public function qualifierDivisions(int $id, int $seasonId): array;
    public function competition(int $id, int $seasonId): array;
    public function complete(int $id, int $seasonId): void;
    public function audit(int $id, string $action, array $payload): void;
}
