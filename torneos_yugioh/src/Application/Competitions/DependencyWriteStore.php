<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface DependencyWriteStore
{
    public function currentSeasonId(): int;

    public function save(int $seasonId, array $input): void;

    public function remove(int $id): void;

    public function audit(string $entity, int $id, string $action, array $payload): void;
}
