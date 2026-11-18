<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native save_competition_dependency adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class SaveCompetitionDependencyHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $seasonId = ygo_current_season_id($pdo);
        $competitionId = (int) ($post['dependency_competition_id'] ?? 0);
        $dependsOnId = (int) ($post['dependency_depends_on_id'] ?? 0);
        $condition = preg_replace(
            '/[^a-z_]/',
            '',
            strtolower((string) ($post['dependency_condition'] ?? 'completed_with_results')),
        );
        if (
            !in_array(
                $condition,
                [
                    'completed',
                    'has_results',
                    'completed_with_results',
                    'top_n',
                    'winner_to_final',
                    'defending_champion',
                    'creates_divisions',
                ],
                true,
            )
        ) {
            $condition = 'completed_with_results';
        }
        $takeCount = max(0, min(128, (int) ($post['dependency_take_count'] ?? 0)));
        if ($condition === 'top_n' && $takeCount <= 0) {
            $takeCount = 4;
        }
        if ($competitionId > 0 && $dependsOnId > 0 && $competitionId !== $dependsOnId) {
            $pdo->prepare(
                "
                INSERT INTO sx_ygo_competition_dependencies (season_id, competition_id, depends_on_competition_id, condition_type, take_count, active)
                VALUES (?, ?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE condition_type=VALUES(condition_type), take_count=VALUES(take_count), active=1
            ",
            )->execute([$seasonId, $competitionId, $dependsOnId, $condition, $takeCount]);
            $pdo->prepare(
                'INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)',
            )->execute([
                'competition',
                $competitionId,
                'save_dependency',
                json_encode(
                    [
                        'depends_on' => $dependsOnId,
                        'condition' => $condition,
                        'take_count' => $takeCount,
                    ],
                    JSON_UNESCAPED_UNICODE,
                ),
            ]);
        }
        return 'dependencia_evento';

        return null;
    }
}
