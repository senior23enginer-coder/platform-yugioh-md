<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Combos;

/** Editorial identity and source-version rendering are external to graph validation. */
interface ComboSources
{
    public function context(int $deckId): array;
    public function reference(array $input, int $deckId): array;
    public function markdown(array $record): string;
}
