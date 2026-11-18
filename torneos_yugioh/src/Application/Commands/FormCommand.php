<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Commands;

/** Captured command data; this object does not read the request or PHP globals. */
final readonly class FormCommand
{
    public function __construct(
        public string $action,
        public array $input,
        public array $files = [],
    ) {}
}
