<?php
declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/src/autoload.php';

/** Replay the immutable history to recover the stage preceding the current one.
 * No score, pairing or BYE is inferred from the current standings.
 */
function ygo_workflow_recovery_plan(array $state): array
{
    return \StationX\Yugioh\Domain\Competitions\WorkflowRecovery::recoveryPlan($state);
}

/** Only empty downstream fixtures may be removed; captured play is never discarded. */
function ygo_workflow_recovery_token(array $state): string
{
    return \StationX\Yugioh\Domain\Competitions\WorkflowRecovery::recoveryToken($state);
}

function ygo_workflow_revert(
    PDO $pdo,
    int $competitionId,
    int $expectedRound,
    string $reason,
    string $expectedToken,
): int {
    return ygo_with_event_write_lock($pdo, static function () use (
        $pdo,
        $competitionId,
        $expectedRound,
        $reason,
        $expectedToken,
    ): int {
        $reason = trim($reason);
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 500) {
            throw new InvalidArgumentException(
                'Explica el motivo de la correccion entre 10 y 500 caracteres.',
            );
        }
        $owns = !$pdo->inTransaction();
        if ($owns) {
            $pdo->beginTransaction();
        }
        try {
            $q = $pdo->prepare(
                'SELECT * FROM sx_ygo_competitions WHERE id=? AND active=1 FOR UPDATE',
            );
            $q->execute([$competitionId]);
            $c = $q->fetch(PDO::FETCH_ASSOC);
            if (!$c) {
                throw new InvalidArgumentException('Evento inexistente.');
            }
            ygo_season_require_open($pdo, (int) $c['season_id'], false, true);
            if (
                in_array(
                    ygo_normalize_status_key((string) $c['status']),
                    ['completed', 'closed', 'archived', 'cancelled'],
                    true,
                )
            ) {
                throw new InvalidArgumentException(
                    'El evento esta cerrado; sus fases se conservan.',
                );
            }
            $s = ygo_workflow_load($pdo, $c, true);
            if (
                !$s ||
                $s['round'] !== $expectedRound ||
                !hash_equals(ygo_workflow_recovery_token($s), $expectedToken)
            ) {
                throw new InvalidArgumentException(
                    'La fase cambio desde que abriste la ficha. Recarga antes de corregir.',
                );
            }
            $plan = ygo_workflow_recovery_plan($s);
            $ids = $plan['remove_ids'];
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $q = $pdo->prepare(
                "SELECT id,status FROM sx_ygo_matches WHERE competition_id=? AND id IN ($marks) FOR UPDATE",
            );
            $q->execute([$competitionId, ...$ids]);
            $matches = $q->fetchAll(PDO::FETCH_ASSOC);
            if (
                count($matches) !== count($ids) ||
                array_filter($matches, static fn($m) => $m['status'] !== 'PENDIENTE')
            ) {
                throw new InvalidArgumentException(
                    'La fase siguiente ya tiene combates iniciados; no se puede eliminar.',
                );
            }
            foreach (
                ['match_results', 'turn_audit', 'live_duel_state', 'match_snapshots', 'checkins']
                as $table
            ) {
                $q = $pdo->prepare("SELECT COUNT(*) FROM sx_ygo_$table WHERE match_id IN ($marks)");
                $q->execute($ids);
                if ((int) $q->fetchColumn() > 0) {
                    throw new InvalidArgumentException(
                        'La fase siguiente ya tiene resultados, turnos, asistencia o datos capturados; se conserva completa.',
                    );
                }
            }
            foreach ($s['history'] as $m) {
                $r = ygo_workflow_db_result($pdo, (int) $m['match_id']);
                if (
                    $r['winner'] !== $m['winner'] ||
                    $r['game_difference'] !== $m['game_difference']
                ) {
                    throw new InvalidArgumentException(
                        'El historial ya fue modificado fuera de este flujo.',
                    );
                }
            }
            foreach ($plan['restore_ids'] as $mid) {
                ygo_admission_assert_result_editable($pdo, $mid);
            }
            $pdo->prepare(
                "DELETE FROM sx_ygo_matches WHERE competition_id=? AND id IN ($marks)",
            )->execute([$competitionId, ...$ids]);
            ygo_workflow_store($pdo, $c, $plan['state']);
            $pdo->prepare(
                'UPDATE sx_ygo_competitions SET ends_on=(SELECT MAX(m.scheduled_on) FROM sx_ygo_matches m WHERE m.competition_id=?) WHERE id=?',
            )->execute([$competitionId, $competitionId]);
            $pdo->prepare(
                'UPDATE sx_ygo_events e SET active=0 WHERE e.competition_id=? AND NOT EXISTS(SELECT 1 FROM sx_ygo_matches m WHERE m.competition_id=e.competition_id AND m.scheduled_on=e.event_date)',
            )->execute([$competitionId]);
            $pdo->prepare(
                'INSERT INTO sx_ygo_audit_log(entity_type,entity_id,action,payload) VALUES(?,?,?,?)',
            )->execute([
                'competition',
                $competitionId,
                'workflow_revert',
                json_encode(
                    [
                        'reason' => $reason,
                        'removed_round' => $expectedRound,
                        'removed_matches' => $s['pending'],
                        'restored_round' => $plan['state']['round'],
                        'editable_matches' => $plan['restore_ids'],
                        'previous_state_sha256' => hash(
                            'sha256',
                            json_encode($s, JSON_THROW_ON_ERROR),
                        ),
                    ],
                    JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                ),
            ]);
            if ($owns) {
                $pdo->commit();
            }
            return count($ids);
        } catch (Throwable $e) {
            if ($owns && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    });
}
