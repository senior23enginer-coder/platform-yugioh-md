<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native select_logo_history adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class SelectLogoHistoryHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $entityType = trim((string) ($post['logo_entity_type'] ?? ''));
        $entityId = (int) ($post['logo_entity_id'] ?? 0);
        $logoUrl = trim((string) ($post['logo_history_url'] ?? ''));
        $allowed = [
            'deck' => ['sx_ygo_decks', 'cover_url'],
            'team' => ['sx_ygo_teams', 'logo_url'],
            'competition' => ['sx_ygo_competitions', 'logo_url'],
        ];
        if ($entityId > 0 && $logoUrl !== '' && isset($allowed[$entityType])) {
            $validStmt = $pdo->prepare(
                'SELECT id FROM sx_ygo_logo_history WHERE entity_type=? AND entity_id=? AND logo_url=? LIMIT 1',
            );
            $validStmt->execute([$entityType, $entityId, $logoUrl]);
            if ($validStmt->fetchColumn()) {
                [$table, $column] = $allowed[$entityType];
                $pdo->prepare("UPDATE {$table} SET {$column}=? WHERE id=?")->execute([
                    $logoUrl,
                    $entityId,
                ]);
                ygo_record_logo_history($pdo, $entityType, $entityId, $logoUrl, 'history');
            }
        }
        return 'logo_' . $entityType . '_' . $entityId;

        return null;
    }
}
