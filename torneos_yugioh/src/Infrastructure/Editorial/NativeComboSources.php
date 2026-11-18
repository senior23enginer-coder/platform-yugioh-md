<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Editorial;

use StationX\Yugioh\Application\Combos\ComboSources;

/** Adapts the existing verified editorial registry and Markdown renderer to outgoing ports. */
final class NativeComboSources implements ComboSources
{
    public function context(int $deckId): array
    {
        return \ygo_combo_context($deckId);
    }

    public function reference(array $input, int $deckId): array
    {
        return \ygo_combo_source_reference($input, $deckId);
    }

    public function markdown(array $record): string
    {
        return \ygo_combo_source_markdown($record);
    }
}
