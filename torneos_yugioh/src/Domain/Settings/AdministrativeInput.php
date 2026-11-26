<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Settings;

final class AdministrativeInput
{
    public static function settings(array $post): iterable
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
                yield $key => $value;
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
            yield $key => $value;
        }
    }

    public static function labels(array $post): iterable
    {
        $keys = $post['ui_label_key'] ?? [];
        $titles = $post['ui_label_title'] ?? [];
        $subtitles = $post['ui_label_subtitle'] ?? [];
        $actives = array_map('strval', (array) ($post['ui_label_active'] ?? []));
        $rows = max(count((array) $keys), count((array) $titles));
        for ($i = 0; $i < $rows; $i++) {
            $key = preg_replace('/[^a-zA-Z0-9_.-]/', '', trim((string) ($keys[$i] ?? '')));
            $title = trim((string) ($titles[$i] ?? ''));
            if ($key === '' || $title === '') {
                continue;
            }
            yield [
                $key,
                $title,
                trim((string) ($subtitles[$i] ?? '')),
                in_array($key, $actives, true) ? 1 : 0,
            ];
        }
    }

    public static function labelCount(array $post): int
    {
        return max(
            count((array) ($post['ui_label_key'] ?? [])),
            count((array) ($post['ui_label_title'] ?? [])),
        );
    }

    public static function catalog(array $post): iterable
    {
        $ids = $post['catalog_id'] ?? [];
        $labels = $post['catalog_label'] ?? [];
        $icons = $post['catalog_icon'] ?? [];
        $accents = $post['catalog_accent'] ?? [];
        $sorts = $post['catalog_sort_order'] ?? [];
        $actives = array_map('strval', (array) ($post['catalog_active'] ?? []));
        foreach ((array) $ids as $index => $idRaw) {
            $id = (int) $idRaw;
            if ($id <= 0) {
                continue;
            }
            $label = trim((string) ($labels[$index] ?? ''));
            if ($label === '') {
                continue;
            }
            yield [
                $label,
                trim((string) ($icons[$index] ?? '')),
                trim((string) ($accents[$index] ?? '')),
                max(0, (int) ($sorts[$index] ?? 100)),
                in_array((string) $id, $actives, true) ? 1 : 0,
                $id,
            ];
        }
    }

    public static function newCatalog(array $post): array
    {
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
        return [
            $newKey,
            $newCode,
            $newLabel,
            trim((string) ($post['new_catalog_icon'] ?? '')),
            trim((string) ($post['new_catalog_accent'] ?? '')),
            max(0, (int) ($post['new_catalog_sort_order'] ?? 100)),
        ];
    }
}
