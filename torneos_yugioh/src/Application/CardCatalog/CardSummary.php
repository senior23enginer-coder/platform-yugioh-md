<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\CardCatalog;

/** Stable inspector/editor projection of exact provider text. */
final class CardSummary
{
    public static function fromProvider(array $card): array
    {
        return [
            'id' => (int) $card['id'],
            'name' => (string) $card['name'],
            'type' => (string) ($card['type'] ?? ''),
            'description' => (string) ($card['desc'] ?? ''),
            'race' => (string) ($card['race'] ?? ''),
            'attribute' => (string) ($card['attribute'] ?? ''),
            'atk' => $card['atk'] ?? null,
            'def' => $card['def'] ?? null,
            'level' => $card['level'] ?? null,
            'image' => './?api=deck_builder&op=image&id=' . (int) $card['id'],
        ];
    }
}
