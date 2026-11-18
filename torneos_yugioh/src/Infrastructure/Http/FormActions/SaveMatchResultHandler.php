<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;
use InvalidArgumentException;

/** Native save_match_result adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class SaveMatchResultHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $matchId = (int) ($post['match_id'] ?? 0);
        if (ygo_team_kind(ygo_competition_for_match($pdo, $matchId)) !== '') {
            throw new InvalidArgumentException(
                'Usa Resultados por equipo para registrar cada puesto o la serie Tag compartida.',
            );
        }
        $offlineRequestId =
            preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($post['offline_request_id'] ?? '')) ?:
            '';
        if ($offlineRequestId !== '') {
            $syncedRequest = $pdo->prepare(
                'SELECT result_code FROM sx_ygo_sync_requests WHERE request_id=? LIMIT 1',
            );
            $syncedRequest->execute([$offlineRequestId]);
            $syncedResult = $syncedRequest->fetchColumn();
            if ($syncedResult !== false) {
                return (string) $syncedResult;
            }
        }
        $values = [];
        for ($i = 1; $i <= 5; $i++) {
            $value = (string) ($post["d$i"] ?? '');
            $values[] = in_array($value, ['A', 'B', 'E'], true) ? $value : '';
        }
        if ($matchId > 0) {
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
                    $duelDetails = json_encode(
                        array_values($decodedDetails),
                        JSON_UNESCAPED_UNICODE,
                    );
                }
            }
            $manualStarts = (string) ($decodedDetails[0]['starts_player'] ?? '');
            $verifiedManualStart = ygo_match_editor_verified_start(
                $pdo,
                $matchId,
                1,
                $manualStarts,
            );
            if ($verifiedManualStart) {
                $startsPlayer = $manualStarts;
            }
            if (
                array_filter($values) &&
                (!$diceWinner || !$turnChosenBy || !$turnDecision || !$startsPlayer) &&
                !$verifiedManualStart
            ) {
                return 'resultado_bloqueado_inicio';
            }
            if (array_filter($values)) {
                $detailsByDuel = [];
                foreach ($decodedDetails ?? [] as $detailRow) {
                    $detailsByDuel[(int) ($detailRow['duel'] ?? 0)] = $detailRow;
                }
                foreach ($values as $valueIndex => $value) {
                    if ($value === '') {
                        continue;
                    }
                    $detailRow = $detailsByDuel[$valueIndex + 1] ?? [];
                    if (!in_array((string) ($detailRow['starts_player'] ?? ''), ['A', 'B'], true)) {
                        return 'resultado_bloqueado_inicio';
                    }
                    if (empty($detailRow['duel_started'])) {
                        return 'resultado_bloqueado_inicio';
                    }
                    if (($detailRow['defeat_method'] ?? '') === 'surrender') {
                        if (!in_array($value, ['A', 'B'], true)) {
                            return 'resultado_bloqueado_rendicion';
                        }
                        $surrenderingSide = $value === 'A' ? 'B' : 'A';
                        $startsSide = (string) ($detailRow['starts_player'] ?? '');
                        $completedTurns = max(
                            (int) ($detailRow['turn_count'] ?? 0),
                            count($detailRow['turn_history'] ?? []),
                        );
                        $activeSide =
                            $completedTurns % 2 === 0
                                ? $startsSide
                                : ($startsSide === 'A'
                                    ? 'B'
                                    : 'A');
                        $recordedSurrender = (string) ($detailRow['surrendered_by'] ?? '');
                        if (
                            $activeSide !== $surrenderingSide ||
                            ($recordedSurrender !== '' && $recordedSurrender !== $surrenderingSide)
                        ) {
                            return 'resultado_bloqueado_rendicion';
                        }
                        $decodedDetails[$valueIndex]['surrendered_by'] = $surrenderingSide;
                        $decodedDetails[$valueIndex]['surrendered_at'] =
                            (string) ($detailRow['surrendered_at'] ?? '') ?: date(DATE_ATOM);
                    }
                    if ($valueIndex > 0 && $values[$valueIndex - 1] === 'E') {
                        $hasRecordedDrawReroll =
                            ($detailRow['turn_reason'] ?? '') === 'draw_reroll' &&
                            in_array((int) ($detailRow['dice_a'] ?? 0), range(1, 6), true) &&
                            in_array((int) ($detailRow['dice_b'] ?? 0), range(1, 6), true) &&
                            in_array((string) ($detailRow['dice_winner'] ?? ''), ['A', 'B'], true);
                        $isHistoricalUnrecordedReroll =
                            ($detailRow['turn_reason'] ?? '') === 'draw_reroll_unrecorded' &&
                            !empty($detailRow['duel_started']) &&
                            !empty($detailRow['turn_history']) &&
                            in_array(
                                (string) ($detailRow['data_origin'] ?? ''),
                                ['added', 'inferred'],
                                true,
                            );
                        if (
                            !$hasRecordedDrawReroll &&
                            !$isHistoricalUnrecordedReroll &&
                            !ygo_match_editor_verified_start(
                                $pdo,
                                $matchId,
                                $valueIndex + 1,
                                (string) ($detailRow['starts_player'] ?? ''),
                            )
                        ) {
                            return 'resultado_bloqueado_inicio';
                        }
                    }
                }
            }
            $dateGuard = ygo_validate_match_result_date($pdo, $matchId);
            if (!$dateGuard['ok']) {
                $pdo->prepare(
                    'INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)',
                )->execute([
                    'match',
                    $matchId,
                    'blocked_result_by_date',
                    json_encode($dateGuard, JSON_UNESCAPED_UNICODE),
                ]);
                return 'resultado_bloqueado_fecha';
            }
            $pdo->beginTransaction();
            ygo_admission_assert_result_editable($pdo, $matchId);
            ygo_workflow_assert_result_editable($pdo, $matchId);
            ygo_season_require_open($pdo, ygo_current_season_id($pdo), true, true);
            $pdo->prepare('INSERT IGNORE INTO sx_ygo_match_results (match_id) VALUES (?)')->execute(
                [$matchId],
            );
            $existingStmt = $pdo->prepare(
                'SELECT r.d1, r.d2, r.d3, r.d4, r.d5, r.duel_details, m.status AS match_status FROM sx_ygo_match_results r JOIN sx_ygo_matches m ON m.id=r.match_id WHERE r.match_id = ? LIMIT 1 FOR UPDATE',
            );
            $existingStmt->execute([$matchId]);
            $existingRow = $existingStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $existingDetails = json_decode((string) ($existingRow['duel_details'] ?? ''), true);
            $correctionAt = max(
                array_merge(
                    [0],
                    array_map(
                        static fn($d) => (int) ($d['manual_correction_at_ms'] ?? 0),
                        is_array($existingDetails) ? $existingDetails : [],
                    ),
                ),
            );
            if (
                $correctionAt > 0 &&
                (int) ($post['result_correction_version'] ?? 0) !== $correctionAt
            ) {
                throw new InvalidArgumentException(
                    'La partida fue modificada. Recarga antes de guardar para no sobrescribir la corrección.',
                );
            }
            $newTurnAuditRows = [];
            if (isset($decodedDetails) && is_array($decodedDetails)) {
                $existingByDuel = [];
                foreach (is_array($existingDetails) ? $existingDetails : [] as $existingDetail) {
                    if (is_array($existingDetail)) {
                        $existingByDuel[(int) ($existingDetail['duel'] ?? 0)] = $existingDetail;
                    }
                }
                $persistedTurnsStmt = $pdo->prepare('
                    SELECT duel_number, turn_number, side, ended_at, elapsed_seconds
                    FROM sx_ygo_turn_audit
                    WHERE match_id = ?
                    ORDER BY duel_number, turn_number
                ');
                $persistedTurnsStmt->execute([$matchId]);
                foreach ($persistedTurnsStmt->fetchAll(PDO::FETCH_ASSOC) as $turnRow) {
                    $duelNumber = (int) $turnRow['duel_number'];
                    if (!isset($existingByDuel[$duelNumber])) {
                        $existingByDuel[$duelNumber] = [
                            'duel' => $duelNumber,
                            'turn_count' => 0,
                            'turn_history' => [],
                        ];
                    }
                    $existingByDuel[$duelNumber]['turn_history'][] = [
                        'turn' => (int) $turnRow['turn_number'],
                        'side' => (string) $turnRow['side'],
                        'ended_at' => (string) ($turnRow['ended_at'] ?? ''),
                        'elapsed_seconds' => (int) $turnRow['elapsed_seconds'],
                    ];
                    $existingByDuel[$duelNumber]['turn_count'] = max(
                        (int) ($existingByDuel[$duelNumber]['turn_count'] ?? 0),
                        (int) $turnRow['turn_number'],
                    );
                }
                foreach ($decodedDetails as &$incomingDetail) {
                    $duelNumber = (int) ($incomingDetail['duel'] ?? 0);
                    $storedDetail = $existingByDuel[$duelNumber] ?? [];
                    if (!empty($storedDetail['manual_correction_at_ms'])) {
                        $incomingDetail['manual_correction_at_ms'] =
                            $storedDetail['manual_correction_at_ms'];
                    }
                    $mergedTurns = ygo_merge_monotonic_turns($storedDetail, $incomingDetail);
                    $incomingDetail = $mergedTurns['detail'];
                    foreach ($mergedTurns['appended'] as $turnRow) {
                        $newTurnAuditRows[] = ['duel' => $duelNumber, 'row' => $turnRow];
                    }
                    if (!empty($storedDetail['duel_started'])) {
                        $storedTimerMode = strtolower((string) ($storedDetail['timer_mode'] ?? ''));
                        $incomingDetail['duel_started'] = true;
                        $incomingDetail['duel_started_at'] = max(
                            0,
                            (int) ($storedDetail['duel_started_at'] ??
                                ($incomingDetail['duel_started_at'] ?? 0)),
                        );
                        if (in_array($storedTimerMode, ['none', 'countdown'], true)) {
                            $incomingDetail['timer_mode'] = $storedTimerMode;
                        }
                    }
                    if (($incomingDetail['timer_mode'] ?? 'none') !== 'countdown') {
                        $incomingDetail['timer_running'] = false;
                        $incomingDetail['timer_started_at'] = null;
                    }
                }
                unset($incomingDetail);
                $duelDetails = json_encode(array_values($decodedDetails), JSON_UNESCAPED_UNICODE);
            }
            if (isset($decodedDetails) && is_array($decodedDetails)) {
                $turnAuditInsert = $pdo->prepare('
                    INSERT IGNORE INTO sx_ygo_turn_audit
                        (match_id, duel_number, turn_number, side, ended_at, elapsed_seconds)
                    VALUES (?, ?, ?, ?, ?, ?)
                ');
                foreach ($decodedDetails as $detailRow) {
                    $duelNumber = max(1, min(5, (int) ($detailRow['duel'] ?? 0)));
                    foreach (
                        ygo_normalize_turn_history(
                            is_array($detailRow['turn_history'] ?? null)
                                ? $detailRow['turn_history']
                                : [],
                        )
                        as $turnRow
                    ) {
                        $turnAuditInsert->execute([
                            $matchId,
                            $duelNumber,
                            (int) $turnRow['turn'],
                            (string) $turnRow['side'],
                            (string) $turnRow['ended_at'] !== ''
                                ? (string) $turnRow['ended_at']
                                : null,
                            (int) $turnRow['elapsed_seconds'],
                        ]);
                    }
                }
            }
            $existingValues = [];
            for ($i = 1; $i <= 5; $i++) {
                $storedValue = (string) ($existingRow["d$i"] ?? '');
                $existingValues[] = in_array($storedValue, ['A', 'B', 'E'], true)
                    ? $storedValue
                    : '';
            }
            $firstWritable = 0;
            while ($firstWritable < 5 && $existingValues[$firstWritable] !== '') {
                $firstWritable++;
            }
            if ($duelDetails === '') {
                for ($index = 0; $index < 5; $index++) {
                    if ($index < $firstWritable) {
                        $values[$index] = $values[$index] ?: $existingValues[$index];
                        continue;
                    }
                    if ($index > $firstWritable) {
                        $values[$index] = $existingValues[$index] ?: '';
                    }
                }
            }
            $evaluation = ygo_apply_event_model_to_result(
                $pdo,
                $matchId,
                ygo_evaluate_duel_with_rule($pdo, $matchId, $values),
                $values,
            );
            $completionNow = ygo_completion_now();
            $decodedDetails = ygo_completion_games(
                $decodedDetails ?? [],
                is_array($existingDetails) ? $existingDetails : [],
                $existingValues,
                $values,
                $completionNow,
                !in_array(
                    strtoupper((string) ($existingRow['match_status'] ?? '')),
                    ['FINALIZADA', 'FINALIZADO', 'COMPLETED', 'FINISHED'],
                    true,
                ),
            );
            $duelDetails = json_encode($decodedDetails, JSON_UNESCAPED_UNICODE);
            $pdo->prepare(
                "
                INSERT INTO sx_ygo_match_results (match_id, d1, d2, d3, d4, d5, result, score, validation_status, notes, dice_roll, turn_choice, dice_winner, turn_chosen_by, turn_decision, starts_player, duel_details)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    d1 = VALUES(d1), d2 = VALUES(d2), d3 = VALUES(d3), d4 = VALUES(d4), d5 = VALUES(d5),
                    result = VALUES(result), score = VALUES(score), validation_status = VALUES(validation_status), notes = VALUES(notes),
                    dice_roll = VALUES(dice_roll), turn_choice = VALUES(turn_choice), dice_winner = VALUES(dice_winner),
                    turn_chosen_by = VALUES(turn_chosen_by), turn_decision = VALUES(turn_decision), starts_player = VALUES(starts_player),
                    duel_details = VALUES(duel_details)
            ",
            )->execute([
                $matchId,
                $values[0] ?: null,
                $values[1] ?: null,
                $values[2] ?: null,
                $values[3] ?: null,
                $values[4] ?: null,
                $evaluation['winner'],
                $evaluation['score'],
                $evaluation['validation'],
                trim((string) ($post['result_notes'] ?? '')),
                $diceRoll !== '' ? $diceRoll : null,
                $turnChoice !== '' ? $turnChoice : null,
                $diceWinner,
                $turnChosenBy,
                $turnDecision,
                $startsPlayer,
                $duelDetails !== '' ? $duelDetails : null,
            ]);
            if (isset($decodedDetails) && is_array($decodedDetails)) {
                $matchSnapshotStmt = $pdo->prepare('
                    SELECT m.competition_id, m.division_id, m.deck_a_id, m.deck_b_id,
                           c.event_ruleset, c.ruleset_version, dv.name AS division_name
                    FROM sx_ygo_matches m
                    LEFT JOIN sx_ygo_competitions c ON c.id = m.competition_id
                    LEFT JOIN sx_ygo_divisions dv ON dv.id = COALESCE(m.division_id, c.division_id)
                    WHERE m.id = ? LIMIT 1
                ');
                $matchSnapshotStmt->execute([$matchId]);
                $matchSnapshot = $matchSnapshotStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                $deckSnapshotStmt = $pdo->prepare('
                    SELECT d.id, d.team_id, d.name, d.archetype, d.variations,
                           t.name AS team_name
                    FROM sx_ygo_decks d
                    LEFT JOIN sx_ygo_teams t ON t.id = d.team_id
                    WHERE d.id = ? LIMIT 1
                ');
                $decklistSnapshotStmt = $pdo->prepare('
                    SELECT card_name, role, quantity, section, notes
                    FROM sx_ygo_deck_cards WHERE deck_id = ? ORDER BY section, id
                ');
                $insertSnapshotStmt = $pdo->prepare('
                    INSERT IGNORE INTO sx_ygo_match_snapshots
                        (match_id, duel_number, side, competition_id, deck_id, team_id, player_id,
                         deck_name, archetype, variation, team_name, ruleset_code, ruleset_version,
                         division_name, decklist_json, decklist_hash, data_origin, snapshot_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ');
                foreach ($decodedDetails as $detailRow) {
                    $duelNumber = max(1, min(5, (int) ($detailRow['duel'] ?? 0)));
                    foreach (['A', 'B'] as $side) {
                        $suffix = strtolower($side);
                        $defaultDeckKey = $side === 'A' ? 'deck_a_id' : 'deck_b_id';
                        $deckId = max(
                            0,
                            (int) ($detailRow['deck_' . $suffix . '_id'] ??
                                ($matchSnapshot[$defaultDeckKey] ?? 0)),
                        );
                        if ($deckId <= 0) {
                            continue;
                        }
                        $deckSnapshotStmt->execute([$deckId]);
                        $deckSnapshot = $deckSnapshotStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                        $decklistSnapshotStmt->execute([$deckId]);
                        $decklist = $decklistSnapshotStmt->fetchAll(PDO::FETCH_ASSOC);
                        $originValue = strtolower(
                            trim(
                                (string) ($detailRow['data_origin'] ??
                                    ($detailRow['source'] ?? '')),
                            ),
                        );
                        $hasTurnHistory =
                            !empty($detailRow['turn_history']) &&
                            is_array($detailRow['turn_history']);
                        $origin =
                            str_contains($originValue, 'infer') ||
                            str_contains($originValue, 'backfill')
                                ? 'inferred'
                                : (str_contains($originValue, 'add')
                                    ? 'added'
                                    : ($originValue === 'captured' || $hasTurnHistory
                                        ? 'captured'
                                        : 'unknown'));
                        $decklistJson = json_encode($decklist, JSON_UNESCAPED_UNICODE);
                        $insertSnapshotStmt->execute([
                            $matchId,
                            $duelNumber,
                            $side,
                            (int) ($matchSnapshot['competition_id'] ?? 0) ?: null,
                            $deckId,
                            (int) ($deckSnapshot['team_id'] ?? 0) ?: null,
                            max(0, (int) ($detailRow['player_' . $suffix . '_id'] ?? 0)) ?: null,
                            (string) ($deckSnapshot['name'] ?? ''),
                            (string) ($deckSnapshot['archetype'] ?? ''),
                            (string) ($deckSnapshot['variations'] ?? ''),
                            (string) ($deckSnapshot['team_name'] ?? ''),
                            (string) ($matchSnapshot['event_ruleset'] ?? ''),
                            (string) ($matchSnapshot['ruleset_version'] ?? ''),
                            (string) ($matchSnapshot['division_name'] ?? ''),
                            $decklistJson,
                            hash('sha256', $decklistJson ?: '[]'),
                            $origin,
                            $origin === 'captured' ? 'exact' : $origin,
                        ]);
                    }
                }
            }
            ygo_completion_set_status($pdo, $matchId, $evaluation['status'], $completionNow);
            $competitionStmt = $pdo->prepare(
                'SELECT competition_id FROM sx_ygo_matches WHERE id = ? LIMIT 1',
            );
            $competitionStmt->execute([$matchId]);
            $resultCompetitionId = (int) ($competitionStmt->fetchColumn() ?: 0);
            if (
                $resultCompetitionId > 0 &&
                !in_array('ranking_impact:NONE', $evaluation['model_effects'] ?? [], true)
            ) {
                ygo_persist_competition_standings(
                    $pdo,
                    ygo_current_season_id($pdo),
                    $resultCompetitionId,
                    'save_match_result',
                );
            }
            $pdo->prepare(
                'INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)',
            )->execute([
                'match',
                $matchId,
                'save_result',
                json_encode(
                    [
                        'duels' => $values,
                        'evaluation' => $evaluation,
                        'date_guard' => $dateGuard,
                        'dice_roll' => $diceRoll,
                        'turn_choice' => $turnChoice,
                        'dice_winner' => $diceWinner,
                        'turn_chosen_by' => $turnChosenBy,
                        'turn_decision' => $turnDecision,
                        'starts_player' => $startsPlayer,
                        'duel_details' => $duelDetails,
                    ],
                    JSON_UNESCAPED_UNICODE,
                ),
            ]);
            $turnAuditLog = $pdo->prepare(
                'INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)',
            );
            foreach ($newTurnAuditRows as $auditRow) {
                $turnAuditLog->execute([
                    'match',
                    $matchId,
                    'turn_added',
                    json_encode(
                        [
                            'duel' => (int) $auditRow['duel'],
                            'turn' => (int) $auditRow['row']['turn'],
                            'side' => (string) $auditRow['row']['side'],
                            'ended_at' => (string) $auditRow['row']['ended_at'],
                            'elapsed_seconds' => (int) $auditRow['row']['elapsed_seconds'],
                        ],
                        JSON_UNESCAPED_UNICODE,
                    ),
                ]);
            }
            ygo_sync_meta_history($pdo);
            ygo_sync_elo_history($pdo);
            ygo_sync_global_ranking_history($pdo);
            ygo_sync_event_summaries($pdo, $resultCompetitionId ?: null);
            ygo_sync_medal_unlocks($pdo, $matchId);
            $savedLiveMatch = ygo_live_duel_match($pdo, $matchId);
            if ($savedLiveMatch) {
                $savedLiveState = ygo_live_duel_initial_state($savedLiveMatch);
                if (($evaluation['status'] ?? '') === 'FINALIZADA') {
                    $savedLiveState['status'] = 'completed';
                    $savedLiveState['active_side'] = '';
                }
                $pdo->prepare(
                    "
                    INSERT INTO sx_ygo_live_duel_state (match_id, duel_number, revision, state_json, updated_by)
                    VALUES (?, ?, 1, ?, 'judge')
                    ON DUPLICATE KEY UPDATE duel_number=VALUES(duel_number), revision=revision+1, state_json=VALUES(state_json), updated_by='judge'
                ",
                )->execute([
                    $matchId,
                    $savedLiveState['duel_number'],
                    json_encode($savedLiveState, JSON_UNESCAPED_UNICODE),
                ]);
            }
            if ($offlineRequestId !== '') {
                $pdo->prepare(
                    'INSERT INTO sx_ygo_sync_requests (request_id,match_id,payload_hash,result_code) VALUES (?,?,?,?)',
                )->execute([
                    $offlineRequestId,
                    $matchId,
                    hash('sha256', json_encode($post, JSON_UNESCAPED_UNICODE) ?: ''),
                    'resultado',
                ]);
            }
            $pdo->commit();
        }
        return 'resultado';

        return null;
    }
}
