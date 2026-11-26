<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Commands;

interface OfflineFormStore
{
    public function result(string $requestId): ?string;
    public function updatedAt(string $action, int $id): string;
    public function record(string $requestId, string $hash, string $result): void;
}
