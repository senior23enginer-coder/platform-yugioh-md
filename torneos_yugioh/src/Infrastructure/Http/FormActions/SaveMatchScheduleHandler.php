<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native save_match_schedule adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class SaveMatchScheduleHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $matchId = (int) ($post['schedule_match_id'] ?? 0);
        if ($matchId > 0) {
            $matchScheduleStmt = $pdo->prepare(
                'SELECT competition_id, phase, status, scheduled_on, starts_at, venue, judge, notes FROM sx_ygo_matches WHERE id = ? LIMIT 1',
            );
            $matchScheduleStmt->execute([$matchId]);
            $matchSchedule = $matchScheduleStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            if (!$matchSchedule) {
                return 'calendario_bloqueado_regla';
            }
            if (
                in_array(
                    strtoupper((string) ($matchSchedule['status'] ?? '')),
                    ['FINALIZADA', 'FINALIZADO', 'COMPLETED'],
                    true,
                )
            ) {
                return 'calendario_bloqueado_finalizado';
            }
            $scheduledOn = $post['scheduled_on'] ?? '' ?: null;
            $startsAt = $post['starts_at'] ?? '' ?: null;
            $venue = trim((string) ($post['venue'] ?? ''));
            $scheduleGuard = ygo_validate_match_schedule(
                $pdo,
                $matchId,
                $scheduledOn,
                $startsAt,
                $venue,
            );
            if (!$scheduleGuard['ok']) {
                $pdo->prepare(
                    'INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)',
                )->execute([
                    'match',
                    $matchId,
                    'blocked_schedule_by_rules',
                    json_encode($scheduleGuard, JSON_UNESCAPED_UNICODE),
                ]);
                return 'calendario_bloqueado_regla';
            }
            $pdo->prepare(
                'UPDATE sx_ygo_matches SET scheduled_on=?, starts_at=?, venue=?, judge=?, notes=? WHERE id=?',
            )->execute([
                $scheduledOn,
                $startsAt,
                $venue,
                trim((string) ($post['judge'] ?? '')),
                trim((string) ($post['match_notes'] ?? '')),
                $matchId,
            ]);
            $pdo->prepare(
                'INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)',
            )->execute([
                'match',
                $matchId,
                'reschedule_match',
                json_encode(
                    [
                        'before' => [
                            'scheduled_on' => $matchSchedule['scheduled_on'] ?? null,
                            'starts_at' => $matchSchedule['starts_at'] ?? null,
                            'venue' => $matchSchedule['venue'] ?? '',
                            'judge' => $matchSchedule['judge'] ?? '',
                            'notes' => $matchSchedule['notes'] ?? '',
                        ],
                        'after' => [
                            'scheduled_on' => $scheduledOn,
                            'starts_at' => $startsAt,
                            'venue' => $venue,
                            'judge' => trim((string) ($post['judge'] ?? '')),
                            'notes' => trim((string) ($post['match_notes'] ?? '')),
                        ],
                    ],
                    JSON_UNESCAPED_UNICODE,
                ),
            ]);
            $matchCompetitionId = (int) ($matchSchedule['competition_id'] ?? 0);
            if ($matchCompetitionId > 0) {
                ygo_rebuild_competition_calendar_events_from_matches(
                    $pdo,
                    ygo_current_season_id($pdo),
                    $matchCompetitionId,
                    (string) ($matchSchedule['phase'] ?? 'match'),
                );
                $competitionTypeStmt = $pdo->prepare(
                    'SELECT type FROM sx_ygo_competitions WHERE id=? LIMIT 1',
                );
                $competitionTypeStmt->execute([$matchCompetitionId]);
                if ((string) $competitionTypeStmt->fetchColumn() !== 'qualifier') {
                    ygo_reflow_active_qualifiers($pdo);
                }
            }
        }
        return 'calendario_reprogramado';

        return null;
    }
}
