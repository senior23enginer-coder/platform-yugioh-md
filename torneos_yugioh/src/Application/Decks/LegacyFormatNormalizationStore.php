<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decks;

interface LegacyFormatNormalizationStore
{
    public function alreadyNormalized(): bool;

    public function currentTcgDivision(): int;

    public function normalize(int $divisionId): int;

    public function record(int $divisionId, int $updated): void;
}
