<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;
use InvalidArgumentException;

/** Native create_match adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class CreateMatchHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $divisionId = (int) ($post['manual_division_id'] ?? ygo_first_division_id($pdo));
        $phase = preg_replace(
            '/[^a-z_]/',
            '',
            strtolower((string) ($post['manual_phase'] ?? 'league')),
        );
        if ($phase === '') {
            $phase = 'league';
        }
        $round = max(1, (int) ($post['manual_round'] ?? 1));
        $table = max(1, (int) ($post['manual_table'] ?? 1));
        $deckA = (int) ($post['manual_deck_a_id'] ?? 0);
        $deckB = (int) ($post['manual_deck_b_id'] ?? 0);
        if ($deckA > 0 && $deckB > 0 && $deckA !== $deckB) {
            $competitionId =
                ($post['manual_competition_id'] ?? '') === ''
                    ? null
                    : (int) $post['manual_competition_id'];
            if ($competitionId) {
                $teamCheck = $pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id=?');
                $teamCheck->execute([$competitionId]);
                if (
                    ygo_team_kind($manualEvent = $teamCheck->fetch(PDO::FETCH_ASSOC) ?: []) !==
                        '' ||
                    ($manualEvent['execution_model'] ?? '') === 'free_for_all'
                ) {
                    throw new InvalidArgumentException(
                        'Programa esta modalidad desde su ficha con las alineaciones fijadas.',
                    );
                }
            }
            $format = preg_replace(
                '/[^a-z0-9_]/',
                '',
                strtolower((string) ($post['manual_match_format'] ?? '1v1')),
            );
            $bestOf = max(1, min(10, (int) ($post['manual_best_of'] ?? 5)));
            $participantsPerSide = max(
                1,
                min(3, (int) ($post['manual_participants_per_side'] ?? 1)),
            );
            if (preg_match('/^([123])v\1$/', $format, $matchParts)) {
                $participantsPerSide = (int) $matchParts[1];
            }
            $deckC =
                ($post['manual_deck_c_id'] ?? '') === '' ? null : (int) $post['manual_deck_c_id'];
            $deckD =
                ($post['manual_deck_d_id'] ?? '') === '' ? null : (int) $post['manual_deck_d_id'];
            $deckE =
                ($post['manual_deck_e_id'] ?? '') === '' ? null : (int) $post['manual_deck_e_id'];
            $deckF =
                ($post['manual_deck_f_id'] ?? '') === '' ? null : (int) $post['manual_deck_f_id'];
            $manualRuleset = preg_replace(
                '/[^a-z0-9_]/',
                '',
                strtolower((string) ($post['manual_ruleset_code'] ?? '')),
            );
            $manualGameRuleId =
                $competitionId === null
                    ? ygo_valid_match_game_rule_id(
                        $pdo,
                        ygo_current_season_id($pdo),
                        (int) ($post['manual_game_rule_id'] ?? 0),
                        $phase,
                    )
                    : null;
            $deckValidation = ygo_match_deck_validation(
                $pdo,
                $competitionId,
                $divisionId,
                [$deckA, $deckB, $deckC, $deckD, $deckE, $deckF],
                $participantsPerSide,
            );
            if ($deckValidation['ok'] && $competitionId === null && $manualRuleset !== '') {
                $rulesetDeckIds = ygo_deck_ids_for_ruleset($pdo, $manualRuleset);
                $selectedDeckIds = array_values(
                    array_filter([$deckA, $deckB, $deckC, $deckD, $deckE, $deckF]),
                );
                if (!$rulesetDeckIds || array_diff($selectedDeckIds, $rulesetDeckIds)) {
                    $deckValidation = [
                        'ok' => false,
                        'message' => 'Hay decks que no estan asociados al formato seleccionado.',
                    ];
                }
            }
            if (!$deckValidation['ok']) {
                $pdo->prepare(
                    'INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)',
                )->execute([
                    'match',
                    null,
                    'blocked_create_match_by_deck_pool',
                    json_encode(
                        [
                            'competition_id' => $competitionId,
                            'division_id' => $divisionId,
                            'deck_ids' => [$deckA, $deckB, $deckC, $deckD, $deckE, $deckF],
                            'message' => $deckValidation['message'],
                        ],
                        JSON_UNESCAPED_UNICODE,
                    ),
                ]);
                return 'partido_bloqueado_pool';
            }
            $manualScheduledOn = $post['manual_scheduled_on'] ?? '' ?: null;
            $manualStartsAt = $post['manual_starts_at'] ?? '' ?: null;
            $manualVenue = trim((string) ($post['manual_venue'] ?? ''));
            $scheduleGuard = ygo_validate_match_schedule(
                $pdo,
                0,
                $manualScheduledOn,
                $manualStartsAt,
                $manualVenue,
            );
            if (!$scheduleGuard['ok']) {
                $pdo->prepare(
                    'INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)',
                )->execute([
                    'match',
                    null,
                    'blocked_create_match_by_rules',
                    json_encode($scheduleGuard, JSON_UNESCAPED_UNICODE),
                ]);
                return 'partido_bloqueado_regla';
            }
            if ($competitionId) {
                $q = $pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id=? AND active=1');
                $q->execute([$competitionId]);
                $manualEvent = $q->fetch(PDO::FETCH_ASSOC);
                if (!$manualEvent) {
                    throw new InvalidArgumentException('Evento inexistente.');
                }
                if (in_array($manualEvent['execution_model'], ygo_workflow_models(), true)) {
                    throw new InvalidArgumentException(
                        'Este sistema genera sus etapas desde la ficha; no admite cruces manuales adicionales.',
                    );
                }
                ygo_require_supported_model(ygo_team_context($pdo, $manualEvent));
            }
            if ($competitionId === null && $phase === 'friendly') {
                $nextTableStmt = $pdo->query(
                    "SELECT COALESCE(MAX(table_number), 0) + 1 FROM sx_ygo_matches WHERE competition_id IS NULL AND phase = 'friendly'",
                );
                $table = max(1, (int) $nextTableStmt->fetchColumn());
            }
            $displayOrder = ($round - 1) * 100 + $table;
            $pdo->prepare(
                "
                INSERT INTO sx_ygo_matches (division_id, competition_id, game_rule_id, phase, match_format, best_of, participants_per_side, round_number, table_number, bracket_round_label, bracket_slot, bracket_side, display_order, generation_number, deck_a_id, deck_b_id, deck_c_id, deck_d_id, deck_e_id, deck_f_id, status, scheduled_on, starts_at, venue, judge, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'main', ?, 1, ?, ?, ?, ?, ?, ?, 'PENDIENTE', ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE competition_id=VALUES(competition_id), game_rule_id=VALUES(game_rule_id), deck_a_id=VALUES(deck_a_id), deck_b_id=VALUES(deck_b_id), deck_c_id=VALUES(deck_c_id), deck_d_id=VALUES(deck_d_id), deck_e_id=VALUES(deck_e_id), deck_f_id=VALUES(deck_f_id), match_format=VALUES(match_format), best_of=VALUES(best_of), participants_per_side=VALUES(participants_per_side), bracket_round_label=VALUES(bracket_round_label), bracket_slot=VALUES(bracket_slot), bracket_side=VALUES(bracket_side), display_order=VALUES(display_order), scheduled_on=VALUES(scheduled_on), starts_at=VALUES(starts_at), venue=VALUES(venue), judge=VALUES(judge), notes=VALUES(notes)
            ",
            )->execute([
                $divisionId > 0 ? $divisionId : null,
                $competitionId,
                $manualGameRuleId,
                $phase,
                $format,
                $bestOf,
                $participantsPerSide,
                $round,
                $table,
                'Ronda ' . $round,
                $table,
                $displayOrder,
                $deckA,
                $deckB,
                $deckC,
                $deckD,
                $deckE,
                $deckF,
                $manualScheduledOn,
                $manualStartsAt,
                $manualVenue,
                trim((string) ($post['manual_judge'] ?? '')),
                trim((string) ($post['manual_notes'] ?? '')),
            ]);
            if ($competitionId) {
                ygo_rebuild_competition_calendar_events_from_matches(
                    $pdo,
                    ygo_current_season_id($pdo),
                    (int) $competitionId,
                    $phase,
                );
            }
        } else {
            return 'partido_invalido';
        }
        return 'partido_manual';

        return null;
    }
}
