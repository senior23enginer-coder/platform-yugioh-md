<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Matches;

interface ManualMatchStore
{
    public function firstDivision(): int;
    public function event(int $id, bool $active): array;
    public function nextFriendlyTable(): int;
    public function save(array $values): void;
    public function audit(string $action, array $payload): void;
}
