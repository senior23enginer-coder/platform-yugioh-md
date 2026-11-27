<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface EventProgressReader
{
    public function rounds(): array;
}
