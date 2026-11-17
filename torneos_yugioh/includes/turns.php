<?php
declare(strict_types=1);

function ygo_normalize_turn_history(array $history): array
{
    $byNumber = [];
    foreach (array_slice($history, 0, 999) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $turn = max(1, min(999, (int) ($row['turn'] ?? 0)));
        $side = strtoupper((string) ($row['side'] ?? ''));
        if (!in_array($side, ['A', 'B'], true)) {
            continue;
        }
        if (!isset($byNumber[$turn])) {
            $byNumber[$turn] = [
                'turn' => $turn,
                'side' => $side,
                'ended_at' => mb_substr(trim((string) ($row['ended_at'] ?? '')), 0, 40),
                'elapsed_seconds' => max(0, min(7200, (int) ($row['elapsed_seconds'] ?? 0))),
            ];
        }
    }
    ksort($byNumber);
    return array_values($byNumber);
}

function ygo_merge_monotonic_turns(array $storedDetail, array $incomingDetail): array
{
    $storedHistory = ygo_normalize_turn_history(is_array($storedDetail['turn_history'] ?? null) ? $storedDetail['turn_history'] : []);
    $incomingHistory = ygo_normalize_turn_history(is_array($incomingDetail['turn_history'] ?? null) ? $incomingDetail['turn_history'] : []);
    $historyByNumber = [];
    foreach ($storedHistory as $row) {
        $historyByNumber[(int) $row['turn']] = $row;
    }
    $appended = [];
    foreach ($incomingHistory as $row) {
        $turn = (int) $row['turn'];
        if (!isset($historyByNumber[$turn])) {
            $historyByNumber[$turn] = $row;
            $appended[] = $row;
        }
    }
    ksort($historyByNumber);
    $history = array_values($historyByNumber);
    $highestRecordedTurn = $history ? max(array_keys($historyByNumber)) : 0;
    $storedCount = max(0, min(999, (int) ($storedDetail['turn_count'] ?? 0)));
    $incomingCount = max(0, min(999, (int) ($incomingDetail['turn_count'] ?? 0)));
    $incomingDetail['turn_history'] = $history;
    $incomingDetail['turn_count'] = min(999, max($storedCount, $incomingCount, $highestRecordedTurn));

    return ['detail' => $incomingDetail, 'appended' => $appended];
}
