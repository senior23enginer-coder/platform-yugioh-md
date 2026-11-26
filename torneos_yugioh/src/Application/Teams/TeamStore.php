<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Teams;

interface TeamStore
{
    public function findId(string $name): int;

    public function activate(int $id): void;

    public function updateProfile(int $id, array $profile): void;

    public function create(string $name, ?array $profile): int;
}
