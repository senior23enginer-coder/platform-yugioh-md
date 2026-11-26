<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Commands;

interface LogoHistoryStore
{
    public function contains(string $entity, int $id, string $url): bool;

    public function select(string $entity, int $id, string $url): void;

    public function record(string $entity, int $id, string $url): void;
}
