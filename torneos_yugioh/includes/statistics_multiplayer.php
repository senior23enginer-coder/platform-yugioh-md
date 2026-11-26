<?php
declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/src/autoload.php';

const YGO_FFA_RATING_VERSION = 'STATION_X_FFA_ELO_1.0';

/** Team means; both sides are updated simultaneously from their pre-match ratings. */
function ygo_stats_tag_elo(array $left, array $right, float $score, float $k = 20): array
{
    return \StationX\Yugioh\Domain\Statistics\RatingMath::tagElo($left, $right, $score, $k);
}

/** Winner-only multinomial Elo. One real table, one winner; sum of deltas is zero. */
function ygo_stats_ffa_elo(array $ratings, int $winner, float $k = 20): array
{
    return \StationX\Yugioh\Domain\Statistics\FreeForAllStatistics::elo($ratings, $winner, $k);
}

function ygo_stats_group_reason(array $m): ?string
{
    return \StationX\Yugioh\Domain\Statistics\TagStatistics::reason($m);
}

function ygo_stats_tag_accumulate(
    array &$r,
    array $m,
    array $person,
    array $opponents,
    bool $won,
    bool $draw,
): void {
    \StationX\Yugioh\Domain\Statistics\TagStatistics::accumulate($r, $m, $person, $opponents, $won, $draw);
}

function ygo_stats_apply_tag(
    array $m,
    array $policy,
    array &$global,
    array &$profiles,
    array &$ledger,
): void {
    \StationX\Yugioh\Domain\Statistics\TagStatistics::apply($m, $policy, $global, $profiles, $ledger);
}

function ygo_stats_calculate_ffa(array $matches, array $policy = [], ?string $cutoff = null): array
{
    return \StationX\Yugioh\Infrastructure\Composition\StatisticsModule::replay()->freeForAll($matches, $policy, $cutoff);
}
