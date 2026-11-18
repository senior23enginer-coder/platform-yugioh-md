<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native sync_offline_form adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class SyncOfflineFormHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $requestId =
            preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($_POST['offline_request_id'] ?? '')) ?:
            '';
        $payload = json_decode((string) ($_POST['offline_payload'] ?? ''), true);
        $allowedActions = [
            'save_deck',
            'save_deck_analysis_profile',
            'save_matchup_analysis',
            'save_team',
            'save_competition',
            'configure_competition',
            'save_match_schedule',
            'save_division',
            'save_settings',
        ];
        if (
            $requestId === '' ||
            !is_array($payload) ||
            !in_array((string) ($payload['action'] ?? ''), $allowedActions, true)
        ) {
            return 'offline_invalido';
        }
        $existing = $pdo->prepare(
            'SELECT result_code FROM sx_ygo_sync_requests WHERE request_id=? LIMIT 1',
        );
        $existing->execute([$requestId]);
        $existingResult = $existing->fetchColumn();
        if ($existingResult !== false) {
            return (string) $existingResult;
        }
        $entityMap = [
            'save_deck' => ['sx_ygo_decks', 'deck_id'],
            'save_deck_analysis_profile' => ['sx_ygo_deck_analysis_profiles', 'deck_id'],
            'save_matchup_analysis' => ['sx_ygo_matchup_analyses', 'analysis_id'],
            'save_team' => ['sx_ygo_teams', 'team_id'],
            'save_competition' => ['sx_ygo_competitions', 'competition_id'],
            'configure_competition' => ['sx_ygo_competitions', 'competition_id'],
            'save_match_schedule' => ['sx_ygo_matches', 'schedule_match_id'],
            'save_division' => ['sx_ygo_divisions', 'division_id'],
        ];
        $originalAction = (string) $payload['action'];
        $baseline = trim((string) ($payload['offline_entity_updated_at'] ?? ''));
        if ($baseline !== '' && isset($entityMap[$originalAction])) {
            [$table, $idField] = $entityMap[$originalAction];
            $entityId = (int) ($payload[$idField] ?? 0);
            if ($entityId > 0 && ygo_column_exists($pdo, $table, 'updated_at')) {
                $current = $pdo->prepare("SELECT updated_at FROM $table WHERE id=? LIMIT 1");
                $current->execute([$entityId]);
                $currentUpdated = (string) ($current->fetchColumn() ?: '');
                if ($currentUpdated !== '' && $currentUpdated !== $baseline) {
                    return 'offline_conflicto';
                }
            }
        }
        unset($payload['offline_entity_updated_at']);
        $outerPost = $_POST;
        $_POST = $payload;
        try {
            $result = ygo_handle_post($pdo) ?: 'offline_guardado';
        } finally {
            $_POST = $outerPost;
        }
        $pdo->prepare(
            'INSERT INTO sx_ygo_sync_requests (request_id,match_id,payload_hash,result_code) VALUES (?,?,?,?)',
        )->execute([
            $requestId,
            0,
            hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE) ?: ''),
            $result,
        ]);
        return $result;

        return null;
    }
}
