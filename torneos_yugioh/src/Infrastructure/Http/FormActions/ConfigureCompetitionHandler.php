<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;
use InvalidArgumentException;

/** Native configure_competition adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class ConfigureCompetitionHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        try {
            ygo_configure_competition($pdo);
        } catch (InvalidArgumentException $error) {
            $_SESSION['ygo_season_error'] = $error->getMessage();
            return 'evento_temporada_bloqueada';
        }
        return 'evento_configurado_' . max(0, (int) ($post['competition_id'] ?? 0));

        return null;
    }
}
