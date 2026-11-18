<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\DeckMetadata;

use StationX\Yugioh\Domain\DeckMetadata\DeckMetadataResolver;
use StationX\Yugioh\Domain\DeckMetadata\DeckStudyProfile;

/** Enriches an already-loaded catalog. No SQL, globals, file paths or inventory mutations. */
final readonly class BuildDeckListMetadata
{
    public function __construct(private DeckStudyReader $studies) {}

    /**
     * @param array<array-key, array<string, mixed>> $decks Preserve native keys and order.
     * @param list<array<string, mixed>> $cards All saved zones; unnamed/nonpositive rows do not count.
     * @param list<array<string, mixed>> $profiles Native ordering: first non-retired profile wins.
     * @return array<array-key, array<string, mixed>>
     */
    public function execute(array $decks, array $cards, array $profiles): array
    {
        $snapshot = $this->studies->read(
            array_values(array_map(static fn(array $deck): int => (int) $deck['id'], $decks)),
        );
        $counts = [];
        foreach ($cards as $card) {
            if (
                trim((string) ($card['card_name'] ?? '')) !== '' &&
                (int) ($card['quantity'] ?? 0) > 0
            ) {
                $id = (int) $card['deck_id'];
                $counts[$id] = ($counts[$id] ?? 0) + (int) $card['quantity'];
            }
        }
        $manual = [];
        foreach ($profiles as $profile) {
            $id = (int) $profile['deck_id'];
            if ($snapshot->excludesProfile($id, (int) ($profile['revision'] ?? 0))) {
                continue;
            }
            // A blank first profile still wins over later profiles, matching the native ordering.
            if (!isset($manual[$id])) {
                $manual[$id] = DeckMetadataResolver::description($profile['play_style'] ?? '');
            }
        }
        foreach ($decks as $key => $deck) {
            $id = (int) $deck['id'];
            $metadata = DeckMetadataResolver::resolve(
                (string) $deck['name'],
                $deck['style'] ?? '',
                $deck['archetype'] ?? '',
                $manual[$id] ?? '',
                $snapshot->profiles[$id] ?? new DeckStudyProfile(),
                $counts[$id] ?? 0,
            );
            $deck['display_style'] = $metadata->style;
            $deck['display_archetype'] = $metadata->archetype;
            $deck['card_count'] = $metadata->cardCount;
            $deck['has_cards'] = $metadata->hasCards();
            $decks[$key] = $deck;
        }
        return $decks;
    }
}
