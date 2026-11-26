<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Seasons;

interface SeasonLifecycleStore
{
    public function find(int $id, bool $lock = false): ?array;

    public function hasOutsideDates(int $id, string $start, string $end): bool;

    public function insert(array $definition): int;

    public function update(int $id, array $definition): void;

    public function openCompetitions(int $id): array;

    public function closeCompetition(int $seasonId, array $competition, string $reason): void;

    public function completeSeason(int $id): void;

    public function expiredIds(string $today): array;

    public function hasDependencies(int $id): bool;

    public function deleteSeason(int $id): void;

    public function audit(int $id, string $action, array $payload): void;
}
