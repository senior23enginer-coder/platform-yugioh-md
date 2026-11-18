<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Seasons;

/** Season identity independent of its persistence or delivery format. */
final readonly class Season
{
    public function __construct(public int $id, public string $name, public string $status) {}

    public function isActive(): bool
    {
        return $this->status === 'active' && trim($this->name) !== '';
    }
}
