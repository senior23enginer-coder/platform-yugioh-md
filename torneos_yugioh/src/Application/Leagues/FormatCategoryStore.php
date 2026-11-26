<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Leagues;

interface FormatCategoryStore
{
    public function managed(int $seasonId): bool;

    public function rename(
        int $seasonId,
        string $oldCode,
        string $newCode,
        array $definition,
        int $tier,
    ): void;

    public function codes(int $seasonId): array;

    public function update(int $seasonId, string $code, array $definition, int $tier): void;
}
