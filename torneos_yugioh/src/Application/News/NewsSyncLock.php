<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\News;

interface NewsSyncLock
{
    public function acquire(): bool;
    public function release(): void;
}
