<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface ManualHonorStore
{
    public function currentSeasonId(): int;
    public function lockEvent(int $eventId, int $seasonId): array;
    public function hasHonor(int $eventId): bool;
    public function participants(int $eventId): array;
    public function validateRoster(string $ruleset, array $ids): void;
    public function team(int $deck): int;
    public function record(array $values): void;
    public function audit(int $eventId, int $champion, int $team, string $notes): void;
}
