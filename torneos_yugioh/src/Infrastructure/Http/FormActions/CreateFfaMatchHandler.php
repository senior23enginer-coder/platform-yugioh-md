<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native create_ffa_match adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class CreateFfaMatchHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $id = (int) ($post['competition_id'] ?? 0);
        $ids = array_map('intval', $post['ffa_decks'] ?? []);
        ygo_ffa_create(
            $pdo,
            $id,
            $ids,
            (string) ($post['ffa_date'] ?? ''),
            (string) ($post['ffa_time'] ?? ''),
        );
        return 'evento_configurado_' . $id;

        return null;
    }
}
