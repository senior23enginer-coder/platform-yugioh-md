<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native save_ui_labels adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class SaveUiLabelsHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $seasonId = ygo_current_season_id($pdo);
        $keys = $post['ui_label_key'] ?? [];
        $titles = $post['ui_label_title'] ?? [];
        $subtitles = $post['ui_label_subtitle'] ?? [];
        $actives = array_map('strval', (array) ($post['ui_label_active'] ?? []));
        $rows = max(count((array) $keys), count((array) $titles));
        $upsert = $pdo->prepare('
            INSERT INTO sx_ygo_ui_labels (season_id, label_key, title, subtitle, active)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE title=VALUES(title), subtitle=VALUES(subtitle), active=VALUES(active)
        ');
        for ($i = 0; $i < $rows; $i++) {
            $key = preg_replace('/[^a-zA-Z0-9_.-]/', '', trim((string) ($keys[$i] ?? '')));
            $title = trim((string) ($titles[$i] ?? ''));
            if ($key === '' || $title === '') {
                continue;
            }
            $upsert->execute([
                $seasonId,
                $key,
                $title,
                trim((string) ($subtitles[$i] ?? '')),
                in_array($key, $actives, true) ? 1 : 0,
            ]);
        }
        $pdo->prepare(
            'INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)',
        )->execute([
            'ui_labels',
            $seasonId,
            'save_ui_labels',
            json_encode(['rows' => $rows], JSON_UNESCAPED_UNICODE),
        ]);
        return 'textos_guardados';

        return null;
    }
}
