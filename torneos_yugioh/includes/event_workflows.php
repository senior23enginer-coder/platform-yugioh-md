<?php
declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/src/autoload.php';
require_once __DIR__ . '/event_write_lock.php';
require_once __DIR__ . '/event_teams.php';

/** Station X rules, versioned independently of official organized-play formats. */
function ygo_workflow_models(): array
{
    return \StationX\Yugioh\Domain\Competitions\WorkflowDefinition::models();
}

function ygo_execution_parameter_fields(string $model): array
{
    return \StationX\Yugioh\Domain\Competitions\WorkflowDefinition::executionParameterFields(
        $model,
    );
}

function ygo_workflow_policy(string $model, int $participants): array
{
    return \StationX\Yugioh\Domain\Competitions\WorkflowDefinition::policy($model, $participants);
}

function ygo_workflow_begin(string $model, array $ids, array $parameters = []): array
{
    return \StationX\Yugioh\Domain\Competitions\WorkflowDefinition::begin(
        $model,
        $ids,
        $parameters,
    );
}

/** Ranking is local to the specified phase/group. BYEs never fabricate a match. */
function ygo_workflow_ranking(array $s, ?array $only = null, ?string $phase = null): array
{
    return \StationX\Yugioh\Domain\Competitions\WorkflowEngine::ranking($s, $only, $phase);
}

/** Bounded exhaustive matching; no silent repeat or partial round when impossible. */
function ygo_workflow_pair(array $ranked, array $forbidden = [], int &$budget = 100000): ?array
{
    return \StationX\Yugioh\Domain\Competitions\WorkflowEngine::pair($ranked, $forbidden, $budget);
}

function ygo_workflow_rr(array $ids): array
{
    return \StationX\Yugioh\Domain\Competitions\WorkflowEngine::rr($ids);
}

function ygo_workflow_ladder_order(array $s): array
{
    return \StationX\Yugioh\Domain\Competitions\WorkflowEngine::ladderOrder($s);
}

/** Returns one stage only. Caller persists this state and waits for all its results. */
function ygo_workflow_next(array $s): array
{
    return \StationX\Yugioh\Domain\Competitions\WorkflowEngine::next($s);
}

function ygo_workflow_accept(array $s, array $results): array
{
    return \StationX\Yugioh\Domain\Competitions\WorkflowEngine::accept($s, $results);
}

function ygo_workflow_load(PDO $pdo, array $competition, bool $lock = false): ?array
{
    $q = $pdo->prepare(
        'SELECT setting_value FROM sx_ygo_settings WHERE season_id=? AND setting_key=?' .
            ($lock ? ' FOR UPDATE' : ''),
    );
    $q->execute([(int) $competition['season_id'], 'event_workflow_' . $competition['id']]);
    $value = $q->fetchColumn();
    if ($value === false) {
        return null;
    }
    if (str_starts_with((string) $value, 'z1:')) {
        $bytes = base64_decode(substr((string) $value, 3), true);
        $value = $bytes === false ? false : gzinflate($bytes, 2097152);
        if ($value === false) {
            throw new InvalidArgumentException(
                'El estado persistido no se puede leer; se conserva sin regenerarlo.',
            );
        }
    }
    $s = json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
    if (($s['policy']['version'] ?? '') !== 'STATION_X_2026_V1') {
        throw new InvalidArgumentException(
            'Version de reglamento desconocida; se conserva el evento sin reinterpretarlo.',
        );
    }
    return $s;
}

function ygo_workflow_store(PDO $pdo, array $competition, array $state): void
{
    $payload =
        'z1:' .
        base64_encode(
            gzdeflate(json_encode($state, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 6),
        );
    if (strlen($payload) > 65535) {
        throw new InvalidArgumentException(
            'El historial supera la capacidad del reglamento; no se guardara una fase parcial.',
        );
    }
    $pdo->prepare(
        'INSERT INTO sx_ygo_settings(season_id,setting_key,setting_value) VALUES(?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',
    )->execute([(int) $competition['season_id'], 'event_workflow_' . $competition['id'], $payload]);
}

/** Every scheduled stage is atomic with its persisted policy/participants and calendar rows. */
function ygo_workflow_generate(
    PDO $pdo,
    array $competition,
    ?array $ids = null,
    ?string $startDate = null,
    string $pattern = 'consecutive',
    int $matchesPerDay = 1,
): int {
    return ygo_with_event_write_lock(
        $pdo,
        fn() => ygo_workflow_generate_serialized(
            $pdo,
            $competition,
            $ids,
            $startDate,
            $pattern,
            $matchesPerDay,
        ),
    );
}

function ygo_workflow_generate_serialized(
    PDO $pdo,
    array $competition,
    ?array $ids = null,
    ?string $startDate = null,
    string $pattern = 'consecutive',
    int $matchesPerDay = 1,
): int {
    $owns = !$pdo->inTransaction();
    if ($owns) {
        $pdo->beginTransaction();
    }
    try {
        $q = $pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id=? AND active=1 FOR UPDATE');
        $q->execute([(int) $competition['id']]);
        $competition = $q->fetch(PDO::FETCH_ASSOC);
        if (!$competition) {
            throw new InvalidArgumentException('Evento inexistente.');
        }
        ygo_season_require_open($pdo, (int) $competition['season_id']);
        $existingState = ygo_workflow_load($pdo, $competition);
        if (!empty($existingState['complete']) && $ids === null) {
            if ($owns) {
                $pdo->commit();
            }
            return 0;
        }
        if (
            in_array(
                ygo_normalize_status_key((string) $competition['status']),
                ['completed', 'closed', 'cancelled', 'archived'],
                true,
            )
        ) {
            throw new InvalidArgumentException('El evento esta cerrado.');
        }
        $competition = ygo_team_context($pdo, $competition);
        ygo_require_supported_model($competition);
        ygo_require_execution_type(
            (string) $competition['type'],
            (string) $competition['execution_model'],
        );
        if (
            (int) ($competition['participants_per_side'] ?? 1) !== 1 &&
            ygo_team_kind($competition) === ''
        ) {
            throw new InvalidArgumentException('Este motor requiere participantes individuales.');
        }
        $s = $existingState;
        $initial = $s === null;
        if ($initial) {
            ygo_assert_fixture_mutable($pdo, (int) $competition['id']);
            if ($ids === null) {
                throw new InvalidArgumentException('El evento no tiene un reglamento iniciado.');
            }
            if (
                $competition['type'] === 'league' &&
                count(ygo_league_divisions($pdo, (int) $competition['id'])) > 1
            ) {
                throw new InvalidArgumentException(
                    'Este motor por etapas requiere una liga con una division. Las ligas con varias divisiones conservan sus calendarios independientes de ida/vuelta.',
                );
            }
            $ids = ygo_admission_require($pdo, $competition, $ids);
            $teamConfig = null;
            $model = (string) $competition['execution_model'];
            if (ygo_team_kind($competition) !== '') {
                $teamConfig = ygo_team_require($pdo, $competition, $ids);
                $ids = array_column($teamConfig['lineups'], 'captain');
                $model =
                    [
                        'round_robin' => '_teams_round_robin',
                        'league_points' => '_teams_round_robin',
                        'double_round_robin' => '_teams_double_round_robin',
                        'single_elimination' => '_teams_single_elimination',
                        'bracket' => '_teams_single_elimination',
                    ][$model] ?? $model;
            }
            $parameters =
                json_decode((string) ($competition['execution_parameters'] ?? ''), true) ?: [];
            if (($competition['prompt_template'] ?? '') === 'clasificatorio_divisiones_3_duelos') {
                $parameters['swiss_rounds'] ??= min(
                    count($ids) - 1,
                    (int) ($competition['qualifier_duels_per_deck'] ?? 3),
                );
            }
            $s = ygo_workflow_begin($model, $ids, $parameters);
            if ($teamConfig !== null) {
                $s['teams'] = $teamConfig;
            }
            $impact = strtoupper((string) ($competition['ranking_impact'] ?? 'FULL'));
            $s['policy']['points'] = [
                'win' => $impact === 'BONUS' ? 4 : ($impact === 'REDUCED' ? 1 : 3),
                'draw' => $impact === 'REDUCED' ? 0 : 1,
                'loss' => 0,
                'bye' => 3,
            ];
            $s['policy']['points_source'] =
                'Escala local del evento: ' . $impact . '; BYE segun politica interna v1.';
            ygo_validate_format_roster($pdo, (string) $competition['event_ruleset'], $ids);
            $s['schedule'] = [
                'start' => $startDate ?? ($competition['starts_on'] ?? date('Y-m-d')),
                'pattern' => $pattern,
                'matches_per_day' => max(1, min(5, $matchesPerDay)),
            ];
        } else {
            if ($ids !== null) {
                throw new InvalidArgumentException(
                    'El reglamento y participantes ya estan fijados; no se regeneraran.',
                );
            }
            if (isset($s['teams']) && $s['teams'] !== ygo_team_load($pdo, $competition)) {
                throw new InvalidArgumentException(
                    'Las alineaciones fijadas fueron modificadas fuera del flujo del evento.',
                );
            }
            if (!empty($s['complete'])) {
                if ($owns) {
                    $pdo->commit();
                }
                return 0;
            }
            // A result used for a later stage cannot be silently reinterpreted after a correction.
            foreach ($s['history'] as $m) {
                $r = ygo_workflow_db_result($pdo, (int) $m['match_id']);
                if (
                    $r['winner'] !== $m['winner'] ||
                    $r['game_difference'] !== $m['game_difference']
                ) {
                    throw new InvalidArgumentException(
                        'Un resultado anterior cambio. Se requiere una correccion controlada de las fases dependientes.',
                    );
                }
            }
            $results = [];
            foreach ($s['pending'] ?? [] as $m) {
                $r = ygo_workflow_db_result($pdo, (int) $m['match_id'], false);
                if ($r === null) {
                    if ($owns) {
                        $pdo->commit();
                    }
                    return 0;
                }
                $results[] = $r;
            }
            $s = ygo_workflow_accept($s, $results);
        }
        $next = ygo_workflow_next($s);
        $s = $next['state'];
        if ($next['complete']) {
            $s['complete'] = true;
            if (
                ($competition['type'] ?? '') === 'qualifier' &&
                ($competition['advancement_rule'] ?? '') === 'SOURCE_ADMISSION'
            ) {
                $s['champion'] = null;
                $s['runner_up'] = null;
            }
            if ($s['champion'] !== null) {
                ygo_workflow_honor($pdo, $competition, (int) $s['champion'], (int) $s['runner_up']);
            }
            $pdo->prepare(
                "UPDATE sx_ygo_competitions SET status='completed',configuration_locked=1 WHERE id=?",
            )->execute([$competition['id']]);
            ygo_record_competition_status_history(
                $pdo,
                (int) $competition['season_id'],
                (int) $competition['id'],
                (string) $competition['status'],
                'completed',
                'workflow_completed',
                ['policy' => $s['policy']['version']],
            );
        } else {
            $created = [];
            $dates = [];
            $last = $pdo->prepare(
                'SELECT MAX(scheduled_on) FROM sx_ygo_matches WHERE competition_id=?',
            );
            $last->execute([$competition['id']]);
            $previous = $last->fetchColumn();
            $base = max(
                date('Y-m-d'),
                (string) $s['schedule']['start'],
                $previous
                    ? ygo_next_competition_match_date(
                        new DateTimeImmutable($previous),
                        $s['schedule']['pattern'],
                        1,
                        1,
                    )
                    : '',
            );
            foreach ($next['matches'] as $i => $m) {
                $date = ygo_next_competition_match_date(
                    new DateTimeImmutable($base),
                    $s['schedule']['pattern'],
                    $i,
                    $s['schedule']['matches_per_day'],
                );
                $sideLabel =
                    [
                        'upper' => 'Cuadro superior',
                        'lower' => 'Cuadro inferior',
                        'final' => 'Final',
                        'reset' => 'Final de reset',
                    ][$m['side']] ?? ($m['group'] !== null ? 'Grupo ' . ($m['group'] + 1) : '');
                $insert = $pdo->prepare(
                    'INSERT INTO sx_ygo_matches(division_id,competition_id,game_rule_id,phase,match_format,best_of,participants_per_side,round_number,table_number,bracket_round_label,bracket_slot,bracket_side,display_order,generation_number,deck_a_id,deck_b_id,status,scheduled_on,starts_at,venue,notes) VALUES(?,?,?,?,?,?,1,?,?,?,?,?,?,1,?,?,\'PENDIENTE\',NULL,NULL,\'Station X Tulua\',?)',
                );
                $insert->execute([
                    $competition['division_id'] ?: ygo_first_division_id($pdo),
                    $competition['id'],
                    $competition['game_rule_id'] ?: null,
                    $m['phase'],
                    '1v1',
                    (int) $competition['best_of'],
                    $m['round'],
                    $i + 1,
                    ($sideLabel !== '' ? $sideLabel . ' — ' : '') . 'Ronda ' . $m['round'],
                    $i + 1,
                    $m['side'],
                    $m['round'] * 100 + $i + 1,
                    $m['a'],
                    $m['b'],
                    'Reglamento interno ' . $s['policy']['version'],
                ]);
                $mid = (int) $pdo->lastInsertId();
                if (isset($s['teams'])) {
                    ygo_team_bind_match($pdo, $competition, $mid, $m['a'], $m['b'], $s['teams']);
                }
                [$date, $time] = ygo_workflow_slot(
                    $pdo,
                    $competition,
                    $mid,
                    $date,
                    $s['schedule']['pattern'],
                    $s['schedule']['matches_per_day'],
                );
                $pdo->prepare(
                    'UPDATE sx_ygo_matches SET scheduled_on=?,starts_at=? WHERE id=?',
                )->execute([$date, $time, $mid]);
                $m['match_id'] = $mid;
                $created[] = $m;
                $dates[] = $date;
            }
            $s['pending'] = $created;
            ygo_season_validate_competition_schedule($pdo, (int) $competition['id']);
            $pdo->prepare(
                'UPDATE sx_ygo_competitions SET configuration_locked=1,ends_on=? WHERE id=?',
            )->execute([max($dates), $competition['id']]);
            foreach (array_unique(array_column($created, 'phase')) as $phase) {
                ygo_rebuild_competition_calendar_events_from_matches(
                    $pdo,
                    (int) $competition['season_id'],
                    (int) $competition['id'],
                    $phase,
                );
            }
        }
        ygo_workflow_store($pdo, $competition, $s);
        if ($next['complete']) {
            ygo_persist_competition_standings(
                $pdo,
                (int) $competition['season_id'],
                (int) $competition['id'],
                'workflow_completed',
            );
        }
        $pdo->prepare(
            'INSERT INTO sx_ygo_audit_log(entity_type,entity_id,action,payload) VALUES(?,?,?,?)',
        )->execute([
            'competition',
            $competition['id'],
            'workflow_stage',
            json_encode(
                [
                    'policy' => $s['policy']['version'],
                    'round' => $s['round'],
                    'created' => count($next['matches']),
                    'complete' => $next['complete'],
                    'byes' => $s['byes'],
                ],
                JSON_UNESCAPED_UNICODE,
            ),
        ]);
        if ($owns) {
            $pdo->commit();
        }
        return count($next['matches']);
    } catch (Throwable $e) {
        if ($owns && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function ygo_workflow_db_result(PDO $pdo, int $matchId, bool $required = true): ?array
{
    $q = $pdo->prepare(
        'SELECT m.status,r.result,r.score FROM sx_ygo_matches m LEFT JOIN sx_ygo_match_results r ON r.match_id=m.id WHERE m.id=?',
    );
    $q->execute([$matchId]);
    $r = $q->fetch(PDO::FETCH_ASSOC);
    if (!$r || $r['status'] !== 'FINALIZADA' || !in_array($r['result'], ['A', 'B', 'E'], true)) {
        if (!$required) {
            return null;
        }
        throw new InvalidArgumentException('Falta un resultado valido de una fase ya consumida.');
    }
    $diff = 0;
    if (preg_match('/^(\d+)-(\d+)$/', (string) $r['score'], $parts)) {
        $diff = (int) $parts[1] - (int) $parts[2];
    }
    return ['winner' => $r['result'], 'game_difference' => $diff];
}

function ygo_workflow_slot(
    PDO $pdo,
    array $competition,
    int $matchId,
    string $from,
    string $pattern,
    int $eventMaxPerDay = 1,
): array {
    $season = $pdo->prepare('SELECT ends_on FROM sx_ygo_seasons WHERE id=?');
    $season->execute([$competition['season_id']]);
    $until = (string) $season->fetchColumn();
    $settings = ygo_settings($pdo);
    $start = substr((string) ($settings['allowed_start_time'] ?? '19:00'), 0, 5);
    $end = substr((string) ($settings['allowed_end_time'] ?? '23:00'), 0, 5);
    $startMinute = (int) substr($start, 0, 2) * 60 + (int) substr($start, 3, 2);
    $endMinute = (int) substr($end, 0, 2) * 60 + (int) substr($end, 3, 2);
    $candidate = new DateTimeImmutable($from);
    $interval = ['every_other_day' => 2, 'every_2_days' => 3, 'every_3_days' => 4][$pattern] ?? 1;
    for (
        $d = 0;
        $d < 1500 && $candidate->format('Y-m-d') <= $until;
        $d++, $candidate = $candidate->modify('+' . $interval . ' days')
    ) {
        if ($pattern === 'weekend' && (int) $candidate->format('N') < 6) {
            continue;
        }
        $capacity = $pdo->prepare(
            'SELECT COUNT(*) total,SUM(competition_id=?) event_total FROM sx_ygo_matches WHERE scheduled_on=? AND id<>?',
        );
        $capacity->execute([$competition['id'], $candidate->format('Y-m-d'), $matchId]);
        $counts = $capacity->fetch(PDO::FETCH_ASSOC);
        if (
            (int) $counts['total'] >= max(1, (int) ($settings['max_matches_per_day'] ?? 3)) ||
            (int) $counts['event_total'] >= $eventMaxPerDay
        ) {
            continue;
        }
        for ($minute = $startMinute; $minute < 1440 && $minute <= $endMinute; $minute += 60) {
            $date = $candidate->format('Y-m-d');
            $slot = sprintf('%02d:%02d:00', intdiv($minute, 60), $minute % 60);
            $busy = $pdo->prepare(
                'SELECT COUNT(*) FROM sx_ygo_matches WHERE scheduled_on=? AND ABS(TIME_TO_SEC(starts_at)-TIME_TO_SEC(?))<3600 AND id<>?',
            );
            $busy->execute([$date, $slot, $matchId]);
            if ((int) $busy->fetchColumn()) {
                continue;
            }
            $check = ygo_validate_match_schedule($pdo, $matchId, $date, $slot, 'Station X Tulua');
            if (!empty($check['ok'])) {
                return [$date, $slot];
            }
        }
    }
    throw new InvalidArgumentException(
        'No hay un horario compatible antes del cierre de la temporada. No se cambio el calendario existente.',
    );
}

function ygo_workflow_honor(PDO $pdo, array $c, int $winner, int $runner): void
{
    if (($teams = ygo_team_load($pdo, $c)) !== null) {
        $record = [
            'version' => 'STATION_X_TEAMS_V1',
            'champion' => ygo_team_lineup($teams, $winner),
            'runner_up' => ygo_team_lineup($teams, $runner),
        ];
        $pdo->prepare(
            'INSERT INTO sx_ygo_settings(season_id,setting_key,setting_value) VALUES(?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',
        )->execute([
            $c['season_id'],
            'event_team_honor_' . $c['id'],
            json_encode($record, JSON_UNESCAPED_UNICODE),
        ]);
        return;
    }
    $q = $pdo->prepare('SELECT COUNT(*) FROM sx_ygo_honors WHERE competition_id=?');
    $q->execute([$c['id']]);
    if ($q->fetchColumn()) {
        return;
    }
    $team = $pdo->prepare('SELECT team_id FROM sx_ygo_decks WHERE id=?');
    $team->execute([$winner]);
    $wt = (int) $team->fetchColumn();
    $team->execute([$runner]);
    $rt = (int) $team->fetchColumn();
    $pdo->prepare(
        'INSERT INTO sx_ygo_honors(season_id,competition_id,champion_deck_id,champion_team_id,runner_up_deck_id,runner_up_team_id,title,notes) VALUES(?,?,?,?,?,?,?,?)',
    )->execute([
        $c['season_id'],
        $c['id'],
        $winner,
        $wt ?: null,
        $runner,
        $rt ?: null,
        $c['name'],
        'Reglamento interno STATION_X_2026_V1; resultado global del evento.',
    ]);
}

function ygo_workflow_assert_result_editable(PDO $pdo, int $matchId): void
{
    $c = ygo_competition_for_match($pdo, $matchId);
    if (!$c) {
        return;
    }
    $lock = $pdo->prepare('SELECT id FROM sx_ygo_competitions WHERE id=? FOR UPDATE');
    $lock->execute([$c['id']]);
    $s = ygo_workflow_load($pdo, $c, true);
    if (!$s) {
        return;
    }
    if (
        !empty($s['complete']) ||
        in_array($matchId, array_column($s['history'], 'match_id'), true)
    ) {
        throw new InvalidArgumentException(
            'Este resultado ya determino otra fase o el cierre. No se puede sobrescribir sin revertir primero sus fases dependientes.',
        );
    }
}

/** Include finished results from the currently open stage without consuming it. */
function ygo_workflow_live_ranking(PDO $pdo, array $c, ?string $phase = null): array
{
    $isOverall = $phase === null;
    $s = ygo_workflow_load($pdo, $c);
    if (!$s) {
        return [];
    }
    foreach ($s['pending'] ?? [] as $m) {
        $r = ygo_workflow_db_result($pdo, (int) $m['match_id'], false);
        if ($r !== null) {
            $s['history'][] = array_merge($m, $r);
        }
    }
    $model = $s['policy']['model'];
    if (
        in_array($model, ['groups', 'groups_knockout'], true) &&
        ($phase === null || $phase === 'groups')
    ) {
        $rows = [];
        foreach ($s['groups'] as $g => $ids) {
            foreach (ygo_workflow_ranking($s, $ids, 'groups') as $i => $r) {
                $r['group'] = $g + 1;
                $r['group_position'] = $i + 1;
                $rows[] = $r;
            }
        }
        if ($phase === null && !empty($s['complete']) && $s['champion'] !== null) {
            $priority = [$s['champion'] => -2, $s['runner_up'] => -1];
            usort(
                $rows,
                static fn($a, $b) => ($priority[$a['id']] ??
                    $a['group'] * 100 + $a['group_position']) <=>
                    ($priority[$b['id']] ?? $b['group'] * 100 + $b['group_position']),
            );
        }
        return $rows;
    }
    $phase =
        $phase ??
        (['swiss' => 'swiss', 'swiss_top_cut' => 'swiss', 'season_playoffs' => 'league'][$model] ??
            null);
    $rows = ygo_workflow_ranking($s, null, $phase);
    if ($model === 'ladder') {
        $positions = array_flip(ygo_workflow_ladder_order($s));
        usort($rows, static fn($a, $b) => $positions[$a['id']] <=> $positions[$b['id']]);
    }
    if ($isOverall && !empty($s['complete']) && $s['champion'] !== null) {
        $priority = [$s['champion'] => -2, $s['runner_up'] => -1];
        $positions = array_flip(array_column($rows, 'id'));
        usort(
            $rows,
            static fn($a, $b) => ($priority[$a['id']] ?? $positions[$a['id']]) <=>
                ($priority[$b['id']] ?? $positions[$b['id']]),
        );
    }
    return $rows;
}
