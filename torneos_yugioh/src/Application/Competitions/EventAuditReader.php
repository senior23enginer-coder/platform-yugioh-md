<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface EventAuditReader
{
    public function snapshot(int $id): array;

    public function event(int $id): array|false;

    public function history(int $id, int $before, string $search): array;
}
