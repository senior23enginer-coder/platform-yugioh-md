<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Statistics;

interface StatisticsViewReader
{
    public function installed(): bool;

    public function previousPayload(): ?string;

    public function publications(bool $full): array;

    public function history(): array;
}
