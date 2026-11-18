<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;
use InvalidArgumentException;

/** Native freeze_event_card_rules adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class FreezeEventCardRulesHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        throw new InvalidArgumentException(
            'El control de cartas, pools y elecciones de Draft fue retirado.',
        );
        return null;
    }
}
