<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Matches;

interface ManualFriendlyStore
{
    public function find(int $id): array;
    public function delete(int $id): void;
    public function audit(int $id, array $match): void;
}
