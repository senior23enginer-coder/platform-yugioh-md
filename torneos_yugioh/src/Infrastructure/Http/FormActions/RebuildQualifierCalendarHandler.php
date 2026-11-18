<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native rebuild_qualifier_calendar adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class RebuildQualifierCalendarHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        return 'clasificatorio_reconstruido_' . ygo_rebuild_qualifier_calendar($pdo);

        return null;
    }
}
