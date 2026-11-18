<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;
use Throwable;
use RuntimeException;

/** Native save_player adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class SavePlayerHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $id = max(0, (int) ($post['player_id'] ?? 0));
        $name = trim((string) ($post['player_name'] ?? ''));
        $alias = trim((string) ($post['player_alias'] ?? ''));
        $teamId = max(0, (int) ($post['player_team_id'] ?? 0));
        $deckIds = array_values(
            array_unique(
                array_filter(
                    array_map('intval', (array) ($post['player_deck_ids'] ?? [])),
                    static fn($value) => $value > 0,
                ),
            ),
        );
        if ($name === '') {
            throw new RuntimeException('El nombre del jugador es obligatorio.');
        }
        $pdo->beginTransaction();
        try {
            if ($id > 0) {
                $pdo->prepare(
                    'UPDATE sx_ygo_players SET team_id=?, display_name=?, alias=?, active=1 WHERE id=?',
                )->execute([$teamId ?: null, $name, $alias, $id]);
            } else {
                $pdo->prepare(
                    'INSERT INTO sx_ygo_players (team_id, display_name, alias, active) VALUES (?, ?, ?, 1)',
                )->execute([$teamId ?: null, $name, $alias]);
                $id = (int) $pdo->lastInsertId();
            }
            $pdo->prepare('UPDATE sx_ygo_player_decks SET active=0 WHERE player_id=?')->execute([
                $id,
            ]);
            $link = $pdo->prepare(
                'INSERT INTO sx_ygo_player_decks (player_id, deck_id, active) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE active=1',
            );
            foreach ($deckIds as $deckId) {
                $link->execute([$id, $deckId]);
            }
            $pdo->prepare(
                'INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES ("player", ?, "save_player", ?)',
            )->execute([
                $id,
                json_encode(['team_id' => $teamId, 'deck_ids' => $deckIds], JSON_UNESCAPED_UNICODE),
            ]);
            $pdo->commit();
        } catch (Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
        return 'jugador_' . $id;

        return null;
    }
}
