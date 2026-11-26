<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Matches;

final class MatchResultPayload
{
    public static function values(array $post): array
    {
        $values = [];
        for ($i = 1; $i <= 5; $i++) {
            $value = (string) ($post["d$i"] ?? '');
            $values[] = in_array($value, ['A', 'B', 'E'], true) ? $value : '';
        }
        return $values;
    }

    public static function offlineRequestId(array $post): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($post['offline_request_id'] ?? '')) ?:
            '';
    }

    public static function normalize(array $post, array $values): array
    {
        $decodedDetails = null;
        $diceRoll = trim((string) ($post['result_dice_roll'] ?? ''));
        $turnChoice = trim((string) ($post['result_turn_choice'] ?? ''));
        $duelDetails = trim((string) ($post['result_duel_details'] ?? ''));
        $dicePayload = [];
        if ($diceRoll !== '') {
            $decodedDice = json_decode($diceRoll, true);
            $dicePayload = is_array($decodedDice) ? $decodedDice : [];
        }
        $diceWinner = strtoupper((string) ($dicePayload['winner'] ?? ''));
        $turnChosenBy = strtoupper((string) ($dicePayload['chosen_by'] ?? $diceWinner));
        $turnDecision = strtolower((string) ($dicePayload['choice'] ?? ''));
        $startsPlayer = strtoupper((string) ($dicePayload['starts'] ?? ''));
        $diceWinner = in_array($diceWinner, ['A', 'B'], true) ? $diceWinner : null;
        $turnChosenBy = in_array($turnChosenBy, ['A', 'B'], true) ? $turnChosenBy : null;
        $turnDecision = in_array($turnDecision, ['primero', 'segundo', 'first', 'second'], true)
            ? ($turnDecision === 'first'
                ? 'primero'
                : ($turnDecision === 'second'
                    ? 'segundo'
                    : $turnDecision))
            : null;
        $startsPlayer = in_array($startsPlayer, ['A', 'B'], true) ? $startsPlayer : null;
        if ($duelDetails !== '') {
            $decodedDetails = json_decode($duelDetails, true);
            if (is_array($decodedDetails)) {
                foreach ($decodedDetails as $detailIndex => $detailRow) {
                    if (!is_array($detailRow)) {
                        unset($decodedDetails[$detailIndex]);
                        continue;
                    }
                    $detailChosenBy = strtoupper((string) ($detailRow['turn_chosen_by'] ?? ''));
                    $detailDecision = strtolower((string) ($detailRow['turn_decision'] ?? ''));
                    $detailStarts = strtoupper((string) ($detailRow['starts_player'] ?? ''));
                    $decodedDetails[$detailIndex]['turn_chosen_by'] = in_array(
                        $detailChosenBy,
                        ['A', 'B'],
                        true,
                    )
                        ? $detailChosenBy
                        : '';
                    $decodedDetails[$detailIndex]['turn_decision'] = in_array(
                        $detailDecision,
                        ['primero', 'segundo', 'first', 'second'],
                        true,
                    )
                        ? ($detailDecision === 'first'
                            ? 'primero'
                            : ($detailDecision === 'second'
                                ? 'segundo'
                                : $detailDecision))
                        : '';
                    $decodedDetails[$detailIndex]['starts_player'] = in_array(
                        $detailStarts,
                        ['A', 'B'],
                        true,
                    )
                        ? $detailStarts
                        : '';
                    $decodedDetails[$detailIndex]['dice_a'] = max(
                        0,
                        min(6, (int) ($detailRow['dice_a'] ?? 0)),
                    );
                    $decodedDetails[$detailIndex]['dice_b'] = max(
                        0,
                        min(6, (int) ($detailRow['dice_b'] ?? 0)),
                    );
                    $detailDiceWinner = strtoupper(
                        (string) ($detailRow['dice_winner'] ??
                            ($detailRow['turn_chosen_by'] ?? '')),
                    );
                    $decodedDetails[$detailIndex]['dice_winner'] = in_array(
                        $detailDiceWinner,
                        ['A', 'B'],
                        true,
                    )
                        ? $detailDiceWinner
                        : '';
                    $turnReason = strtolower(trim((string) ($detailRow['turn_reason'] ?? '')));
                    $decodedDetails[$detailIndex]['turn_reason'] = in_array(
                        $turnReason,
                        ['dice', 'previous_duel_loss', 'draw_reroll', 'draw_reroll_unrecorded'],
                        true,
                    )
                        ? $turnReason
                        : '';
                    $decodedDetails[$detailIndex]['notes'] = mb_substr(
                        trim((string) ($detailRow['notes'] ?? '')),
                        0,
                        500,
                    );
                    $method = strtolower(trim((string) ($detailRow['defeat_method'] ?? '')));
                    $decodedDetails[$detailIndex]['defeat_method'] = in_array(
                        $method,
                        ['life_points', 'surrender', 'time_limit', 'deck_out', 'effect_win'],
                        true,
                    )
                        ? $method
                        : '';
                    $surrenderedBy = strtoupper(
                        trim((string) ($detailRow['surrendered_by'] ?? '')),
                    );
                    $decodedDetails[$detailIndex]['surrendered_by'] = in_array(
                        $surrenderedBy,
                        ['A', 'B'],
                        true,
                    )
                        ? $surrenderedBy
                        : '';
                    $decodedDetails[$detailIndex]['surrendered_at'] = mb_substr(
                        trim((string) ($detailRow['surrendered_at'] ?? '')),
                        0,
                        40,
                    );
                    $decodedDetails[$detailIndex]['turn_count'] = max(
                        0,
                        min(999, (int) ($detailRow['turn_count'] ?? 0)),
                    );
                    $decodedDetails[$detailIndex]['brick_a'] = !empty($detailRow['brick_a']);
                    $decodedDetails[$detailIndex]['brick_b'] = !empty($detailRow['brick_b']);
                    $decodedDetails[$detailIndex]['duration_seconds'] = max(
                        0,
                        min(7200, (int) ($detailRow['duration_seconds'] ?? 0)),
                    );
                    $decodedDetails[$detailIndex]['timer_running'] = !empty(
                        $detailRow['timer_running']
                    );
                    $decodedDetails[$detailIndex]['timer_started_at'] = $decodedDetails[
                        $detailIndex
                    ]['timer_running']
                        ? max(0, (int) ($detailRow['timer_started_at'] ?? 0))
                        : null;
                    $decodedDetails[$detailIndex]['timer_updated_at'] = max(
                        0,
                        (int) ($detailRow['timer_updated_at'] ?? 0),
                    );
                    $decodedDetails[$detailIndex]['duel_started'] =
                        !empty($detailRow['duel_started']) ||
                        in_array((string) ($detailRow['winner'] ?? ''), ['A', 'B', 'E'], true);
                    $decodedDetails[$detailIndex]['duel_started_at'] = $decodedDetails[
                        $detailIndex
                    ]['duel_started']
                        ? max(0, (int) ($detailRow['duel_started_at'] ?? 0))
                        : null;
                    $dataOrigin = strtolower(trim((string) ($detailRow['data_origin'] ?? '')));
                    $decodedDetails[$detailIndex]['data_origin'] = in_array(
                        $dataOrigin,
                        ['captured', 'added', 'inferred'],
                        true,
                    )
                        ? $dataOrigin
                        : ($decodedDetails[$detailIndex]['duel_started_at']
                            ? 'captured'
                            : 'added');
                    $timerMode = strtolower(trim((string) ($detailRow['timer_mode'] ?? '')));
                    $decodedDetails[$detailIndex]['timer_mode'] = in_array(
                        $timerMode,
                        ['none', 'countdown'],
                        true,
                    )
                        ? $timerMode
                        : 'none';
                    $turnHistory = is_array($detailRow['turn_history'] ?? null)
                        ? array_slice($detailRow['turn_history'], 0, 999)
                        : [];
                    $decodedDetails[$detailIndex]['turn_history'] = array_values(
                        array_map(static function (array $turn): array {
                            $side = strtoupper((string) ($turn['side'] ?? ''));
                            return [
                                'turn' => max(1, min(999, (int) ($turn['turn'] ?? 1))),
                                'side' => in_array($side, ['A', 'B'], true) ? $side : '',
                                'ended_at' => mb_substr(
                                    trim((string) ($turn['ended_at'] ?? '')),
                                    0,
                                    40,
                                ),
                                'elapsed_seconds' => max(
                                    0,
                                    min(7200, (int) ($turn['elapsed_seconds'] ?? 0)),
                                ),
                            ];
                        }, array_filter($turnHistory, 'is_array')),
                    );
                    $decodedDetails[$detailIndex]['lp_start_a'] = max(
                        1,
                        min(99999, (int) ($detailRow['lp_start_a'] ?? 8000)),
                    );
                    $decodedDetails[$detailIndex]['lp_start_b'] = max(
                        1,
                        min(99999, (int) ($detailRow['lp_start_b'] ?? 8000)),
                    );
                    $decodedDetails[$detailIndex]['deck_a_id'] = max(
                        0,
                        (int) ($detailRow['deck_a_id'] ?? 0),
                    );
                    $decodedDetails[$detailIndex]['deck_b_id'] = max(
                        0,
                        (int) ($detailRow['deck_b_id'] ?? 0),
                    );
                    $decodedDetails[$detailIndex]['player_a_id'] = max(
                        0,
                        (int) ($detailRow['player_a_id'] ?? 0),
                    );
                    $decodedDetails[$detailIndex]['player_b_id'] = max(
                        0,
                        (int) ($detailRow['player_b_id'] ?? 0),
                    );
                    $historyRows = is_array($detailRow['lp_history'] ?? null)
                        ? array_slice($detailRow['lp_history'], 0, 100)
                        : [];
                    $decodedDetails[$detailIndex]['lp_history'] = array_values(
                        array_map(static function (array $history): array {
                            $side = strtolower((string) ($history['side'] ?? ''));
                            $operation = strtolower((string) ($history['operation'] ?? ''));
                            return [
                                'side' => in_array($side, ['a', 'b'], true) ? $side : '',
                                'operation' => in_array(
                                    $operation,
                                    ['subtract', 'add', 'half', 'reset'],
                                    true,
                                )
                                    ? $operation
                                    : '',
                                'amount' => max(0, min(99999, (int) ($history['amount'] ?? 0))),
                                'before' => max(0, min(99999, (int) ($history['before'] ?? 0))),
                                'after' => max(0, min(99999, (int) ($history['after'] ?? 0))),
                            ];
                        }, array_filter($historyRows, 'is_array')),
                    );
                }
                if ($diceWinner && $turnChosenBy && $turnDecision && $startsPlayer) {
                    $foundD1 = false;
                    foreach ($decodedDetails as &$detailRow) {
                        if ((int) ($detailRow['duel'] ?? 0) === 1) {
                            $detailRow['turn_chosen_by'] =
                                $detailRow['turn_chosen_by'] ?: $turnChosenBy;
                            $detailRow['turn_decision'] =
                                $detailRow['turn_decision'] ?: $turnDecision;
                            $detailRow['starts_player'] =
                                $detailRow['starts_player'] ?: $startsPlayer;
                            $detailRow['turn_reason'] = $detailRow['turn_reason'] ?? 'dice';
                            $foundD1 = true;
                            break;
                        }
                    }
                    unset($detailRow);
                    if (!$foundD1) {
                        array_unshift($decodedDetails, [
                            'duel' => 1,
                            'winner' => $values[0] ?: '',
                            'defeat_method' => '',
                            'lp_a' => '',
                            'lp_b' => '',
                            'turn_chosen_by' => $turnChosenBy,
                            'turn_decision' => $turnDecision,
                            'starts_player' => $startsPlayer,
                            'turn_reason' => 'dice',
                        ]);
                    }
                }
                $duelDetails = json_encode(array_values($decodedDetails), JSON_UNESCAPED_UNICODE);
            }
        }
        return [
            'diceRoll' => $diceRoll,
            'turnChoice' => $turnChoice,
            'duelDetails' => $duelDetails,
            'diceWinner' => $diceWinner,
            'turnChosenBy' => $turnChosenBy,
            'turnDecision' => $turnDecision,
            'startsPlayer' => $startsPlayer,
            'decodedDetails' => $decodedDetails,
        ];
    }
}
