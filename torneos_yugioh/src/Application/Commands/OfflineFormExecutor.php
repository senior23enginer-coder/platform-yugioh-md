<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Commands;

interface OfflineFormExecutor
{
    public function execute(array $payload): string;
}
