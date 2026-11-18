<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native finalize_ffa adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class FinalizeFfaHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $id = (int) ($post['competition_id'] ?? 0);
        ygo_ffa_finalize($pdo, $id);
        return 'evento_configurado_' . $id;
        return null;
    }
}
