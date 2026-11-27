<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Delegates input parsing and admission persistence to separate policies and use cases. */
final class SaveEventAdmissionHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        [$id, $rows, $capacity] = \StationX\Yugioh\Domain\Competitions\AdmissionFormInput::parse($post);
        ygo_admission_save($pdo, $id, $rows, $capacity);
        return 'evento_configurado_' . $id;
    }
}
