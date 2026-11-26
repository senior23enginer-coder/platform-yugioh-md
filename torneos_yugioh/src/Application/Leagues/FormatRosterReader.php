<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Leagues;

interface FormatRosterReader
{
    public function deckIds(string $ruleset): array;

    public function activeFormats(): array;
}
