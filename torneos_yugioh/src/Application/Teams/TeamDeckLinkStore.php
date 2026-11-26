<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Teams;

interface TeamDeckLinkStore
{
    public function active(int $teamId): bool;

    public function add(int $teamId, array $ids): void;

    public function remove(int $teamId, array $ids): void;

    public function audit(int $teamId, array $add, array $remove): void;
}
