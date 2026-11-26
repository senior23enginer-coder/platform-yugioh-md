<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Presentation;

final class CompetitionMatchProjection
{
    public static function cups(array $fixtures): array
    {
        return self::collect($fixtures, ['cup', 'final'], true);
    }

    public static function worlds(array $fixtures): array
    {
        return self::collect($fixtures, ['world'], false);
    }

    private static function collect(array $fixtures, array $phases, bool $cup): array
    {
        $matches = [];
        $stats = [];
        foreach ($fixtures as $rounds) {
            foreach ($rounds as $round) {
                foreach ($round['matches'] ?? [] as $match) {
                    $id = (int) ($match['competition_id'] ?? 0);
                    $phase = (string) ($match['phase'] ?? '' ?: '');
                    if ($id <= 0 || !in_array($phase, $phases, true)) {
                        continue;
                    }
                    $matches[$id][] = $match + ['round_number' => (int) ($round['ronda'] ?? 0)];
                    if (!isset($stats[$id])) {
                        $stats[$id] = [
                            'total' => 0,
                            'scheduled' => 0,
                            'finalized' => 0,
                            'pending' => 0,
                        ];
                        if ($cup) {
                            $stats[$id]['dates'] = [];
                        }
                    }
                    $finished =
                        ($match['estado'] ?? '') === 'FINALIZADA' || !empty($match['score']);
                    $stats[$id]['total']++;
                    $stats[$id][$finished ? 'finalized' : 'pending']++;
                    if (!empty($match['scheduled_on'])) {
                        $stats[$id]['scheduled']++;
                        if ($cup) {
                            $stats[$id]['dates'][(string) $match['scheduled_on']] = true;
                        }
                    }
                }
            }
        }
        if ($cup) {
            foreach ($matches as &$group) {
                usort(
                    $group,
                    static fn(array $left, array $right): int => (int) ($left['display_order'] ??
                        0) <=> (int) ($right['display_order'] ?? 0) ?:
                    (int) ($left['round_number'] ?? 0) <=> (int) ($right['round_number'] ?? 0) ?:
                    (int) ($left['mesa'] ?? 0) <=> (int) ($right['mesa'] ?? 0),
                );
            }
            unset($group);
        }
        return ['matches' => $matches, 'stats' => $stats];
    }
}
