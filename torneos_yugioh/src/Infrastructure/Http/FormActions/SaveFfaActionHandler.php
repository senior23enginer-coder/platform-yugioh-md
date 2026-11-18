<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;
use InvalidArgumentException;

/** Native save_ffa_action adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class SaveFfaActionHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $id = (int) ($post['ffa_match_id'] ?? 0);
        $command = ['kind' => (string) ($post['ffa_kind'] ?? '')];
        foreach (['deck_id', 'delta'] as $k) {
            if (isset($post['ffa_' . $k])) {
                $n = filter_var($post['ffa_' . $k], FILTER_VALIDATE_INT);
                if ($n === false) {
                    throw new InvalidArgumentException('Valor de accion invalido.');
                }
                $command[$k] = $n;
            }
        }
        if (isset($post['ffa_reason'])) {
            $command['reason'] = (string) $post['ffa_reason'];
        }
        ygo_ffa_command(
            $pdo,
            $id,
            (int) ($post['ffa_revision'] ?? -1),
            (string) ($post['ffa_request'] ?? ''),
            $command,
        );
        $q = $pdo->prepare('SELECT competition_id FROM sx_ygo_ffa_matches WHERE id=?');
        $q->execute([$id]);
        return 'evento_configurado_' . (int) $q->fetchColumn();

        return null;
    }
}
