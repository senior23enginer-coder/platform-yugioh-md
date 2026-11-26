<?php
declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/src/autoload.php';
require_once __DIR__ . '/event_catalog_rules.php';
require_once __DIR__ . '/participant_identity.php';
require_once __DIR__ . '/completion_times.php';

function ygo_team_kind(array $c): string
{
    $code = ygo_template_base_code((string) ($c['prompt_template'] ?? ($c['code'] ?? '')));
    if ($code === 'tag_duel') {
        return 'tag';
    }
    if ($code === 'all_star_event' && (int) ($c['participants_per_side'] ?? 1) === 1) {
        return '';
    }
    return in_array(
        $code,
        [
            'team_ycs',
            'ots_tournament_series',
            'team_3v3',
            'team_league',
            'team_cup',
            'team_championship',
            'all_star_event',
        ],
        true,
    )
        ? 'team'
        : '';
}

function ygo_team_decode_payload(string $json): array
{
    return \StationX\Yugioh\Domain\Competitions\TeamConfigurationPolicy::decodePayload($json);
}

function ygo_team_load(PDO $p, array $c): ?array
{
    return \StationX\Yugioh\Infrastructure\Composition\TeamEventModule::configuration($p)->load($c);
}

function ygo_team_store(PDO $p, array $c, array $s): void
{
    \StationX\Yugioh\Infrastructure\Composition\TeamEventModule::configuration($p)->store($c, $s);
}

function ygo_team_lineups(array $rows, string $kind): array
{
    return \StationX\Yugioh\Domain\Competitions\TeamLineupPolicy::lineups($rows, $kind);
}

function ygo_team_all_ids(array $s): array
{
    return \StationX\Yugioh\Domain\Competitions\TeamLineupPolicy::allIds($s);
}

function ygo_team_context(PDO $p, array $c): array
{
    return \StationX\Yugioh\Infrastructure\Composition\TeamEventModule::configuration($p)->context($c);
}

function ygo_team_save(PDO $p, int $id, array $rows): array
{
    return ygo_with_event_write_lock($p, static fn(): array => \StationX\Yugioh\Infrastructure\Composition\TeamEventModule::configuration($p)->save($id, $rows));
}

function ygo_team_require(PDO $p, array $c, array $ids): array
{
    return \StationX\Yugioh\Infrastructure\Composition\TeamEventModule::configuration($p)->require($c, $ids);
}

function ygo_team_lineup(array $s, int $captain): array
{
    return \StationX\Yugioh\Domain\Competitions\TeamLineupPolicy::lineup($s, $captain);
}

function ygo_team_bind_match(PDO $p, array $c, int $mid, int $a, int $b, array $s): void
{
    \StationX\Yugioh\Infrastructure\Composition\TeamEventModule::configuration($p)->bind($c, $mid, $a, $b, $s);
}

/** Three complete individual matches, or one genuinely shared Tag series. */
function ygo_team_aggregate(string $kind, array $evaluations, bool $drawAllowed): array
{
    return \StationX\Yugioh\Domain\Competitions\TeamLineupPolicy::aggregate(
        $kind,
        $evaluations,
        $drawAllowed,
    );
}

function ygo_team_result_load(PDO $p, int $season, int $mid): ?array
{
    $q = $p->prepare(
        'SELECT setting_value FROM sx_ygo_settings WHERE season_id=? AND setting_key=?',
    );
    $q->execute([$season, 'event_team_result_' . $mid]);
    $v = $q->fetchColumn();
    return $v === false ? null : json_decode((string) $v, true, 64, JSON_THROW_ON_ERROR);
}

/** Collective rows never count as a captain's individual result.
 * Actual 3v3 seat results contribute once per member; Tag has no individual winner.
 */
function ygo_team_expand_individual_rows(PDO $p, array $rows): array
{
    $teamIds = [];
    $records = [];
    foreach (
        $p
            ->query(
                "SELECT m.id FROM sx_ygo_matches m JOIN sx_ygo_settings s ON s.setting_key=CONCAT('event_teams_',m.competition_id) JOIN sx_ygo_competitions c ON c.id=m.competition_id AND c.season_id=s.season_id",
            )
            ->fetchAll(PDO::FETCH_COLUMN)
        as $id
    ) {
        $teamIds[(int) $id] = true;
    }
    if (!$teamIds) {
        return $rows;
    }
    foreach (
        $p
            ->query(
                "SELECT setting_key,setting_value FROM sx_ygo_settings WHERE setting_key LIKE 'event_team_result_%'",
            )
            ->fetchAll(PDO::FETCH_ASSOC)
        as $r
    ) {
        $records[(int) substr($r['setting_key'], 18)] = json_decode($r['setting_value'], true);
    }
    $decks = [];
    foreach (
        $p
            ->query(
                'SELECT d.id,d.name,d.archetype,d.variations,d.team_id,t.name team_name FROM sx_ygo_decks d LEFT JOIN sx_ygo_teams t ON t.id=d.team_id',
            )
            ->fetchAll(PDO::FETCH_ASSOC)
        as $d
    ) {
        $decks[(int) $d['id']] = $d;
    }
    $out = [];
    foreach ($rows as $row) {
        $mid = (int) ($row['match_id'] ?? ($row['id'] ?? 0));
        if (!isset($teamIds[$mid])) {
            $out[] = $row;
            continue;
        }
        $r = $records[$mid] ?? null;
        if (!$r || $r['kind'] !== 'team') {
            continue;
        }
        foreach ($r['evaluations'] as $seat => $evaluation) {
            $v = $row;
            $a = $r['lineup_a']['decks'][$seat];
            $b = $r['lineup_b']['decks'][$seat];
            $v['deck_a_id'] = $a;
            $v['deck_b_id'] = $b;
            $v['result'] = $evaluation['winner'];
            $v['score'] = $evaluation['score'];
            $v['duel_details'] = '[]';
            foreach (['c', 'd', 'e', 'f'] as $key) {
                $v['deck_' . $key . '_id'] = null;
            }
            foreach (['a' => $a, 'b' => $b] as $side => $id) {
                $v['deck_' . $side] = $decks[$id]['name'];
                $v['deck_' . $side . '_name'] = $decks[$id]['name'];
                $v['archetype_' . $side] = $decks[$id]['archetype'];
                $v['variant_' . $side] = $decks[$id]['variations'];
                $v['team_' . $side . '_id'] = $decks[$id]['team_id'];
                $v['team_' . $side . '_name'] = $decks[$id]['team_name'];
            }
            foreach (range(1, 5) as $n) {
                $v['d' . $n] = $r['sequences'][$seat][$n - 1] ?? null;
            }
            $v['team_seat'] = $seat + 1;
            $out[] = $v;
        }
    }
    return $out;
}

function ygo_team_save_result(PDO $p, int $mid, array $sequences, int $revision): array
{
    return ygo_with_event_write_lock($p, static function () use (
        $p,
        $mid,
        $sequences,
        $revision,
    ): array {
        $owns = !$p->inTransaction();
        if ($owns) {
            $p->beginTransaction();
        }
        try {
            $c = ygo_competition_for_match($p, $mid);
            if (!$c || ygo_team_kind($c) === '') {
                throw new InvalidArgumentException('El combate no es de equipos.');
            }
            $q = $p->prepare('SELECT * FROM sx_ygo_competitions WHERE id=? FOR UPDATE');
            $q->execute([$c['id']]);
            $c = $q->fetch(PDO::FETCH_ASSOC);
            ygo_season_require_open($p, (int) $c['season_id'], true, true);
            if (
                in_array(
                    ygo_normalize_status_key($c['status']),
                    ['completed', 'closed', 'cancelled', 'archived'],
                    true,
                )
            ) {
                throw new InvalidArgumentException('El evento esta cerrado.');
            }
            ygo_admission_assert_result_editable($p, $mid);
            ygo_workflow_assert_result_editable($p, $mid);
            $date = ygo_validate_match_result_date($p, $mid);
            if (!$date['ok']) {
                throw new InvalidArgumentException(
                    'El combate no puede registrarse antes de su fecha programada.',
                );
            }
            $s = ygo_team_load($p, $c);
            $wf = ygo_workflow_load($p, $c);
            $m = null;
            if ($s !== ($wf['teams'] ?? null)) {
                throw new InvalidArgumentException(
                    'Las alineaciones no coinciden con el reglamento fijado.',
                );
            }
            foreach ($wf['pending'] ?? [] as $candidate) {
                if ($candidate['match_id'] === $mid) {
                    $m = $candidate;
                }
            }
            if (!$s || !$s['frozen'] || !$m) {
                throw new InvalidArgumentException(
                    'Solo se registran resultados de la fase actual y su alineacion fijada.',
                );
            }
            $q = $p->prepare('SELECT * FROM sx_ygo_matches WHERE id=? FOR UPDATE');
            $q->execute([$mid]);
            $db = $q->fetch(PDO::FETCH_ASSOC);
            $left = ygo_team_lineup($s, $m['a']);
            $right = ygo_team_lineup($s, $m['b']);
            foreach (['a', 'c', 'e'] as $i => $slot) {
                if ((int) ($db['deck_' . $slot . '_id'] ?? 0) !== (int) ($left['decks'][$i] ?? 0)) {
                    throw new InvalidArgumentException('Cambio la alineacion del lado A.');
                }
            }
            foreach (['b', 'd', 'f'] as $i => $slot) {
                if (
                    (int) ($db['deck_' . $slot . '_id'] ?? 0) !== (int) ($right['decks'][$i] ?? 0)
                ) {
                    throw new InvalidArgumentException('Cambio la alineacion del lado B.');
                }
            }
            $old = ygo_team_result_load($p, (int) $c['season_id'], $mid);
            if (($old['revision'] ?? 0) !== $revision) {
                throw new InvalidArgumentException(
                    'Otro registro actualizo este resultado. Recarga antes de guardar.',
                );
            }
            if (!array_is_list($sequences)) {
                throw new InvalidArgumentException('Los puestos deben estar ordenados sin saltos.');
            }
            $evaluations = [];
            foreach ($sequences as $sequence) {
                if (!is_array($sequence) || !array_is_list($sequence)) {
                    throw new InvalidArgumentException('Secuencia invalida.');
                }
                $evaluations[] = ygo_evaluate_duel_with_rule($p, $mid, $sequence);
            }
            $aggregate = ygo_team_aggregate(
                $s['kind'],
                $evaluations,
                in_array($m['phase'], ['swiss', 'groups', 'league'], true),
            );
            $record = [
                'version' => 'STATION_X_TEAMS_V1',
                'revision' => $revision + 1,
                'kind' => $s['kind'],
                'lineup_a' => $left,
                'lineup_b' => $right,
                'sequences' => $sequences,
                'evaluations' => $evaluations,
                'aggregate' => $aggregate,
                'identities' => $s['identities'] ?? [],
            ];
            $completionNow = ygo_completion_now();
            foreach ($sequences as $seat => $sequence) {
                $record['game_details'][$seat] = ygo_completion_games(
                    [],
                    $old['game_details'][$seat] ?? [],
                    $old['sequences'][$seat] ?? [],
                    $sequence,
                    $completionNow,
                );
            }
            $record['completed_at'] =
                $old['completed_at'] ?? ($db['status'] !== 'FINALIZADA' ? $completionNow : null);
            $p->prepare(
                'INSERT INTO sx_ygo_settings(season_id,setting_key,setting_value) VALUES(?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',
            )->execute([
                $c['season_id'],
                'event_team_result_' . $mid,
                json_encode($record, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]);
            // Individual seat games never masquerade as D1-D3 of the collective match.
            $duels =
                $s['kind'] === 'tag' ? array_pad($sequences[0], 5, null) : array_fill(0, 5, null);
            $p->prepare(
                "INSERT INTO sx_ygo_match_results(match_id,d1,d2,d3,d4,d5,result,score,validation_status,notes) VALUES(?,?,?,?,?,?,?,?,'OK',?) ON DUPLICATE KEY UPDATE d1=VALUES(d1),d2=VALUES(d2),d3=VALUES(d3),d4=VALUES(d4),d5=VALUES(d5),result=VALUES(result),score=VALUES(score),validation_status='OK',notes=VALUES(notes)",
            )->execute([
                $mid,
                ...$duels,
                $aggregate['winner'],
                $aggregate['score'],
                'Resultado colectivo STATION_X_TEAMS_V1; detalle por puesto en la ficha del evento.',
            ]);
            ygo_completion_set_status($p, $mid, 'FINALIZADA', $completionNow);
            ygo_persist_competition_standings(
                $p,
                (int) $c['season_id'],
                (int) $c['id'],
                'save_team_result',
            );
            $p->prepare(
                'INSERT INTO sx_ygo_audit_log(entity_type,entity_id,action,payload) VALUES(?,?,?,?)',
            )->execute([
                'match',
                $mid,
                'save_team_result',
                json_encode($record, JSON_UNESCAPED_UNICODE),
            ]);
            ygo_sync_meta_history($p);
            ygo_sync_elo_history($p);
            ygo_sync_global_ranking_history($p);
            ygo_sync_medal_unlocks($p, $mid);
            if ($owns) {
                $p->commit();
            }
            return $record;
        } catch (Throwable $e) {
            if ($owns && $p->inTransaction()) {
                $p->rollBack();
            }
            throw $e;
        }
    });
}
