<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native save_catalog_items adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class SaveCatalogItemsHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $seasonId = ygo_current_season_id($pdo);
        $ids = $post['catalog_id'] ?? [];
        $labels = $post['catalog_label'] ?? [];
        $icons = $post['catalog_icon'] ?? [];
        $accents = $post['catalog_accent'] ?? [];
        $sorts = $post['catalog_sort_order'] ?? [];
        $actives = array_map('strval', (array) ($post['catalog_active'] ?? []));
        $update = $pdo->prepare(
            'UPDATE sx_ygo_catalog_items SET label=?, icon=?, accent=?, sort_order=?, active=? WHERE id=? AND season_id IN (0, ?)',
        );
        foreach ((array) $ids as $index => $idRaw) {
            $id = (int) $idRaw;
            if ($id <= 0) {
                continue;
            }
            $label = trim((string) ($labels[$index] ?? ''));
            if ($label === '') {
                continue;
            }
            $update->execute([
                $label,
                trim((string) ($icons[$index] ?? '')),
                trim((string) ($accents[$index] ?? '')),
                max(0, (int) ($sorts[$index] ?? 100)),
                in_array((string) $id, $actives, true) ? 1 : 0,
                $id,
                $seasonId,
            ]);
        }
        $newKey = preg_replace(
            '/[^a-zA-Z0-9_.-]/',
            '',
            trim((string) ($post['new_catalog_key'] ?? '')),
        );
        $newCode = preg_replace(
            '/[^a-zA-Z0-9_.-]/',
            '',
            trim((string) ($post['new_catalog_code'] ?? '')),
        );
        $newLabel = trim((string) ($post['new_catalog_label'] ?? ''));
        if ($newKey !== '' && $newCode !== '' && $newLabel !== '') {
            $pdo->prepare(
                '
                INSERT INTO sx_ygo_catalog_items (season_id, catalog_key, item_code, label, icon, accent, sort_order, meta_json, active)
                VALUES (?, ?, ?, ?, ?, ?, ?, "[]", 1)
                ON DUPLICATE KEY UPDATE label=VALUES(label), icon=VALUES(icon), accent=VALUES(accent), sort_order=VALUES(sort_order), active=1
            ',
            )->execute([
                $seasonId,
                $newKey,
                $newCode,
                $newLabel,
                trim((string) ($post['new_catalog_icon'] ?? '')),
                trim((string) ($post['new_catalog_accent'] ?? '')),
                max(0, (int) ($post['new_catalog_sort_order'] ?? 100)),
            ]);
        }
        $pdo->prepare(
            'INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)',
        )->execute([
            'catalog_items',
            $seasonId,
            'save_catalog_items',
            json_encode(
                ['updated' => count((array) $ids), 'new' => $newKey . ':' . $newCode],
                JSON_UNESCAPED_UNICODE,
            ),
        ]);
        return 'opciones_guardadas';

        return null;
    }
}
