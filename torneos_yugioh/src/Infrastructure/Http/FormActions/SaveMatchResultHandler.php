<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Compatibility adapter: delegates to the port-based result command. */
final class SaveMatchResultHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        return \StationX\Yugioh\Infrastructure\Composition\MatchResultCommandModule::create(
            $pdo,
        )->execute($post);
    }
}
