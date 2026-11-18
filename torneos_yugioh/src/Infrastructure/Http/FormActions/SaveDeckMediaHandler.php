<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native save_deck_media adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class SaveDeckMediaHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $deckId = (int) ($post['media_deck_id'] ?? 0);
        $url = trim((string) ($post['media_url'] ?? ''));
        if ($deckId > 0 && $url !== '') {
            $pdo->prepare(
                'INSERT INTO sx_ygo_deck_media (deck_id, media_type, url, title) VALUES (?, ?, ?, ?)',
            )->execute([
                $deckId,
                trim((string) ($post['media_type'] ?? 'image')),
                $url,
                trim((string) ($post['media_title'] ?? '')),
            ]);
        }
        return 'media_deck';

        return null;
    }
}
