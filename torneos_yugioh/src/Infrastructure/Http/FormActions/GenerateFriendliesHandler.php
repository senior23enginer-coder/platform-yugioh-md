<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native generate_friendlies adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class GenerateFriendliesHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        return 'amistosos_' . ygo_generate_friendlies($pdo);

        return null;
    }
}
