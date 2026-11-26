<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Decks;

final class DeckCardInput
{
    public static function single(array $input): ?array
    {
        $deckId = (int) ($input['card_deck_id'] ?? 0);
        $name = trim((string) ($input['card_name'] ?? ''));
        if ($deckId <= 0 || $name === '') {
            return null;
        }
        return [
            'deckId' => $deckId,
            'name' => $name,
            'role' => trim((string) ($input['card_role'] ?? '')),
            'quantity' => max(1, (int) ($input['card_quantity'] ?? 1)),
            'section' => trim((string) ($input['card_section'] ?? 'main')),
            'notes' => trim((string) ($input['card_notes'] ?? '')),
        ];
    }

    public static function parse(int $deckId, string $raw): array
    {
        $valid = [];
        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            $columns = str_getcsv(trim($line), ';');
            if (count($columns) < 3) {
                $columns = str_getcsv(trim($line), ',');
            }
            $section = strtolower(trim((string) ($columns[0] ?? 'main')));
            $quantity = max(1, min(3, (int) ($columns[1] ?? 1)));
            $name = mb_substr(trim((string) ($columns[2] ?? '')), 0, 180);
            if (
                !in_array($section, ['main', 'extra', 'side', 'tech'], true) ||
                $name === '' ||
                strtolower($name) === 'nombre'
            ) {
                continue;
            }
            $valid[] = [
                'deckId' => $deckId,
                'name' => $name,
                'role' => mb_substr(trim((string) ($columns[3] ?? '')), 0, 120),
                'quantity' => $quantity,
                'section' => $section,
                'notes' => mb_substr(trim((string) ($columns[4] ?? '')), 0, 255),
            ];
        }
        return $valid;
    }
}
