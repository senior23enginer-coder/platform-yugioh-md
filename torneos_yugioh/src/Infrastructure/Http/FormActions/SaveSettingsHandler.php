<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native save_settings adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class SaveSettingsHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $allowed = [
            'allow_early_results',
            'allow_overdue_results',
            'result_date_guard',
            'alert_days_ahead',
            'valid_weekdays',
            'allowed_start_time',
            'allowed_end_time',
            'max_matches_per_day',
            'allow_special_days',
            'special_days',
            'venues',
            'stats_minimum_sample',
            'tier_sample_minimum',
            'best_debut_min_matches',
            'meta_presence_weight',
            'tier_zero_presence',
            'tier_zero_winrate',
            'tier_one_score',
            'tier_two_score',
            'elo_k_factor',
            'live_refresh_seconds',
            'notification_minutes_before',
        ];
        foreach ($allowed as $key) {
            if ($key === 'valid_weekdays') {
                $rawDays = $post['valid_weekdays'] ?? [];
                $days = is_array($rawDays) ? $rawDays : explode(',', (string) $rawDays);
                $days = array_values(
                    array_intersect(
                        ['1', '2', '3', '4', '5', '6', '0'],
                        array_map('strval', $days),
                    ),
                );
                $value = implode(',', $days);
                ygo_save_setting($pdo, $key, $value);
                continue;
            } else {
                $value = (string) ($post[$key] ?? '0');
            }
            if ($key === 'max_matches_per_day') {
                $value = (string) max(1, min(3, (int) $value));
            } elseif (
                in_array(
                    $key,
                    ['alert_days_ahead', 'live_refresh_seconds', 'notification_minutes_before'],
                    true,
                )
            ) {
                $value = (string) max(1, min(30, (int) $value));
            } elseif (
                in_array(
                    $key,
                    ['stats_minimum_sample', 'tier_sample_minimum', 'best_debut_min_matches'],
                    true,
                )
            ) {
                $value = (string) max(1, min(100, (int) $value));
            } elseif (
                in_array(
                    $key,
                    [
                        'meta_presence_weight',
                        'tier_zero_presence',
                        'tier_zero_winrate',
                        'tier_one_score',
                        'tier_two_score',
                    ],
                    true,
                )
            ) {
                $value = (string) max(0, min(100, (float) $value));
            } elseif ($key === 'elo_k_factor') {
                $value = (string) max(1, min(128, (float) $value));
            } elseif (in_array($key, ['allowed_start_time', 'allowed_end_time'], true)) {
                $value = preg_match('/^\d{2}:\d{2}$/', $value)
                    ? $value
                    : ($key === 'allowed_start_time'
                        ? '10:00'
                        : '22:00');
            } elseif (in_array($key, ['special_days', 'venues'], true)) {
                $value = trim($value);
            } else {
                $value = $value === '1' ? '1' : '0';
            }
            ygo_save_setting($pdo, $key, $value);
        }
        $pdo->prepare(
            'INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)',
        )->execute([
            'settings',
            ygo_current_season_id($pdo),
            'save_settings',
            json_encode($post, JSON_UNESCAPED_UNICODE),
        ]);
        ygo_sync_meta_history($pdo);
        ygo_sync_elo_history($pdo);
        ygo_sync_global_ranking_history($pdo);
        ygo_sync_event_summaries($pdo);
        ygo_sync_medal_unlocks($pdo);
        return 'configuracion_guardada';

        return null;
    }
}
