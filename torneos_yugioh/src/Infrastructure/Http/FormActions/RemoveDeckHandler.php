<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native remove_deck adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class RemoveDeckHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $id = (int) ($post['deck_id'] ?? 0);
        if ($id > 0) {
            $blocker = ygo_deck_delete_blocker($pdo, $id);
            if ($blocker) {
                $pdo->prepare(
                    'INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)',
                )->execute([
                    'deck',
                    $id,
                    'blocked_remove_deck_in_event',
                    json_encode($blocker, JSON_UNESCAPED_UNICODE),
                ]);
                return 'deck_bloqueado_evento';
            }
            $pdo->prepare('UPDATE sx_ygo_decks SET active=0 WHERE id=?')->execute([$id]);
        }
        return 'deck_quitado';

        return null;
    }
}
