<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

final class DependencyInput
{
    public static function normalize(array $post): ?array
    {
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
        if ($competitionId <= 0 || $dependsOnId <= 0 || $competitionId === $dependsOnId) {
            return null;
        }
        return [
            'competitionId' => $competitionId,
            'dependsOnId' => $dependsOnId,
            'condition' => $condition,
            'takeCount' => $takeCount,
        ];
    }
}
