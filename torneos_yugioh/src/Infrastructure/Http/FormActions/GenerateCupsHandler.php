<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native generate_cups adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class GenerateCupsHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        return 'copas_' . ygo_generate_cups($pdo);

        return null;
    }
}
