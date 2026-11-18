<?php
declare(strict_types=1);

/**
 * database-read-model: compatibility services extracted from the web entry point.
 * Preserve public functions, transaction scopes and serialized write locks.
 * See context/source-architecture.md for the current migration boundaries.
 */

function ygo_load_data(PDO $pdo, array $fallbackDivisions, array $fallbackFixtures, array $fallbackDecks): array
{
    $seasonId = ygo_current_season_id($pdo);
    $divisionRows = $pdo->prepare('SELECT * FROM sx_ygo_divisions WHERE season_id=? AND active=1 ORDER BY tier_order,id');
    $divisionRows->execute([$seasonId]);
    $dbDivisions = $divisionRows->fetchAll(PDO::FETCH_ASSOC);

    $divisions = [];
    foreach ($dbDivisions as $division) {
        $code = (string) $division['code'];
        $divisions[$code] = [
            'id' => (int) $division['id'],
            'name' => (string) $division['name'],
            'short' => $code,
            'cup' => (string) $division['cup_name'],
            'deck_count' => (int) ($division['deck_count'] ?? 0),
            'tier_order' => (int) ($division['tier_order'] ?? 0),
            'accent' => (string) ($division['accent'] ?: ($fallbackDivisions[$code]['accent'] ?? '#36d8ff')),
            'description' => (string) ($division['description'] ?: ($fallbackDivisions[$code]['description'] ?? 'Evento editable.')),
            'starts_on' => (string) ($division['starts_on'] ?? ''),
            'ends_on' => (string) ($division['ends_on'] ?? ''),
            'promotion_slots' => (int) ($division['promotion_slots'] ?? 2),
            'relegation_slots' => (int) ($division['relegation_slots'] ?? 2),
            'promotion_to_division_id' => $division['promotion_to_division_id'] !== null ? (int) $division['promotion_to_division_id'] : null,
            'relegation_to_division_id' => $division['relegation_to_division_id'] !== null ? (int) $division['relegation_to_division_id'] : null,
            'league_competition_id' => $division['league_competition_id'] !== null ? (int) $division['league_competition_id'] : null,
            'source_qualifier_id' => $division['source_qualifier_id'] !== null ? (int) $division['source_qualifier_id'] : null,
            'rank_from' => $division['rank_from'] !== null ? (int) $division['rank_from'] : null,
            'rank_to' => $division['rank_to'] !== null ? (int) $division['rank_to'] : null,
            'logo_url' => (string) ($division['logo_url'] ?? ''),
            'created_at' => (string) ($division['created_at'] ?? ''),
            'active' => (int) ($division['active'] ?? 1),
            'decks' => [],
        ];
    }

    $deckRows = $pdo->query("
        SELECT d.*, dv.code AS division_code, t.name AS team_name
             , t.logo_url AS team_logo, t.short_code AS team_code, t.primary_color AS team_primary_color, t.secondary_color AS team_secondary_color, t.symbol AS team_symbol
        FROM sx_ygo_decks d
        LEFT JOIN sx_ygo_divisions dv ON dv.id = d.division_id AND dv.active = 1
        LEFT JOIN sx_ygo_teams t ON t.id = d.team_id
        WHERE d.active = 1
        ORDER BY COALESCE(dv.tier_order, 9999), d.seed_rank, d.name
    ")->fetchAll(PDO::FETCH_ASSOC);
    $allDecks = [];
    foreach ($deckRows as $deck) {
        $code = (string) ($deck['division_code'] ?? '');
        $hasDivision = $code !== '' && isset($divisions[$code]);
        $row = [
            (int) $deck['seed_rank'],
            (string) $deck['name'],
            (string) ($deck['reason'] ?: 'Ficha editable'),
            'id' => (int) $deck['id'],
            'team_id' => $deck['team_id'] !== null ? (int) $deck['team_id'] : null,
            'team' => (string) ($deck['team_name'] ?: 'Sin equipo'),
            'team_code' => (string) ($deck['team_code'] ?: ''),
            'team_logo' => ygo_media_src((string) ($deck['team_logo'] ?: '')),
            'team_primary_color' => (string) ($deck['team_primary_color'] ?: ''),
            'team_secondary_color' => (string) ($deck['team_secondary_color'] ?: ''),
            'team_symbol' => (string) ($deck['team_symbol'] ?: ''),
            'archetype' => (string) ($deck['archetype'] ?: $deck['name']),
            'style' => (string) ($deck['style'] ?: 'Editable'),
            'key_cards' => (string) ($deck['key_cards'] ?: ''),
            'variations' => (string) ($deck['variations'] ?: ''),
            'notes' => (string) ($deck['notes'] ?: ''),
            'cover_url' => ygo_media_src((string) ($deck['cover_url'] ?: '')),
            'logo_url' => ygo_media_src((string) ($deck['logo_url'] ?: '')),
        ];
        if ($hasDivision) {
            $divisions[$code]['decks'][] = $row;
        }
        $allDecks[] = [
            'id' => (int) $deck['id'],
            'rank' => (int) $deck['seed_rank'],
            'name' => (string) $deck['name'],
            'division' => $hasDivision ? $code : 'Sin division',
            'division_id' => $hasDivision ? (int) $deck['division_id'] : null,
            'team' => (string) ($deck['team_name'] ?: 'Sin equipo'),
            'team_code' => (string) ($deck['team_code'] ?: ''),
            'team_logo' => ygo_media_src((string) ($deck['team_logo'] ?: '')),
            'team_primary_color' => (string) ($deck['team_primary_color'] ?: ''),
            'team_secondary_color' => (string) ($deck['team_secondary_color'] ?: ''),
            'team_symbol' => (string) ($deck['team_symbol'] ?: ''),
            'team_id' => $deck['team_id'] !== null ? (int) $deck['team_id'] : null,
            'reason' => (string) ($deck['reason'] ?: 'Ficha editable'),
            'archetype' => (string) ($deck['archetype'] ?: $deck['name']),
            'style' => (string) ($deck['style'] ?: 'Editable'),
            'key_cards' => (string) ($deck['key_cards'] ?: ''),
            'variations' => (string) ($deck['variations'] ?: ''),
            'notes' => (string) ($deck['notes'] ?: ''),
            'cover_url' => ygo_media_src((string) ($deck['cover_url'] ?: '')),
            'logo_url' => ygo_media_src((string) ($deck['logo_url'] ?: '')),
        ];
    }

    $qualifierDeckRows = [];
    foreach ($allDecks as $deck) {
        $qualifierDeckRows[] = [
            (int) $deck['rank'],
            (string) $deck['name'],
            (string) $deck['reason'],
            'id' => (int) $deck['id'],
            'team_id' => $deck['team_id'],
            'team' => $deck['team'],
            'team_code' => $deck['team_code'],
            'team_logo' => $deck['team_logo'] ?? '',
            'team_primary_color' => $deck['team_primary_color'],
            'team_secondary_color' => $deck['team_secondary_color'],
            'team_symbol' => $deck['team_symbol'],
            'archetype' => $deck['archetype'],
            'style' => $deck['style'],
            'key_cards' => $deck['key_cards'],
            'variations' => $deck['variations'],
            'notes' => $deck['notes'],
            'cover_url' => $deck['cover_url'],
            'logo_url' => $deck['logo_url'] ?? '',
        ];
    }
    $divisions = ['CLASIFICATORIO' => [
        'id' => 0,
        'name' => 'Torneo clasificatorio inicial',
        'short' => 'CLASIFICATORIO',
        'cup' => 'Ranking de entrada a divisiones',
        'accent' => '#36d8ff',
        'description' => 'Fase previa gestionable con minimo 3 combates por deck.',
        'decks' => $qualifierDeckRows,
    ]] + $divisions;

    $fixtures = [];
    $matchRows = $pdo->query("
        SELECT m.*, CASE WHEN m.phase = 'qualifier' THEN 'CLASIFICATORIO' ELSE dv.code END AS division_code,
               c.name AS competition_name, c.type AS competition_type, c.status AS competition_status, c.event_ruleset AS competition_ruleset, c.ruleset_version AS competition_ruleset_version,
               da.name AS deck_a_name, db.name AS deck_b_name,
               ta.name AS team_a_name, tb.name AS team_b_name,
               ta.short_code AS team_a_code, tb.short_code AS team_b_code,
               ta.logo_url AS team_a_logo, tb.logo_url AS team_b_logo,
               dc.name AS deck_c_name, dd.name AS deck_d_name, de.name AS deck_e_name, df.name AS deck_f_name,
               r.d1, r.d2, r.d3, r.d4, r.d5, r.result, r.score, r.validation_status, r.notes AS result_notes,
               r.dice_roll, r.turn_choice, r.dice_winner, r.turn_chosen_by, r.turn_decision, r.starts_player, r.duel_details, r.updated_at AS result_updated_at
        FROM sx_ygo_matches m
        LEFT JOIN sx_ygo_divisions dv ON dv.id = m.division_id
        JOIN sx_ygo_decks da ON da.id = m.deck_a_id
        JOIN sx_ygo_decks db ON db.id = m.deck_b_id
        LEFT JOIN sx_ygo_teams ta ON ta.id = da.team_id
        LEFT JOIN sx_ygo_teams tb ON tb.id = db.team_id
        LEFT JOIN sx_ygo_decks dc ON dc.id = m.deck_c_id
        LEFT JOIN sx_ygo_decks dd ON dd.id = m.deck_d_id
        LEFT JOIN sx_ygo_decks de ON de.id = m.deck_e_id
        LEFT JOIN sx_ygo_decks df ON df.id = m.deck_f_id
        LEFT JOIN sx_ygo_competitions c ON c.id = m.competition_id AND c.active = 1
        LEFT JOIN sx_ygo_match_results r ON r.match_id = m.id
        WHERE m.competition_id IS NULL OR c.id IS NOT NULL
        ORDER BY dv.tier_order, m.display_order, m.round_number, m.table_number
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($matchRows as $match) {
        $phase = (string) $match['phase'];
        $baseCode = (string) $match['division_code'];
        if ($phase === 'qualifier') {
            $code = 'CLASIFICATORIO';
        } elseif ($phase === 'league') {
            $code = $baseCode !== '' ? $baseCode : 'LIGA';
        } elseif ($phase === 'cup') {
            $code = $baseCode !== '' ? 'COPA_' . $baseCode : 'COPA';
        } elseif ($phase === 'world') {
            $code = 'MUNDIAL';
        } elseif ($phase === 'friendly') {
            $code = 'AMISTOSOS';
        } else {
            $code = strtoupper($phase);
        }
        if ($code === '') {
            $code = strtoupper($phase ?: 'GENERAL');
        }
        $round = (int) $match['round_number'];
        if (!isset($fixtures[$code][$round])) {
            $fixtures[$code][$round] = ['ronda' => $round, 'descansa' => '', 'matches' => []];
        }
        $fixtures[$code][$round]['matches'][] = [
            'id' => (int) $match['id'],
            'phase' => $phase,
            'competition_id' => $match['competition_id'] !== null ? (int) $match['competition_id'] : null,
            'manual_friendly' => $phase === 'friendly' && $match['competition_id'] === null,
            'game_rule_id' => $match['game_rule_id'] !== null ? (int) $match['game_rule_id'] : null,
            'competition_name' => (string) ($match['competition_name'] ?? ''),
            'competition_type' => (string) ($match['competition_type'] ?? $phase),
            'competition_status' => (string) ($match['competition_status'] ?? ''),
            'competition_ruleset' => (string) ($match['competition_ruleset'] ?? ''),
            'competition_ruleset_version' => (string) ($match['competition_ruleset_version'] ?? ''),
            'match_format' => (string) ($match['match_format'] ?? '1v1'),
            'best_of' => (int) ($match['best_of'] ?? 5),
            'participants_per_side' => (int) ($match['participants_per_side'] ?? 1),
            'mesa' => (int) $match['table_number'],
            'bracket_round_label' => (string) ($match['bracket_round_label'] ?? ''),
            'bracket_slot' => $match['bracket_slot'] !== null ? (int) $match['bracket_slot'] : null,
            'bracket_side' => (string) ($match['bracket_side'] ?? ''),
            'display_order' => (int) ($match['display_order'] ?? 0),
            'generation_number' => (int) ($match['generation_number'] ?? 1),
            'deck_a_id' => (int) $match['deck_a_id'],
            'deck_b_id' => (int) $match['deck_b_id'],
            'deck_c_id' => $match['deck_c_id'] !== null ? (int) $match['deck_c_id'] : null,
            'deck_d_id' => $match['deck_d_id'] !== null ? (int) $match['deck_d_id'] : null,
            'deck_e_id' => $match['deck_e_id'] !== null ? (int) $match['deck_e_id'] : null,
            'deck_f_id' => $match['deck_f_id'] !== null ? (int) $match['deck_f_id'] : null,
            'a' => (string) $match['deck_a_name'],
            'b' => (string) $match['deck_b_name'],
            'team_a' => (string) ($match['team_a_name'] ?: 'Sin equipo'),
            'team_b' => (string) ($match['team_b_name'] ?: 'Sin equipo'),
            'team_a_code' => (string) ($match['team_a_code'] ?: ''),
            'team_b_code' => (string) ($match['team_b_code'] ?: ''),
            'team_a_logo' => (string) ($match['team_a_logo'] ?? ''),
            'team_b_logo' => (string) ($match['team_b_logo'] ?? ''),
            'team_a_extra' => array_values(array_filter([(string) ($match['deck_c_name'] ?? ''), (string) ($match['deck_e_name'] ?? '')])),
            'team_b_extra' => array_values(array_filter([(string) ($match['deck_d_name'] ?? ''), (string) ($match['deck_f_name'] ?? '')])),
            'estado' => (string) $match['status'],
            'scheduled_on' => (string) ($match['scheduled_on'] ?? ''),
            'starts_at' => (string) ($match['starts_at'] ?? ''),
            'venue' => (string) ($match['venue'] ?? ''),
            'judge' => (string) ($match['judge'] ?? ''),
            'notes' => (string) ($match['notes'] ?? ''),
            'duels' => array_values(array_filter([$match['d1'], $match['d2'], $match['d3'], $match['d4'], $match['d5']], static fn($v): bool => $v !== null && $v !== '')),
            'result' => (string) ($match['result'] ?? ''),
            'score' => (string) ($match['score'] ?? ''),
            'result_notes' => (string) ($match['result_notes'] ?? ''),
            'dice_roll' => (string) ($match['dice_roll'] ?? ''),
            'turn_choice' => (string) ($match['turn_choice'] ?? ''),
            'dice_winner' => (string) ($match['dice_winner'] ?? ''),
            'turn_chosen_by' => (string) ($match['turn_chosen_by'] ?? ''),
            'turn_decision' => (string) ($match['turn_decision'] ?? ''),
            'starts_player' => (string) ($match['starts_player'] ?? ''),
            'duel_details' => (string) ($match['duel_details'] ?? ''),
            'result_updated_at' => (string) ($match['result_updated_at'] ?? ''),
            'completed_at' => $match['completed_at'] ?? null,
        ];
    }
    foreach ($divisions as $code => $division) {
        if (!isset($fixtures[$code])) {
            $fixtures[$code] = [];
        } else {
            ksort($fixtures[$code]);
            $fixtures[$code] = array_values($fixtures[$code]);
        }
    }
    $deckMatchCounts = [];
    foreach ($matchRows as $match) {
        foreach (['deck_a_id', 'deck_b_id', 'deck_c_id', 'deck_d_id', 'deck_e_id', 'deck_f_id'] as $deckColumn) {
            $matchDeckId = (int) ($match[$deckColumn] ?? 0);
            if ($matchDeckId > 0) {
                $deckMatchCounts[$matchDeckId] = ($deckMatchCounts[$matchDeckId] ?? 0) + 1;
            }
        }
    }

    $teams = $pdo->query('SELECT * FROM sx_ygo_teams WHERE active = 1 ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    $competitions = $pdo->query("
        SELECT c.*, d.code AS division_code, s.name AS season_name, s.status AS season_status, s.starts_on AS season_starts_on, s.ends_on AS season_ends_on
        FROM sx_ygo_competitions c
        JOIN sx_ygo_seasons s ON s.id=c.season_id
        LEFT JOIN sx_ygo_divisions d ON d.id = c.division_id
        WHERE c.active = 1
        ORDER BY COALESCE(c.starts_on, '2999-12-31'), c.type, c.name
    ")->fetchAll(PDO::FETCH_ASSOC);
    $leagueDivisionIdsByCompetition = [];
    foreach ($dbDivisions as $division) {
        $leagueCompetitionId = (int) ($division['league_competition_id'] ?? 0);
        if ($leagueCompetitionId > 0) {
            $leagueDivisionIdsByCompetition[$leagueCompetitionId][] = (int) $division['id'];
        }
    }
    $matchTypes = $pdo->query('SELECT * FROM sx_ygo_match_types WHERE active = 1 ORDER BY sort_order, id')->fetchAll(PDO::FETCH_ASSOC);
    $competitionDeckRows = $pdo->query('SELECT competition_id, deck_id FROM sx_ygo_competition_decks WHERE active = 1 ORDER BY competition_id, sort_order, id')->fetchAll(PDO::FETCH_ASSOC);
    $competitionDecksById = [];
    foreach ($competitionDeckRows as $row) {
        $competitionDecksById[(int) $row['competition_id']][] = (int) $row['deck_id'];
    }
    $deckTeamById = [];
    foreach ($allDecks as $deck) {
        $deckTeamById[(int) ($deck['id'] ?? 0)] = $deck['team_id'] !== null ? (int) $deck['team_id'] : null;
    }
    $competitionDependencies = ygo_competition_dependencies($pdo);
    $competitionGateById = [];
    $progressGroups = ygo_event_progress_groups($pdo);
    foreach ($competitions as &$competition) {
        $competitionId = (int) $competition['id'];
        $competition['progress'] = ygo_event_progress($progressGroups[$competitionId] ?? [], in_array((string)$competition['type'], ['cup','final'], true));
        $associatedDivisionIds = array_values(array_unique($leagueDivisionIdsByCompetition[$competitionId] ?? []));
        $primaryDivisionId = (int) ($competition['division_id'] ?? 0);
        if ($primaryDivisionId > 0 && !in_array($primaryDivisionId, $associatedDivisionIds, true)) {
            array_unshift($associatedDivisionIds, $primaryDivisionId);
        }
        $competition['division_ids'] = $associatedDivisionIds;
        $competition['division_count'] = count($associatedDivisionIds);
        $gate = ygo_competition_gate($pdo, $competitionId);
        $competition['dependencies'] = $gate['dependencies'];
        $competition['can_execute'] = $gate['can_execute'];
        $competition['blocked_reason'] = $gate['can_execute'] ? '' : $gate['message'];
        try {
            $allowedDeckIds = ygo_allowed_deck_ids_for_competition($pdo, $competitionId, (int) ($competition['division_id'] ?? 0));
            $standings = ygo_competition_standings($pdo, $competitionId);
        } catch (Throwable $error) {
            $allowedDeckIds = [];
            $standings = [];
        }
        $competition['selected_deck_ids'] = $competitionDecksById[$competitionId] ?? [];
        $visibleDeckIds = $competition['selected_deck_ids'] ?: $allowedDeckIds;
        $participantTeamIds = [];
        foreach ($visibleDeckIds as $deckId) {
            $teamId = $deckTeamById[(int) $deckId] ?? null;
            if ($teamId !== null && $teamId > 0) {
                $participantTeamIds[$teamId] = true;
            }
        }
        $competition['deck_pool_count'] = count($allowedDeckIds);
        $competition['participant_deck_count'] = count($visibleDeckIds);
        $competition['participant_team_count'] = count($participantTeamIds);
        $competition['ranking'] = $standings;
        $competition['ranking_ready'] = count($standings) > 0;
        $competition['top_cut'] = array_slice($standings, 0, (string) ($competition['type'] ?? '') === 'world' ? 24 : 16);
        $competitionGateById[$competitionId] = $gate;
    }
    unset($competition);
    foreach ($fixtures as &$rounds) {
        foreach ($rounds as &$round) {
            foreach ($round['matches'] as &$match) {
                $competitionId = (int) ($match['competition_id'] ?? 0);
                $gate = $competitionId > 0 ? ($competitionGateById[$competitionId] ?? ygo_competition_gate($pdo, $competitionId)) : ['can_execute' => true, 'message' => 'Sin competencia asociada.'];
                $match['competition_can_execute'] = (bool) ($gate['can_execute'] ?? true);
                $match['competition_blocked_reason'] = (string) (($gate['message'] ?? '') ?: '');
            }
            unset($match);
        }
        unset($round);
    }
    unset($rounds);
    $cards = $pdo->query('SELECT * FROM sx_ygo_deck_cards ORDER BY deck_id, section, id')->fetchAll(PDO::FETCH_ASSOC);
    $media = $pdo->query('SELECT * FROM sx_ygo_deck_media ORDER BY deck_id, id')->fetchAll(PDO::FETCH_ASSOC);
    $logoHistory = $pdo->query('SELECT * FROM sx_ygo_logo_history ORDER BY created_at DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC);
    $honors = $pdo->query("
        SELECT h.*, c.name AS competition_name, dc.name AS champion_name, dr.name AS runner_name,
               tc.name AS champion_team_name, tr.name AS runner_team_name
        FROM sx_ygo_honors h
        LEFT JOIN sx_ygo_competitions c ON c.id = h.competition_id
        LEFT JOIN sx_ygo_decks dc ON dc.id = h.champion_deck_id
        LEFT JOIN sx_ygo_decks dr ON dr.id = h.runner_up_deck_id
        LEFT JOIN sx_ygo_teams tc ON tc.id = h.champion_team_id
        LEFT JOIN sx_ygo_teams tr ON tr.id = h.runner_up_team_id
        ORDER BY h.created_at DESC, h.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    $audit = $pdo->query('SELECT * FROM sx_ygo_audit_log ORDER BY created_at DESC, id DESC LIMIT 80')->fetchAll(PDO::FETCH_ASSOC);
    $events = $pdo->query("
        SELECT e.*, c.name AS competition_name, c.type AS competition_type, c.status AS competition_status, d.code AS division_code
        FROM sx_ygo_events e
        LEFT JOIN sx_ygo_competitions c ON c.id = e.competition_id
        LEFT JOIN sx_ygo_divisions d ON d.id = e.division_id
        WHERE e.active = 1
        ORDER BY e.event_date, e.starts_at, e.id
    ")->fetchAll(PDO::FETCH_ASSOC);
    $rulesets = $pdo->query('SELECT * FROM sx_ygo_rulesets WHERE active = 1 ORDER BY format_family, label')->fetchAll(PDO::FETCH_ASSOC);
    $rulesetVersions = $pdo->query('SELECT * FROM sx_ygo_ruleset_versions WHERE active = 1 ORDER BY sort_order, COALESCE(effective_on, "9999-12-31") DESC, version_label DESC')->fetchAll(PDO::FETCH_ASSOC);
    $gameRules = $pdo->query('SELECT * FROM sx_ygo_game_rules ORDER BY active DESC, priority_order, name')->fetchAll(PDO::FETCH_ASSOC);
    $gameRuleConditions = $pdo->query('SELECT * FROM sx_ygo_game_rule_conditions WHERE active = 1 ORDER BY game_rule_id, sort_order, id')->fetchAll(PDO::FETCH_ASSOC);
    $conditionsByRule = [];
    foreach ($gameRuleConditions as $condition) {
        $conditionsByRule[(int) ($condition['game_rule_id'] ?? 0)][] = $condition;
    }
    foreach ($gameRules as &$gameRule) {
        $gameRule['conditions'] = $conditionsByRule[(int) ($gameRule['id'] ?? 0)] ?? [];
        $gameRule['condition_count'] = count(array_filter($gameRule['conditions'], static fn($condition) => !empty($condition['active'])));
    }
    unset($gameRule);
    $statusHistory = ygo_table_exists($pdo, 'sx_ygo_competition_status_history')
        ? $pdo->query('SELECT * FROM sx_ygo_competition_status_history ORDER BY created_at DESC, id DESC LIMIT 120')->fetchAll(PDO::FETCH_ASSOC)
        : [];
    ygo_backfill_missing_standing_snapshots($pdo, ygo_current_season_id($pdo));
    $standingSnapshots = ygo_table_exists($pdo, 'sx_ygo_standing_snapshots')
        ? $pdo->query('SELECT * FROM sx_ygo_standing_snapshots ORDER BY competition_id, snapshot_key, position')->fetchAll(PDO::FETCH_ASSOC)
        : [];
    $seasons = $pdo->query('SELECT s.*, (SELECT COUNT(*) FROM sx_ygo_competitions c WHERE c.season_id=s.id) event_count, (SELECT COUNT(*) FROM sx_ygo_divisions d WHERE d.season_id=s.id) division_count FROM sx_ygo_seasons s ORDER BY s.starts_on DESC,s.id DESC')->fetchAll(PDO::FETCH_ASSOC);
    $players = ygo_table_exists($pdo, 'sx_ygo_players')
        ? $pdo->query("SELECT p.*, t.name AS team_name FROM sx_ygo_players p LEFT JOIN sx_ygo_teams t ON t.id=p.team_id WHERE p.active=1 ORDER BY p.display_name")->fetchAll(PDO::FETCH_ASSOC)
        : [];
    $playerDecks = ygo_table_exists($pdo, 'sx_ygo_player_decks')
        ? $pdo->query('SELECT player_id, deck_id FROM sx_ygo_player_decks WHERE active=1')->fetchAll(PDO::FETCH_ASSOC)
        : [];
    $matchSnapshots = ygo_table_exists($pdo, 'sx_ygo_match_snapshots')
        ? $pdo->query('SELECT * FROM sx_ygo_match_snapshots ORDER BY captured_at DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC)
        : [];
    $matchupAnalyses = ygo_table_exists($pdo, 'sx_ygo_matchup_analyses')
        ? $pdo->query("SELECT a.*, d.name AS deck_name, o.name AS opponent_name FROM sx_ygo_matchup_analyses a JOIN sx_ygo_decks d ON d.id=a.deck_id JOIN sx_ygo_decks o ON o.id=a.opponent_deck_id WHERE a.active=1 ORDER BY a.updated_at DESC, a.id DESC")->fetchAll(PDO::FETCH_ASSOC)
        : [];
    $matchupAnalysisHistory = ygo_table_exists($pdo, 'sx_ygo_matchup_analysis_history')
        ? $pdo->query('SELECT * FROM sx_ygo_matchup_analysis_history ORDER BY created_at DESC, id DESC LIMIT 2000')->fetchAll(PDO::FETCH_ASSOC)
        : [];
    $deckAnalysisProfiles = ygo_table_exists($pdo, 'sx_ygo_deck_analysis_profiles')
        ? $pdo->query('SELECT * FROM sx_ygo_deck_analysis_profiles ORDER BY updated_at DESC, deck_id')->fetchAll(PDO::FETCH_ASSOC)
        : [];
    $deckAnalysisProfileHistory = ygo_table_exists($pdo, 'sx_ygo_deck_analysis_profile_history')
        ? $pdo->query('SELECT * FROM sx_ygo_deck_analysis_profile_history ORDER BY created_at DESC, id DESC LIMIT 2000')->fetchAll(PDO::FETCH_ASSOC)
        : [];
    $medalUnlocks = ygo_table_exists($pdo, 'sx_ygo_medal_unlocks')
        ? $pdo->query('SELECT * FROM sx_ygo_medal_unlocks ORDER BY unlocked_at DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC)
        : [];
    $checkins = ygo_table_exists($pdo, 'sx_ygo_checkins')
        ? $pdo->query('SELECT * FROM sx_ygo_checkins ORDER BY checked_in_at DESC, id DESC LIMIT 300')->fetchAll(PDO::FETCH_ASSOC)
        : [];
    $metaHistory = ygo_table_exists($pdo, 'sx_ygo_meta_history')
        ? $pdo->query('SELECT * FROM sx_ygo_meta_history ORDER BY snapshot_on DESC, id DESC LIMIT 2000')->fetchAll(PDO::FETCH_ASSOC)
        : [];
    $eloHistory = ygo_table_exists($pdo, 'sx_ygo_elo_history')
        ? $pdo->query('SELECT e.*,d.name AS deck_name,o.name AS opponent_name FROM sx_ygo_elo_history e LEFT JOIN sx_ygo_decks d ON d.id=e.deck_id LEFT JOIN sx_ygo_decks o ON o.id=e.opponent_deck_id ORDER BY e.played_on DESC,e.match_id DESC,e.id DESC LIMIT 4000')->fetchAll(PDO::FETCH_ASSOC)
        : [];
    $medalProgress = ygo_table_exists($pdo, 'sx_ygo_medal_progress')
        ? $pdo->query('SELECT * FROM sx_ygo_medal_progress ORDER BY entity_type,entity_id,unlocked DESC,rarity,medal_name')->fetchAll(PDO::FETCH_ASSOC)
        : [];
    $rankingHistory = ygo_table_exists($pdo, 'sx_ygo_global_ranking_history')
        ? $pdo->query('SELECT h.*,d.name AS deck_name,t.name AS team_name FROM sx_ygo_global_ranking_history h LEFT JOIN sx_ygo_decks d ON d.id=h.deck_id LEFT JOIN sx_ygo_teams t ON t.id=d.team_id ORDER BY h.snapshot_on DESC,h.season_id,h.position LIMIT 5000')->fetchAll(PDO::FETCH_ASSOC)
        : [];
    $eventSummaries = ygo_table_exists($pdo, 'sx_ygo_event_summaries')
        ? $pdo->query('SELECT s.*,c.name AS competition_name,d1.name AS champion_name,d2.name AS runner_up_name FROM sx_ygo_event_summaries s LEFT JOIN sx_ygo_competitions c ON c.id=s.competition_id LEFT JOIN sx_ygo_decks d1 ON d1.id=s.champion_deck_id LEFT JOIN sx_ygo_decks d2 ON d2.id=s.runner_up_deck_id ORDER BY s.finalized_on DESC,s.competition_id DESC')->fetchAll(PDO::FETCH_ASSOC)
        : [];

    $allDecks=ygo_deck_list_metadata($allDecks,$cards,$deckAnalysisProfiles);
    return [
        'divisions' => $divisions,
        'fixtures' => $fixtures,
        'allDecks' => $allDecks,
        'teams' => $teams,
        'competitions' => $competitions,
        'cards' => $cards,
        'media' => $media,
        'logoHistory' => $logoHistory,
        'honors' => $honors,
        'audit' => $audit,
        'events' => $events,
        'rulesets' => $rulesets,
        'rulesetVersions' => $rulesetVersions,
        'matchTypes' => $matchTypes,
        'gameRules' => $gameRules,
        'gameRuleConditions' => $gameRuleConditions,
        'competitionDependencies' => $competitionDependencies,
        'statusHistory' => $statusHistory,
        'standingSnapshots' => $standingSnapshots,
        'seasons' => $seasons,
        'seasonEvents' => ygo_season_event_rows($pdo),
        'players' => $players,
        'playerDecks' => $playerDecks,
        'matchSnapshots' => $matchSnapshots,
        'matchupAnalyses' => $matchupAnalyses,
        'matchupAnalysisHistory' => $matchupAnalysisHistory,
        'deckAnalysisProfiles' => $deckAnalysisProfiles,
        'deckAnalysisProfileHistory' => $deckAnalysisProfileHistory,
        'medalUnlocks' => $medalUnlocks,
        'checkins' => $checkins,
        'metaHistory' => $metaHistory,
        'eloHistory' => $eloHistory,
        'medalProgress' => $medalProgress,
        'rankingHistory' => $rankingHistory,
        'eventSummaries' => $eventSummaries,
        'settings' => ygo_settings($pdo),
        'uiLabels' => ygo_ui_labels($pdo),
        'serverToday' => date('Y-m-d'),
    ];
}
