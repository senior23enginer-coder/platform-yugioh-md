<?php
declare(strict_types=1);

/**
 * standings: compatibility services extracted from the web entry point.
 * Preserve public functions, transaction scopes and serialized write locks.
 * See context/source-architecture.md for the current migration boundaries.
 */

function ygo_competition_standings(PDO $pdo, int $competitionId, ?string $phaseFilter = null): array
{
    $competitionStmt = $pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id = ? AND active = 1 LIMIT 1');
    $competitionStmt->execute([$competitionId]);
    $competitionConfig = $competitionStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $workflowRanking=$competitionConfig ? ygo_workflow_live_ranking($pdo,$competitionConfig,$phaseFilter) : [];
    if($workflowRanking && $phaseFilter===null)$phaseFilter=['swiss'=>'swiss','swiss_top_cut'=>'swiss','groups'=>'groups','groups_knockout'=>'groups','season_playoffs'=>'league'][(string)$competitionConfig['execution_model']]??null;
    $rankingImpact = strtoupper((string) (($competitionConfig['ranking_impact'] ?? '') ?: 'FULL'));
    $level = strtoupper((string) (($competitionConfig['level'] ?? '') ?: 'COMPETITIVE'));
    if (!$workflowRanking && ($rankingImpact === 'NONE' || in_array($level, ['CASUAL', 'BEGINNER', 'ACADEMY', 'PROMOTIONAL', 'EXHIBITION'], true))) {
        return [];
    }
    $winPoints = $rankingImpact === 'REDUCED' ? 1 : ($rankingImpact === 'BONUS' ? 4 : 3);
    $drawPoints = $rankingImpact === 'REDUCED' ? 0 : 1;
    $selectedStmt = $pdo->prepare('SELECT COUNT(*) FROM sx_ygo_competition_decks WHERE competition_id = ? AND active = 1');
    $selectedStmt->execute([$competitionId]);
    $hasSelectedDecks = (int) $selectedStmt->fetchColumn() > 0;
    if ($hasSelectedDecks) {
        $deckStmt = $pdo->prepare("
            SELECT
                d.id,
                d.name,
                d.seed_rank,
                COALESCE(t.name, 'Sin equipo') AS team,
                COALESCE(t.short_code, '') AS team_code,
                COALESCE(t.primary_color, '#36d8ff') AS team_primary_color,
                COALESCE(t.secondary_color, '#ffd25f') AS team_secondary_color
            FROM sx_ygo_competition_decks cd
            JOIN sx_ygo_decks d ON d.id = cd.deck_id
            LEFT JOIN sx_ygo_teams t ON t.id = d.team_id
            WHERE cd.competition_id = ? AND cd.active = 1 AND d.active = 1
            ORDER BY cd.sort_order, d.seed_rank, d.name
        ");
        $deckStmt->execute([$competitionId]);
        $deckRows = $deckStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $deckRows = $pdo->query("
        SELECT
            d.id,
            d.name,
            d.seed_rank,
            COALESCE(t.name, 'Sin equipo') AS team,
            COALESCE(t.short_code, '') AS team_code,
            COALESCE(t.primary_color, '#36d8ff') AS team_primary_color,
            COALESCE(t.secondary_color, '#ffd25f') AS team_secondary_color
        FROM sx_ygo_decks d
        LEFT JOIN sx_ygo_teams t ON t.id = d.team_id
        WHERE d.active = 1
        ORDER BY d.seed_rank, d.name
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
    $rows = [];
    foreach ($deckRows as $deck) {
        $rows[(int) $deck['id']] = [
            'id' => (int) $deck['id'],
            'name' => (string) $deck['name'],
            'team' => (string) $deck['team'],
            'team_code' => (string) $deck['team_code'],
            'team_primary_color' => (string) $deck['team_primary_color'],
            'team_secondary_color' => (string) $deck['team_secondary_color'],
            'seed_rank' => (int) $deck['seed_rank'],
            'pts' => 0,
            'pj' => 0,
            'pg' => 0,
            'pe' => 0,
            'pp' => 0,
            'cg' => 0,
            'cp' => 0,
            'dp' => 0,
        ];
    }
    $phaseSql = $phaseFilter !== null && $phaseFilter !== '' ? ' AND m.phase = ?' : '';
    $matchStmt = $pdo->prepare("
        SELECT m.deck_a_id, m.deck_b_id, r.result, r.score
        FROM sx_ygo_matches m
        JOIN sx_ygo_match_results r ON r.match_id = m.id
        WHERE m.competition_id = ? AND r.result IS NOT NULL AND r.result <> ''$phaseSql
    ");
    $matchStmt->execute($phaseFilter !== null && $phaseFilter !== '' ? [$competitionId, $phaseFilter] : [$competitionId]);
    foreach ($matchStmt->fetchAll(PDO::FETCH_ASSOC) as $match) {
        $aId = (int) $match['deck_a_id'];
        $bId = (int) $match['deck_b_id'];
        if (!isset($rows[$aId], $rows[$bId])) {
            continue;
        }
        [$cgA, $cgB] = array_pad(array_map('intval', explode('-', (string) ($match['score'] ?: '0-0'))), 2, 0);
        $rows[$aId]['pj']++;
        $rows[$bId]['pj']++;
        $rows[$aId]['cg'] += $cgA;
        $rows[$aId]['cp'] += $cgB;
        $rows[$bId]['cg'] += $cgB;
        $rows[$bId]['cp'] += $cgA;
        if ($match['result'] === 'A') {
            $rows[$aId]['pts'] += $winPoints;
            $rows[$aId]['pg']++;
            $rows[$bId]['pp']++;
        } elseif ($match['result'] === 'B') {
            $rows[$bId]['pts'] += $winPoints;
            $rows[$bId]['pg']++;
            $rows[$aId]['pp']++;
        } elseif ($match['result'] === 'E') {
            $rows[$aId]['pts'] += $drawPoints;
            $rows[$bId]['pts'] += $drawPoints;
            $rows[$aId]['pe']++;
            $rows[$bId]['pe']++;
        }
    }
    foreach ($rows as &$row) {
        $row['dp'] = $row['cg'] - $row['cp'];
    }
    unset($row);
    usort($rows, static fn(array $a, array $b): int => $b['pts'] <=> $a['pts'] ?: $b['dp'] <=> $a['dp'] ?: $b['cg'] <=> $a['cg'] ?: $a['seed_rank'] <=> $b['seed_rank']);
    if($workflowRanking){
        $byId=[];foreach($rows as $r)$byId[$r['id']]=$r;$rows=[];
        foreach($workflowRanking as $r)if(isset($byId[$r['id']])){$row=$byId[$r['id']];$row['pts']=$r['points'];$row['buchholz']=$r['buchholz'];$row['group']=$r['group']??null;$row['group_position']=$r['group_position']??null;$rows[]=$row;}
    }
    if($competitionConfig && ($teamConfig=ygo_team_load($pdo,$competitionConfig))!==null){$lineups=[];foreach($teamConfig['lineups'] as $lineup)$lineups[$lineup['captain']]=$lineup;$rows=array_values(array_filter($rows,static fn($r)=>isset($lineups[$r['id']])));foreach($rows as &$r){$r['name']=$lineups[$r['id']]['name'];$r['team']='Equipo '.$teamConfig['size'].'v'.$teamConfig['size'];$r['member_deck_ids']=$lineups[$r['id']]['decks'];}unset($r);}
    return $rows;
}

function ygo_audit_label(string $action): string
{
    return [
        'configure_competition' => 'Configuración del evento guardada',
        'save_competition' => 'Evento guardado',
        'save_event_logo' => 'Logo del evento guardado',
        'apply_qualifier_divisions' => 'Divisiones aplicadas desde el clasificatorio',
        'save_team_lineups' => 'Equipos y alineaciones guardados',
        'save_event_admission' => 'Fuentes y clasificados guardados',
        'workflow_stage' => 'Fase creada',
        'workflow_revert' => 'Fase anterior corregida',
        'division_movements_applied' => 'Ascensos y descensos aplicados',
        'manual_honor_confirmed' => 'Campeón confirmado',
        'create_ffa' => 'Mesa multijugador creada',
        'finalize_ffa' => 'Evento multijugador finalizado',
        'delete_competition' => 'Evento eliminado',
        'blocked_create_match_by_deck_pool' => 'Bloqueo por pool de decks',
        'blocked_default_event_seed' => 'Bloqueo al sembrar eventos base',
        'blocked_create_match_by_rules' => 'Bloqueo por reglas de calendario',
        'blocked_schedule_by_rules' => 'Bloqueo al programar',
        'blocked_result_by_date' => 'Bloqueo de resultado por fecha',
        'turn_added' => 'Turno registrado',
        'save_dependency' => 'Prerequisito guardado',
        'delete_dependency' => 'Prerequisito eliminado',
        'advance_competition_phase' => 'Avance de fase',
        'division_promotion' => 'Ascenso de division',
        'division_relegation' => 'Descenso de division',
        'apply_from_qualifier' => 'Division aplicada desde clasificatorio',
        'blocked_finalize_competition' => 'Bloqueo al finalizar evento',
        'finalize_competition' => 'Evento finalizado',
        'recalculate_standings' => 'Ranking recalculado',
        'archive_qa_codex_data' => 'QA Codex archivado',
        'save_result' => 'Resultado guardado',
    ][$action] ?? $action;
}
