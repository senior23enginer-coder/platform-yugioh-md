<?php
declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/src/autoload.php';

function ygo_normalize_turn_history(array $history): array
{
    return \StationX\Yugioh\Domain\Matches\TurnHistory::normalizeTurnHistory($history);
}

function ygo_merge_monotonic_turns(array $storedDetail, array $incomingDetail): array
{
    return \StationX\Yugioh\Domain\Matches\TurnHistory::mergeMonotonicTurns(
        $storedDetail,
        $incomingDetail,
    );
}
