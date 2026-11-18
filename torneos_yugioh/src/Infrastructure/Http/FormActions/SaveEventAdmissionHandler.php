<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;
use JsonException;
use InvalidArgumentException;

/** Native save_event_admission adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class SaveEventAdmissionHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        try {
            $rows = json_decode(
                (string) ($post['admission_sources_json'] ?? '[]'),
                true,
                64,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $e) {
            throw new InvalidArgumentException(
                'Las fuentes recibidas no tienen un formato valido.',
            );
        }
        if (!is_array($rows)) {
            throw new InvalidArgumentException('Fuentes invalidas.');
        }
        $capacity = filter_var($post['admission_capacity'] ?? 0, FILTER_VALIDATE_INT);
        if ($capacity === false) {
            throw new InvalidArgumentException('El total de plazas debe ser entero.');
        }
        $id = (int) ($post['competition_id'] ?? 0);
        ygo_admission_save($pdo, $id, $rows, $capacity);
        return 'evento_configurado_' . $id;

        return null;
    }
}
