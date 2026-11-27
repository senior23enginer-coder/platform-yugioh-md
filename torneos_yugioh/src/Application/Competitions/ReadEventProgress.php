<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

use StationX\Yugioh\Domain\Competitions\EventProgressPolicy;

final class ReadEventProgress
{
    public function __construct(private readonly EventProgressReader $reader)
    {
    }

    public function execute(): array
    {
        return EventProgressPolicy::group($this->reader->rounds());
    }
}
