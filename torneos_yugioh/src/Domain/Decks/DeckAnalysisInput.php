<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Decks;

final class DeckAnalysisInput
{
    public static function profile(array $post): array
    {
        $profileListJson = static function (string $field) use ($post): string {
            $raw = (string) ($post[$field] ?? '');
            $structured = json_decode($raw, true);
            $isStructured =
                is_array($structured) &&
                array_is_list($structured) &&
                count(array_filter($structured, 'is_string')) === count($structured);
            $rows = $isStructured ? $structured : (preg_split('/[\r\n,;]+/', $raw) ?: []);
            $limit = $isStructured ? 2400 : 240;
            $rows = array_values(
                array_unique(
                    array_filter(
                        array_map(
                            static fn($value): string => mb_substr(
                                trim((string) $value),
                                0,
                                $limit,
                            ),
                            $rows,
                        ),
                    ),
                ),
            );
            return json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
        };
        $score = static fn(string $field): int => max(0, min(5, (int) ($post[$field] ?? 0)));
        $confidence = strtoupper(trim((string) ($post['deck_analysis_confidence'] ?? 'MEDIA')));
        if (!in_array($confidence, ['BAJA', 'MEDIA', 'ALTA'], true)) {
            $confidence = 'MEDIA';
        }
        $sourceType = strtoupper(
            trim((string) ($post['deck_analysis_source_type'] ?? 'EDITORIAL')),
        );
        if (!in_array($sourceType, ['EDITORIAL', 'ANALYTICAL'], true)) {
            $sourceType = 'EDITORIAL';
        }
        $values = [
            mb_substr(trim((string) ($post['deck_analysis_play_style'] ?? '')), 0, 120),
            mb_substr(trim((string) ($post['deck_analysis_pace'] ?? '')), 0, 30),
            mb_substr(trim((string) ($post['deck_analysis_complexity'] ?? '')), 0, 30),
            trim((string) ($post['deck_analysis_primary_game_plan'] ?? '')),
            trim((string) ($post['deck_analysis_win_condition'] ?? '')),
            trim((string) ($post['deck_analysis_resource_engine'] ?? '')),
            trim((string) ($post['deck_analysis_opening_plan'] ?? '')),
            trim((string) ($post['deck_analysis_mid_game_plan'] ?? '')),
            trim((string) ($post['deck_analysis_late_game_plan'] ?? '')),
            trim((string) ($post['deck_analysis_going_first_plan'] ?? '')),
            trim((string) ($post['deck_analysis_going_second_plan'] ?? '')),
            trim((string) ($post['deck_analysis_side_deck_strategy'] ?? '')),
            $profileListJson('deck_analysis_strengths'),
            $profileListJson('deck_analysis_weaknesses'),
            $profileListJson('deck_analysis_strategy_tags'),
            $score('deck_analysis_consistency_score'),
            $score('deck_analysis_ceiling_score'),
            $score('deck_analysis_resilience_score'),
            $score('deck_analysis_interaction_score'),
            $score('deck_analysis_recovery_score'),
            $score('deck_analysis_first_score'),
            $score('deck_analysis_second_score'),
            $confidence,
            $sourceType,
            trim((string) ($post['deck_analysis_notes'] ?? '')),
        ];
        return ['values' => $values, 'sourceType' => $sourceType, 'reason' => trim((string) ($post['deck_analysis_change_reason'] ?? ''))];
    }

    public static function matchup(array $post): array
    {
        $score = max(-2, min(2, (int) ($post['strategic_score'] ?? 0)));
        $classificationByScore = [
            -2 => 'MUY_DESFAVORABLE',
            -1 => 'DESFAVORABLE',
            0 => 'EQUILIBRADO',
            1 => 'FAVORABLE',
            2 => 'MUY_FAVORABLE',
        ];
        $confidence = strtoupper(trim((string) ($post['matchup_confidence'] ?? 'MEDIA')));
        if (!in_array($confidence, ['BAJA', 'MEDIA', 'ALTA'], true)) {
            $confidence = 'MEDIA';
        }
        $sourceType = strtoupper(trim((string) ($post['matchup_source_type'] ?? 'EDITORIAL')));
        if (!in_array($sourceType, ['EDITORIAL', 'ANALYTICAL'], true)) {
            $sourceType = 'EDITORIAL';
        }
        $listJson = static function (string $field) use ($post): string {
            $rows = preg_split('/[\r\n,;]+/', (string) ($post[$field] ?? '')) ?: [];
            $rows = array_values(
                array_unique(
                    array_filter(
                        array_map(
                            static fn($value): string => mb_substr(trim((string) $value), 0, 240),
                            $rows,
                        ),
                    ),
                ),
            );
            return json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
        };
        $scope = [
            mb_substr(trim((string) ($post['matchup_deck_version'] ?? '')), 0, 180),
            mb_substr(trim((string) ($post['matchup_opponent_version'] ?? '')), 0, 180),
            mb_substr(trim((string) ($post['matchup_ruleset_version'] ?? '')), 0, 180),
        ];
        $values = [
            $score,
            $classificationByScore[$score],
            trim((string) ($post['matchup_reason'] ?? '')),
            $listJson('matchup_advantages'),
            $listJson('matchup_disadvantages'),
            $listJson('matchup_choke_points'),
            trim((string) ($post['matchup_going_first_plan'] ?? '')),
            trim((string) ($post['matchup_going_second_plan'] ?? '')),
            trim((string) ($post['matchup_side_plan'] ?? '')),
            $confidence,
            $sourceType,
            trim((string) ($post['matchup_review_notes'] ?? '')),
        ];
        return ['scope' => $scope, 'values' => $values, 'sourceType' => $sourceType, 'reason' => trim((string) ($post['matchup_change_reason'] ?? ''))];
    }
}
