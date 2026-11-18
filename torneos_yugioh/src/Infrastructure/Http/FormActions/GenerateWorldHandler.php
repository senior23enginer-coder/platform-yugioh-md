<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native generate_world adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class GenerateWorldHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        return 'mundial_' . ygo_generate_world($pdo);

        return null;
    }
}
