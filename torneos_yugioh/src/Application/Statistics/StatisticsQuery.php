<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Statistics;

use StationX\Yugioh\Domain\Statistics\Analytics;
use StationX\Yugioh\Domain\Statistics\StatisticsProjection;
use StationX\Yugioh\Domain\Statistics\StatisticsRevisionPolicy;

final readonly class StatisticsQuery
{
    public function __construct(
        private ReadStatisticsView $view,
        private StatisticsPublicationReader $publications,
        private CalculateStatistics $calculator,
    ) {}

    public function execute(array $query): ?array
    {
        $stats = $this->view->execute();
        if (!empty($query['publication_id'])) {
            $cut = $this->publications->find((int) $query['publication_id']);
            if (!$cut) {
                return null;
            }
            $stats = StatisticsRevisionPolicy::publicValues(json_decode($cut['payload'], true));
            $stats['publication_id'] = $cut['id'];
            $stats['published_at'] = $cut['published_at'];
        }
        $format = trim((string) ($query['format'] ?? ''));
        if ($format !== '') {
            foreach (['global', 'metagame', 'medals', 'ledger'] as $collection) {
                $stats[$collection] = array_values(
                    array_filter(
                        $stats[$collection] ?? [],
                        static fn($r) => (string) ($r['format_id'] ?? '') === $format,
                    ),
                );
            }
            foreach (['ranking', 'ledger'] as $collection) {
                $stats['free_for_all'][$collection] = array_values(
                    array_filter(
                        $stats['free_for_all'][$collection] ?? [],
                        static fn($r) => ($r['format_id'] ?? '') === $format,
                    ),
                );
            }
        }
        if (!empty($query['entity_team_id'])) {
            $team = (int) $query['entity_team_id'];
            foreach (['global', 'profiles', 'medals'] as $list) {
                $stats[$list] = array_values(
                    array_filter(
                        $stats[$list] ?? [],
                        static fn($r) => (int) ($r['team_id'] ?? 0) === $team,
                    ),
                );
            }
            $stats['ledger'] = array_values(
                array_filter(
                    $stats['ledger'] ?? [],
                    static fn($r) => (int) ($r['team_a_id'] ?? 0) === $team ||
                        (int) ($r['team_b_id'] ?? 0) === $team ||
                        in_array($team, array_column($r['participants'] ?? [], 'team_id'), true),
                ),
            );
            $stats['free_for_all']['ranking'] = array_values(
                array_filter(
                    $stats['free_for_all']['ranking'] ?? [],
                    static fn($r) => (int) $r['team_id'] === $team,
                ),
            );
            $stats['free_for_all']['ledger'] = array_values(
                array_filter(
                    $stats['free_for_all']['ledger'] ?? [],
                    static fn($r) => in_array($team, array_column($r['changes'], 'team_id'), true),
                ),
            );
        }
        $filters = [];
        foreach (
            [
                'event_id',
                'season_id',
                'division_id',
                'phase',
                'event_type',
                'team_id',
                'deck_id',
                'player_id',
                'judge',
                'venue',
                'from',
                'to',
            ]
            as $key
        ) {
            if (isset($query['stats_' . $key]) && trim((string) $query['stats_' . $key]) !== '') {
                $filters[$key] = trim((string) $query['stats_' . $key]);
            }
        }
        $analysis = $stats;
        if ($filters) {
            $analysis = $this->calculator->execute(
                StatisticsProjection::filter($stats['confirmed_matches'] ?? [], $filters),
                [],
                [],
                $stats['policy'] ?? [],
                $stats['cutoff'] ?? null,
            );
            $selectedMatches = array_fill_keys(array_column($analysis['ledger'], 'match_id'), true);
            foreach ($analysis['global'] as &$r) {
                $official =
                    array_values(
                        array_filter(
                            $stats['global'] ?? [],
                            static fn($g) => $g['team_id'] === $r['team_id'] &&
                                $g['format_id'] === $r['format_id'],
                        ),
                    )[0] ?? null;
                $r['rating'] = $official['rating'] ?? null;
                $r['rival_pre_sum'] = 0;
                foreach ($stats['ledger'] ?? [] as $entry) {
                    if (
                        !isset($selectedMatches[$entry['match_id']]) ||
                        $entry['format_id'] !== $r['format_id']
                    ) {
                        continue;
                    }
                    if (($entry['kind'] ?? '') === 'tag') {
                        foreach ($entry['participants'] as $participant) {
                            if ($participant['team_id'] === $r['team_id']) {
                                $r['rival_pre_sum'] += $participant['rival_mean'];
                            }
                        }
                        continue;
                    }
                    if ($entry['team_a_id'] === $r['team_id']) {
                        $r['rival_pre_sum'] += $entry['before_b'];
                    } elseif ($entry['team_b_id'] === $r['team_id']) {
                        $r['rival_pre_sum'] += $entry['before_a'];
                    }
                }
            }
            unset($r);
            $stats['matchups'] = $analysis['matchups'];
            $stats['players'] = $analysis['players'];
        }
        $analytics = Analytics::analytics(
            $analysis,
            max(1, min(100, (int) ($query['minimum'] ?? 3))),
        );
        $stats['analytical_filters'] = $filters;

        $stats['analytics'] = $analytics;
        return $stats;
    }
}
