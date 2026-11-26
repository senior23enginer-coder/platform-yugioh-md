<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Matches;

use DateTimeZone;
use InvalidArgumentException;
use StationX\Yugioh\Application\Shared\Clock;
use StationX\Yugioh\Application\Shared\TransactionBoundary;
use StationX\Yugioh\Domain\Matches\CompletionPolicy;
use StationX\Yugioh\Domain\Matches\MatchResultPayload;
use StationX\Yugioh\Domain\Matches\ResultIntegrityPolicy;
use StationX\Yugioh\Domain\Matches\TurnHistory;

final readonly class SaveMatchResult
{
    public function __construct(
        private MatchResultStore $store,
        private MatchResultRules $rules,
        private MatchResultEffects $effects,
        private PublishMatchSnapshots $snapshots,
        private TransactionBoundary $transaction,
        private Clock $clock,
    ) {}

    public function execute(array $post): string
    {
        $matchId = (int) ($post['match_id'] ?? 0);
        if ($this->rules->teamKindForMatch($matchId) !== '') {
            throw new InvalidArgumentException(
                'Usa Resultados por equipo para registrar cada puesto o la serie Tag compartida.',
            );
        }
        $offlineRequestId = MatchResultPayload::offlineRequestId($post);
        if ($offlineRequestId !== '') {
            $syncedResult = $this->store->syncedResult($offlineRequestId);
            if ($syncedResult !== false) {
                return (string) $syncedResult;
            }
        }
        $values = MatchResultPayload::values($post);
        if ($matchId > 0) {
            [
                'diceRoll' => $diceRoll,
                'turnChoice' => $turnChoice,
                'duelDetails' => $duelDetails,
                'diceWinner' => $diceWinner,
                'turnChosenBy' => $turnChosenBy,
                'turnDecision' => $turnDecision,
                'startsPlayer' => $startsPlayer,
                'decodedDetails' => $decodedDetails,
            ] = MatchResultPayload::normalize($post, $values);
            $manualStarts = (string) ($decodedDetails[0]['starts_player'] ?? '');
            $verifiedManualStart = $this->rules->verifiedStart($matchId, 1, $manualStarts);
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
                            (string) ($detailRow['surrendered_at'] ?? '') ?:
                            $this->clock->now()->format(DATE_ATOM);
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
                            !$this->rules->verifiedStart(
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
            $dateGuard = $this->rules->validateDate($matchId);
            if (!$dateGuard['ok']) {
                $this->store->audit($matchId, 'blocked_result_by_date', $dateGuard);
                return 'resultado_bloqueado_fecha';
            }
            return $this->transaction->run(function () use (
                $matchId,
                $post,
                $values,
                $diceRoll,
                $turnChoice,
                $duelDetails,
                $diceWinner,
                $turnChosenBy,
                $turnDecision,
                $startsPlayer,
                $decodedDetails,
                $offlineRequestId,
                $dateGuard,
            ): string {
                $this->rules->assertEditable($matchId);
                $this->store->ensure($matchId);
                $existingRow = $this->store->lockExisting($matchId);
                $existingDetails = json_decode((string) ($existingRow['duel_details'] ?? ''), true);
                ResultIntegrityPolicy::assertCorrectionVersion(
                    $existingDetails,
                    (int) ($post['result_correction_version'] ?? 0),
                );
                $newTurnAuditRows = [];
                if (isset($decodedDetails) && is_array($decodedDetails)) {
                    $existingByDuel = [];
                    foreach (
                        is_array($existingDetails) ? $existingDetails : []
                        as $existingDetail
                    ) {
                        if (is_array($existingDetail)) {
                            $existingByDuel[(int) ($existingDetail['duel'] ?? 0)] = $existingDetail;
                        }
                    }
                    foreach ($this->store->persistedTurns($matchId) as $turnRow) {
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
                        $mergedTurns = TurnHistory::mergeMonotonicTurns(
                            $storedDetail,
                            $incomingDetail,
                        );
                        $incomingDetail = $mergedTurns['detail'];
                        foreach ($mergedTurns['appended'] as $turnRow) {
                            $newTurnAuditRows[] = ['duel' => $duelNumber, 'row' => $turnRow];
                        }
                        if (!empty($storedDetail['duel_started'])) {
                            $storedTimerMode = strtolower(
                                (string) ($storedDetail['timer_mode'] ?? ''),
                            );
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
                    $duelDetails = json_encode(
                        array_values($decodedDetails),
                        JSON_UNESCAPED_UNICODE,
                    );
                }
                if (isset($decodedDetails) && is_array($decodedDetails)) {
                    $this->store->appendTurns(
                        $matchId,
                        ResultIntegrityPolicy::appendTurnRows($decodedDetails),
                    );
                }
                $existingValues = [];
                for ($i = 1; $i <= 5; $i++) {
                    $storedValue = (string) ($existingRow["d$i"] ?? '');
                    $existingValues[] = in_array($storedValue, ['A', 'B', 'E'], true)
                        ? $storedValue
                        : '';
                }
                if ($duelDetails === '') {
                    $values = ResultIntegrityPolicy::preserveStoredValues($existingValues, $values);
                }
                $evaluation = $this->rules->evaluate($matchId, $values);
                $completionNow = $this->clock
                    ->now()
                    ->setTimezone(new DateTimeZone('America/Bogota'))
                    ->format('Y-m-d H:i:s');
                $decodedDetails = CompletionPolicy::completionGames(
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
                $this->store->save([
                    'matchId' => $matchId,
                    'd1' => $values[0] ?: null,
                    'd2' => $values[1] ?: null,
                    'd3' => $values[2] ?: null,
                    'd4' => $values[3] ?: null,
                    'd5' => $values[4] ?: null,
                    'winner' => $evaluation['winner'],
                    'score' => $evaluation['score'],
                    'validation' => $evaluation['validation'],
                    'notes' => trim((string) ($post['result_notes'] ?? '')),
                    'diceRoll' => $diceRoll !== '' ? $diceRoll : null,
                    'turnChoice' => $turnChoice !== '' ? $turnChoice : null,
                    'diceWinner' => $diceWinner,
                    'turnChosenBy' => $turnChosenBy,
                    'turnDecision' => $turnDecision,
                    'startsPlayer' => $startsPlayer,
                    'duelDetails' => $duelDetails !== '' ? $duelDetails : null,
                ]);
                if (isset($decodedDetails) && is_array($decodedDetails)) {
                    $this->snapshots->execute($matchId, $decodedDetails);
                }
                $this->effects->setStatus($matchId, $evaluation['status'], $completionNow);
                $resultCompetitionId = $this->store->competitionId($matchId);
                if (
                    $resultCompetitionId > 0 &&
                    !in_array('ranking_impact:NONE', $evaluation['model_effects'] ?? [], true)
                ) {
                    $this->effects->persistStandings($resultCompetitionId);
                }
                $this->store->audit($matchId, 'save_result', [
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
                ]);
                foreach ($newTurnAuditRows as $auditRow) {
                    $this->store->audit($matchId, 'turn_added', [
                        'duel' => (int) $auditRow['duel'],
                        'turn' => (int) $auditRow['row']['turn'],
                        'side' => (string) $auditRow['row']['side'],
                        'ended_at' => (string) $auditRow['row']['ended_at'],
                        'elapsed_seconds' => (int) $auditRow['row']['elapsed_seconds'],
                    ]);
                }
                $this->effects->syncStatistics($resultCompetitionId, $matchId);
                $savedLiveMatch = $this->effects->liveMatch($matchId);
                if ($savedLiveMatch) {
                    $savedLiveState = $this->effects->initialLiveState($savedLiveMatch);
                    if (($evaluation['status'] ?? '') === 'FINALIZADA') {
                        $savedLiveState['status'] = 'completed';
                        $savedLiveState['active_side'] = '';
                    }
                    $this->store->saveLiveState($matchId, $savedLiveState);
                }
                if ($offlineRequestId !== '') {
                    $this->store->recordSync(
                        $offlineRequestId,
                        $matchId,
                        hash('sha256', json_encode($post, JSON_UNESCAPED_UNICODE) ?: ''),
                    );
                }
                return 'resultado';
            });
        }
        return 'resultado';
    }
}
