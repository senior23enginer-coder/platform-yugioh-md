<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Matches;

interface LiveQueryReader
{
    public function revision(): int;
    public function ffaHistory(int $id): array;
}
