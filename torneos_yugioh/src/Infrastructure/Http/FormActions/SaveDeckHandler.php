<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native save_deck adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class SaveDeckHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $id = (int) ($post['deck_id'] ?? 0);
        $deckName = trim((string) $post['deck_name']);
        $archetype = trim((string) $post['archetype']);
        $existingTeamId = null;
        if ($id > 0) {
            $existingTeamStmt = $pdo->prepare(
                'SELECT team_id FROM sx_ygo_decks WHERE id=? AND active=1 LIMIT 1',
            );
            $existingTeamStmt->execute([$id]);
            $existingTeamId = $existingTeamStmt->fetchColumn();
            $existingTeamId =
                $existingTeamId === false || $existingTeamId === null
                    ? null
                    : (int) $existingTeamId;
        }
        $existingCoverUrl = '';
        if ($id > 0) {
            $existingCoverStmt = $pdo->prepare(
                'SELECT cover_url FROM sx_ygo_decks WHERE id=? AND active=1 LIMIT 1',
            );
            $existingCoverStmt->execute([$id]);
            $existingCoverUrl = (string) ($existingCoverStmt->fetchColumn() ?: '');
        }
        $coverUrl = trim((string) ($post['cover_url'] ?? '')) ?: $existingCoverUrl;
        $teamId = array_key_exists('team_id', $post)
            ? (($post['team_id'] ?? '') === ''
                ? 0
                : (int) $post['team_id'])
            : (int) ($existingTeamId ?? 0);
        if (
            $id <= 0 &&
            $teamId <= 0 &&
            $deckName !== '' &&
            array_key_exists('team_id', $post) &&
            ($post['team_id'] ?? '') !== ''
        ) {
            $profile = ygo_team_profile_for_deck($deckName, $archetype);
            $teamId = ygo_find_or_create_team($pdo, $profile['name'], $profile);
        }
        $values = [
            ($post['division_id'] ?? '') === '' ? null : (int) $post['division_id'],
            $teamId > 0 ? $teamId : null,
            max(1, (int) ($post['seed_rank'] ?? 999)),
            $deckName,
            $archetype,
            trim((string) $post['style']),
            trim((string) $post['reason']),
            trim((string) $post['key_cards']),
            trim((string) $post['variations']),
            trim((string) $post['notes']),
            $coverUrl,
        ];
        if ($values[3] !== '') {
            if ($id > 0) {
                $pdo->prepare(
                    'UPDATE sx_ygo_decks SET division_id=?, team_id=?, seed_rank=?, name=?, archetype=?, style=?, reason=?, key_cards=?, variations=?, notes=?, cover_url=?, active=1 WHERE id=?',
                )->execute([...$values, $id]);
            } else {
                $pdo->prepare(
                    'INSERT INTO sx_ygo_decks (division_id, team_id, seed_rank, name, archetype, style, reason, key_cards, variations, notes, cover_url, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)',
                )->execute($values);
                $id = (int) $pdo->lastInsertId();
            }
            $uploadedDeckLogo = ygo_store_deck_logo_upload($id);
            if ($uploadedDeckLogo !== '') {
                $pdo->prepare('UPDATE sx_ygo_decks SET cover_url=? WHERE id=?')->execute([
                    $uploadedDeckLogo,
                    $id,
                ]);
                ygo_record_logo_history($pdo, 'deck', $id, $uploadedDeckLogo);
            }
        }
        return 'deck_' . $id;

        return null;
    }
}
