<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Presentation;

final class DeckFormatProjection
{
    public static function options(array $rulesets, array $eventRulesetOptions): array
    {
        $rulesetSelectOptions = [];
        foreach ($rulesets as $ruleset) {
            $rulesetSelectOptions[(string) ($ruleset['code'] ?? '')] =
                (string) ($ruleset['label'] ?? ($ruleset['code'] ?? ''));
        }
        if (!$rulesetSelectOptions) {
            $rulesetSelectOptions = $eventRulesetOptions;
        }
        $formatSelectOptions = array_filter(
            $rulesetSelectOptions,
            static fn(string $label, string $code): bool => $code !== '' && $code !== 'custom',
            ARRAY_FILTER_USE_BOTH,
        );
        return [$rulesetSelectOptions, $formatSelectOptions];
    }

    public static function assign(
        array $allDecks,
        array $formatSelectOptions,
        ?callable $deckIdsForFormat,
    ): array {
        $deckFormatCodesById = [];
        if ($deckIdsForFormat !== null) {
            foreach ($formatSelectOptions as $formatCode => $formatLabel) {
                foreach ($deckIdsForFormat((string) $formatCode) as $formatDeckId) {
                    $deckFormatCodesById[$formatDeckId][] = (string) $formatCode;
                }
            }
        }
        foreach ($allDecks as &$formatDeck) {
            $formatDeck['format_codes'] = array_values(
                array_unique($deckFormatCodesById[(int) ($formatDeck['id'] ?? 0)] ?? []),
            );
        }
        unset($formatDeck);
        return [$deckFormatCodesById, $allDecks];
    }
}
