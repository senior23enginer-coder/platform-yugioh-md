<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native save_team_deck_links adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class SaveTeamDeckLinksHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $teamId = (int) ($post['team_id'] ?? 0);
        if ($teamId > 0) {
            $teamExists = $pdo->prepare(
                'SELECT id FROM sx_ygo_teams WHERE id=? AND active=1 LIMIT 1',
            );
            $teamExists->execute([$teamId]);
            if ((int) ($teamExists->fetchColumn() ?: 0) > 0) {
                $addDeckIds = $post['team_add_deck_ids'] ?? [];
                $removeDeckIds = $post['team_remove_deck_ids'] ?? [];
                if (!is_array($addDeckIds)) {
                    $addDeckIds = [];
                }
                if (!is_array($removeDeckIds)) {
                    $removeDeckIds = [];
                }
                $addDeckIds = array_values(
                    array_unique(
                        array_filter(
                            array_map('intval', $addDeckIds),
                            static fn(int $id): bool => $id > 0,
                        ),
                    ),
                );
                $removeDeckIds = array_values(
                    array_unique(
                        array_filter(
                            array_map('intval', $removeDeckIds),
                            static fn(int $id): bool => $id > 0,
                        ),
                    ),
                );
                if ($addDeckIds) {
                    $placeholders = implode(',', array_fill(0, count($addDeckIds), '?'));
                    $pdo->prepare(
                        "UPDATE sx_ygo_decks SET team_id=? WHERE active=1 AND team_id IS NULL AND id IN ($placeholders)",
                    )->execute([$teamId, ...$addDeckIds]);
                }
                if ($removeDeckIds) {
                    $placeholders = implode(',', array_fill(0, count($removeDeckIds), '?'));
                    $pdo->prepare(
                        "UPDATE sx_ygo_decks SET team_id=NULL WHERE active=1 AND team_id=? AND id IN ($placeholders)",
                    )->execute([$teamId, ...$removeDeckIds]);
                }
                $pdo->prepare(
                    'INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)',
                )->execute([
                    'team',
                    $teamId,
                    'save_team_deck_links',
                    json_encode(
                        ['add' => $addDeckIds, 'remove' => $removeDeckIds],
                        JSON_UNESCAPED_UNICODE,
                    ),
                ]);
            }
        }
        return 'equipo_ficha';

        return null;
    }
}
