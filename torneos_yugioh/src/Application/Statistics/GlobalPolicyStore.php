<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Statistics;

interface GlobalPolicyStore
{
    public function inTransaction(): bool;

    public function exists(): bool;

    public function policy(): ?string;

    public function save(array $policy): void;
}
