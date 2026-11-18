<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native save_deck_card adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class SaveDeckCardHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $deckId = (int) ($post['card_deck_id'] ?? 0);
        $cardName = trim((string) ($post['card_name'] ?? ''));
        if ($deckId > 0 && $cardName !== '') {
            $pdo->prepare(
                'INSERT INTO sx_ygo_deck_cards (deck_id, card_name, role, quantity, section, notes) VALUES (?, ?, ?, ?, ?, ?)',
            )->execute([
                $deckId,
                $cardName,
                trim((string) ($post['card_role'] ?? '')),
                max(1, (int) ($post['card_quantity'] ?? 1)),
                trim((string) ($post['card_section'] ?? 'main')),
                trim((string) ($post['card_notes'] ?? '')),
            ]);
        }
        return 'carta_deck';

        return null;
    }
}
