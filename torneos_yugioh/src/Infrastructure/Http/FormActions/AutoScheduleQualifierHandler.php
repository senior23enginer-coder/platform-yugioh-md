<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native auto_schedule_qualifier adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class AutoScheduleQualifierHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        return 'clasificatorio_programado_' . ygo_auto_schedule_qualifier($pdo);

        return null;
    }
}
