<?php
declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/src/autoload.php';
require_once __DIR__ . '/statistics_multiplayer.php';

/** Statistical derivatives only. Never writes competitions, matches, results or honors. */
const YGO_STATS_VERSION = 'STATION_X_STATS_8.4';

function ygo_stats_ratio(float $n, float $d): ?float
{
    return \StationX\Yugioh\Domain\Statistics\RatingMath::ratio($n, $d);
}
function ygo_stats_wilson(float $p, int $n): ?float
{
    return \StationX\Yugioh\Domain\Statistics\RatingMath::wilson($p, $n);
}
function ygo_stats_elo(float $a, float $b, float $s, float $k = 20, float $divisor = 600): array
{
    return \StationX\Yugioh\Domain\Statistics\RatingMath::elo($a, $b, $s, $k, $divisor);
}

function ygo_stats_policy(): array
{
    return \StationX\Yugioh\Domain\Statistics\StatisticsPolicy::defaults();
}

/** Adapters must explicitly confirm eligibility; unknown is never a positive result. */
function ygo_stats_eligible(array $m): ?string
{
    return \StationX\Yugioh\Domain\Statistics\StatisticsPolicy::eligible($m);
}

function ygo_stats_metric(int $team, int $deck, string $format): array
{
    return \StationX\Yugioh\Domain\Statistics\MetricFactory::metric($team, $deck, $format);
}

/** LP endpoints cannot prove a flawless victory; only complete evidence can. */
function ygo_stats_lp_history_known(array $d, string $side): bool
{
    return \StationX\Yugioh\Domain\Statistics\LifePointPolicy::lpHistoryKnown($d, $side);
}
function ygo_stats_flawless(array $d, string $side): bool
{
    return \StationX\Yugioh\Domain\Statistics\LifePointPolicy::flawless($d, $side);
}

function ygo_stats_accumulate(array &$r, array $m, string $side): void
{
    \StationX\Yugioh\Domain\Statistics\MetricAccumulator::accumulate($r, $m, $side);
}

function ygo_stats_tier(array $r, array $policy = []): array
{
    return \StationX\Yugioh\Domain\Statistics\MetagamePolicy::tier($r, $policy);
}

/** Complete chronological replay, used for first calculation and corrections alike. */
function ygo_stats_calculate(
    array $matches,
    array $events = [],
    array $honors = [],
    array $policy = [],
    ?string $cutoff = null,
    array $publishedTiers = [],
    array $ffaMatches = [],
): array {
    return \StationX\Yugioh\Infrastructure\Composition\StatisticsModule::replay()->execute(
        $matches,
        $events,
        $honors,
        $policy,
        $cutoff,
        $publishedTiers,
        $ffaMatches,
    );
}

function ygo_stats_historical_tier(
    array $cuts,
    int $season,
    string $format,
    int $deck,
    string $at,
): ?string {
    return \StationX\Yugioh\Domain\Statistics\HistoricalMetrics::historicalTier(
        $cuts,
        $season,
        $format,
        $deck,
        $at,
    );
}

function ygo_stats_championships(array $events, array $honors, array $policy, string $cutoff): array
{
    return \StationX\Yugioh\Domain\Statistics\HistoricalMetrics::championships(
        $events,
        $honors,
        $policy,
        $cutoff,
    );
}

/** Individual scopes and evidence; secret names remain hidden until unlocked. */
function ygo_stats_achievements(
    array $r,
    array $events = [],
    array $honors = [],
    array $matches = [],
): array {
    return \StationX\Yugioh\Domain\Statistics\AchievementPolicy::achievements(
        $r,
        $events,
        $honors,
        $matches,
    );
}

/** Official global positions close once per calendar month, in Colombia time. */
function ygo_stats_global_cuts(string $start, string $now): array
{
    return \StationX\Yugioh\Domain\Statistics\StatisticsCuts::globalCuts($start, $now);
}

function ygo_stats_cuts(string $start, string $end, string $now): array
{
    return \StationX\Yugioh\Domain\Statistics\StatisticsCuts::cuts($start, $end, $now);
}

function ygo_stats_analytics(array $data, int $minimum = 3): array
{
    return \StationX\Yugioh\Domain\Statistics\Analytics::analytics($data, $minimum);
}

/** Export projections are shared by CSV and PDF; all values come from the API snapshot. */
function ygo_stats_export_rows(array $data, string $collection = 'global'): array
{
    return \StationX\Yugioh\Domain\Statistics\StatisticsProjection::exportRows($data, $collection);
}

function ygo_stats_filter(array $matches, array $filters): array
{
    return \StationX\Yugioh\Domain\Statistics\StatisticsProjection::filter($matches, $filters);
}
