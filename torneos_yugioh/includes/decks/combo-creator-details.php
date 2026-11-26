<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/autoload.php';

/** Optional manual-study fields; no interpretation of card effects. */
function ygo_combo_context(int $deckId): array
{
    return (new \StationX\Yugioh\Infrastructure\Editorial\NativeComboSources())->context($deckId);
}

function ygo_combo_details(array $node, array $cards, string $format): array
{
    return \StationX\Yugioh\Domain\Combos\ComboStepPolicy::details($node, $cards, $format);
}
