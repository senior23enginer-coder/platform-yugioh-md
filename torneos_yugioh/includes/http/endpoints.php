<?php
declare(strict_types=1);

// Existing HTTP responses and entity exports; response exits remain explicit.
if ($dbStatus['connected'] && in_array($duelPlayerSide, ['A', 'B'], true)) {
    $duelPlayerMatchId = max(0, (int) ($_GET['match_id'] ?? 0));
    $duelPlayerMatch = $duelPlayerMatchId > 0 ? ygo_live_duel_match($pdo, $duelPlayerMatchId) : [];
    if (!$duelPlayerMatch) {
        http_response_code(404);
    }
    $sideKey = strtolower($duelPlayerSide);
    $opponentSide = $duelPlayerSide === 'A' ? 'B' : 'A';
    $ownTeam = (string) ($duelPlayerMatch['team_' . $sideKey] ?? ('Equipo ' . $duelPlayerSide));
    $ownDeck = (string) ($duelPlayerMatch['deck_' . $sideKey] ?? ('Deck ' . $duelPlayerSide));
    $ownLogo = ygo_media_src((string) ($duelPlayerMatch['logo_' . $sideKey] ?? ''));
    header('Content-Type: text/html; charset=utf-8');
    ?><!doctype html>
    <html lang="es" data-duel-player-side="<?php echo e($duelPlayerSide); ?>" data-duel-player-match="<?php echo $duelPlayerMatchId; ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
        <meta name="theme-color" content="#06111f">
        <title><?php echo e($ownTeam); ?> | Pantalla de duelo</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
        <link href="./assets/player-duel.css?v=20260927-surrender-calc-1" rel="stylesheet">
    </head>
    <body>
    <main class="player-duel-shell" data-player-duel-root>
        <header class="player-duel-header">
            <div class="player-duel-brand"><?php if ($ownLogo !== ''): ?><img src="<?php echo e($ownLogo); ?>" alt="Logo de <?php echo e($ownTeam); ?>"><?php else: ?><span><?php echo e($duelPlayerSide); ?></span><?php endif; ?></div>
            <div><small>Pantalla del lado <?php echo e($duelPlayerSide); ?></small><h1><?php echo e($ownTeam); ?></h1><p><?php echo e($ownDeck); ?></p></div>
            <span class="player-sync is-waiting" data-player-sync><i></i> Conectando</span>
        </header>
        <?php if (!$duelPlayerMatch): ?>
            <section class="player-message"><h2>Partida no encontrada</h2><p>Revisa el enlace proporcionado por el juez.</p></section>
        <?php else: ?>
            <section class="player-match-meta"><span><?php echo e((string) ($duelPlayerMatch['competition_name'] ?? 'Partida')); ?></span><b>R<?php echo (int) ($duelPlayerMatch['round_number'] ?? 0); ?> · Mesa <?php echo (int) ($duelPlayerMatch['table_number'] ?? 0); ?></b><strong data-player-duel-number>D1</strong></section>
            <section class="player-turn-panel" data-player-turn-panel>
                <small>Turno actual</small><h2 data-player-turn-label>Esperando al juez</h2><p data-player-turn-detail>El duelo todavía no ha comenzado.</p>
                <button type="button" class="player-primary" data-player-end-turn disabled><i class="bi bi-check2-circle"></i><span>Terminar mi turno</span></button>
                <button type="button" class="player-surrender" data-player-surrender disabled><i class="bi bi-flag-fill"></i><span>Rendirme</span></button>
            </section>
            <section class="player-scoreboard">
                <article class="is-own"><small>Mis LP</small><strong data-player-lp-own>8000</strong><span><?php echo e($ownTeam); ?></span></article>
                <b>VS</b>
                <article><small>LP rival</small><strong data-player-lp-rival>8000</strong><span><?php echo e((string) ($duelPlayerMatch['team_' . strtolower($opponentSide)] ?? ('Equipo ' . $opponentSide))); ?></span></article>
            </section>
            <section class="player-tools">
                <header><div><small>Herramienta personal</small><h2>Calculadora de LP</h2></div><strong data-player-entry>0</strong></header>
                <div class="player-keypad"><?php foreach (['7','8','9','4','5','6','1','2','3','0','00','000'] as $key): ?><button type="button" data-player-key="<?php echo e($key); ?>"><?php echo e($key); ?></button><?php endforeach; ?></div>
                <div class="player-operations">
                    <button type="button" data-player-lp-op="clear"><i class="bi bi-backspace"></i><span>Borrar</span></button>
                    <button type="button" data-player-lp-op="subtract"><i class="bi bi-dash-lg"></i><span>Restar</span></button>
                    <button type="button" data-player-lp-op="add"><i class="bi bi-plus-lg"></i><span>Sumar</span></button>
                    <button type="button" data-player-lp-op="half"><b>1/2</b><span>Mitad</span></button>
                    <button type="button" data-player-lp-op="reset"><i class="bi bi-arrow-repeat"></i><span>Inicial</span></button>
                </div>
            </section>
            <section class="player-tools player-standard-tool">
                <header><div><small>Calculo auxiliar</small><h2>Calculadora estandar</h2></div><strong data-player-standard-display>0</strong></header>
                <div class="player-standard-keypad">
                    <?php foreach (['7','8','9','divide','4','5','6','multiply','1','2','3','subtract','clear','0','equals','add'] as $key): ?>
                        <?php $label = ['divide' => '&divide;', 'multiply' => '&times;', 'subtract' => '&minus;', 'add' => '+', 'clear' => 'C', 'equals' => '='][$key] ?? $key; ?>
                        <button type="button" data-player-standard-key="<?php echo e($key); ?>"><?php echo $label; ?></button>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="player-use-standard" data-player-standard-use>Usar resultado como valor LP</button>
            </section>
            <section class="player-status-grid">
                <article><small>Tiempo</small><strong data-player-clock>Sin reloj</strong></article>
                <article><small>Turnos completados</small><strong data-player-turn-count>0</strong></article>
                <article class="player-dice-status"><small>Dados e inicio</small><strong data-player-dice>Esperando definición</strong></article>
            </section>
            <div class="player-toast" data-player-toast hidden></div>
        <?php endif; ?>
    </main>
    <?php if ($duelPlayerMatch): ?><script src="./assets/player-duel.js?v=20260927-surrender-calc-1" defer></script><?php endif; ?>
    <?php echo ygo_ui_labels_script($presentationLabels); ?>
    </body></html><?php
    exit;
}

if($dbStatus['connected']&&(string)($_GET['api']??'')==='ffa_history'){
    $q=$pdo->prepare('SELECT revision,payload,created_at FROM sx_ygo_ffa_actions WHERE match_id=? ORDER BY revision');$q->execute([(int)($_GET['ffa_match_id']??0)]);
    header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode(['actions'=>$q->fetchAll(PDO::FETCH_ASSOC)],JSON_UNESCAPED_UNICODE);exit;
}
if ($dbStatus['connected'] && (string)($_GET['api']??'')==='statistics') {
    $stats=ygo_stats_view($pdo);
    if(!empty($_GET['publication_id'])){
        $q=$pdo->prepare('SELECT id,payload,published_at FROM sx_ygo_statistics_publications WHERE id=?');$q->execute([(int)$_GET['publication_id']]);$cut=$q->fetch(PDO::FETCH_ASSOC);
        if(!$cut){http_response_code(404);header('Content-Type: application/json');echo json_encode(['status'=>'PENDIENTE','reason'=>'Corte publicado inexistente']);exit;}
        $stats=ygo_stats_public_values(json_decode($cut['payload'],true));$stats['publication_id']=$cut['id'];$stats['published_at']=$cut['published_at'];
    }
    $format=trim((string)($_GET['format']??''));
    if($format!==''){
        foreach(['global','metagame','medals','ledger'] as $collection)$stats[$collection]=array_values(array_filter($stats[$collection]??[],static fn($r)=>(string)($r['format_id']??'')===$format));
        foreach(['ranking','ledger'] as $collection)$stats['free_for_all'][$collection]=array_values(array_filter($stats['free_for_all'][$collection]??[],static fn($r)=>($r['format_id']??'')===$format));
    }
    if(!empty($_GET['entity_team_id'])){
        $team=(int)$_GET['entity_team_id'];
        foreach(['global','profiles','medals'] as $list)$stats[$list]=array_values(array_filter($stats[$list]??[],static fn($r)=>(int)($r['team_id']??0)===$team));
        $stats['ledger']=array_values(array_filter($stats['ledger']??[],static fn($r)=>(int)($r['team_a_id']??0)===$team||(int)($r['team_b_id']??0)===$team||in_array($team,array_column($r['participants']??[],'team_id'),true)));
        $stats['free_for_all']['ranking']=array_values(array_filter($stats['free_for_all']['ranking']??[],static fn($r)=>(int)$r['team_id']===$team));
        $stats['free_for_all']['ledger']=array_values(array_filter($stats['free_for_all']['ledger']??[],static fn($r)=>in_array($team,array_column($r['changes'],'team_id'),true)));
    }
    $filters=[];foreach(['event_id','season_id','division_id','phase','event_type','team_id','deck_id','player_id','judge','venue','from','to'] as $key)if(isset($_GET['stats_'.$key])&&trim((string)$_GET['stats_'.$key])!=='')$filters[$key]=trim((string)$_GET['stats_'.$key]);
    $analysis=$stats;
    if($filters){
        $analysis=ygo_stats_calculate(ygo_stats_filter($stats['confirmed_matches']??[],$filters),[],[],$stats['policy']??[],$stats['cutoff']??null);
        $selectedMatches=array_fill_keys(array_column($analysis['ledger'],'match_id'),true);
        foreach($analysis['global'] as &$r){
            $official=array_values(array_filter($stats['global']??[],static fn($g)=>$g['team_id']===$r['team_id']&&$g['format_id']===$r['format_id']))[0]??null;
            $r['rating']=$official['rating']??null;$r['rival_pre_sum']=0;
            foreach($stats['ledger']??[] as $entry){
                if(!isset($selectedMatches[$entry['match_id']])||$entry['format_id']!==$r['format_id'])continue;
                if(($entry['kind']??'')==='tag'){foreach($entry['participants'] as $participant)if($participant['team_id']===$r['team_id'])$r['rival_pre_sum']+=$participant['rival_mean'];continue;}
                if($entry['team_a_id']===$r['team_id'])$r['rival_pre_sum']+=$entry['before_b'];
                elseif($entry['team_b_id']===$r['team_id'])$r['rival_pre_sum']+=$entry['before_a'];
            }
        }unset($r);
        $stats['matchups']=$analysis['matchups'];$stats['players']=$analysis['players'];
    }
    $analytics=ygo_stats_analytics($analysis,max(1,min(100,(int)($_GET['minimum']??3))));
    $stats['analytical_filters']=$filters;

    $stats['analytics']=$analytics;
    if(in_array($_GET['export']??'',['csv','pdf'],true)){
        ini_set('precision','17');
        $rows=ygo_stats_export_rows($stats,(string)($_GET['collection']??'global'));
        if($_GET['export']==='pdf')ygo_output_simple_pdf('Estadisticas | '.$stats['version'].' | '.$stats['cutoff'],$rows,'station-x-estadisticas.pdf');
        header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="station-x-estadisticas.csv"');
        $out=fopen('php://output','w');foreach($rows as $row)fputcsv($out,$row);fclose($out);exit;
    }
    header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($stats,JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION);exit;
}
if ($dbStatus['connected'] && (string) ($_GET['api'] ?? '') === 'live') {
    $liveMatches = [];
    foreach ($fixtures as $rounds) {
        foreach ($rounds as $round) {
            foreach (($round['matches'] ?? []) as $match) {
                $liveMatches[] = [
                    'id' => (int) ($match['id'] ?? 0),
                    'competition_id' => (int) ($match['competition_id'] ?? 0),
                    'competition_name' => (string) ($match['competition_name'] ?? ''),
                    'ronda' => (string) ($match['ronda'] ?? ''),
                    'mesa' => (string) ($match['mesa'] ?? ''),
                    'a' => (string) ($match['a'] ?? ''),
                    'b' => (string) ($match['b'] ?? ''),
                    'team_a' => (string) ($match['team_a'] ?? ''),
                    'team_b' => (string) ($match['team_b'] ?? ''),
                    'venue' => (string) ($match['venue'] ?? ''),
                    'estado' => (string) ($match['estado'] ?? ''),
                    'score' => (string) ($match['score'] ?? ''),
                    'd1' => $match['d1'] ?? null, 'd2' => $match['d2'] ?? null,
                    'd3' => $match['d3'] ?? null, 'd4' => $match['d4'] ?? null, 'd5' => $match['d5'] ?? null,
                    'duel_details' => (string) ($match['duel_details'] ?? ''),
                    'scheduled_on' => (string) ($match['scheduled_on'] ?? ''),
                    'starts_at' => (string) ($match['starts_at'] ?? ''),
                    'result_updated_at' => (string) ($match['result_updated_at'] ?? ''),
                ];
            }
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
    echo json_encode([
        'server_time' => date(DATE_ATOM),
        'revision' => (int) ($pdo->query('SELECT COALESCE(MAX(id),0) FROM sx_ygo_audit_log')->fetchColumn() ?: 0),
        'matches' => $liveMatches,
        'medal_unlocks' => array_slice($appData['medalUnlocks'] ?? [], 0, 100),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($dbStatus['connected'] && isset($_GET['public_live'])) {
    $refreshSeconds = max(5, min(30, (int) ($settings['live_refresh_seconds'] ?? 15)));
    header('Content-Type: text/html; charset=utf-8');
    ?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Partidas en vivo | YUGIOH MD</title><link rel="stylesheet" href="./assets/app.css?v=20260922-intelligence-5"><style>body{margin:0;background:#050d19;color:#eef5ff;font-family:Inter,Arial,sans-serif}.public-live{max-width:1500px;margin:auto;padding:clamp(16px,3vw,42px)}.public-live header{display:flex;justify-content:space-between;align-items:center;gap:18px;border-bottom:1px solid #1b4264;padding-bottom:20px}.public-live h1{font-size:clamp(28px,5vw,56px);margin:0}.public-live header span{color:#ffd25f;font-weight:800}.public-live-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,360px),1fr));gap:16px;margin-top:24px}.public-match{border:1px solid #244663;background:#09172a;padding:20px;border-radius:8px}.public-match.is-live{border-color:#20c997;box-shadow:0 0 0 1px #20c99755}.public-match .meta{display:flex;justify-content:space-between;color:#9db0ce}.public-match .teams{display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:12px;margin:24px 0}.public-match .teams b{font-size:clamp(18px,3vw,28px)}.public-match .teams b:last-child{text-align:right}.public-match .score{font-size:32px;color:#ffd25f;font-weight:900}.public-empty{padding:50px;border:1px dashed #35506f;text-align:center;margin-top:24px;color:#aabbd5}@media(max-width:600px){.public-live header{align-items:flex-start;flex-direction:column}.public-match .teams{grid-template-columns:1fr}.public-match .teams b:last-child{text-align:left}.public-match .score{font-size:26px}}</style></head><body><main class="public-live"><header><div><span>YUGIOH MD | STATION X TULUA</span><h1>Centro público de partidas</h1><p>Mesas activas y próximas partidas. Actualización automática.</p></div><a class="btn btn-outline-info" href="./?public_season=<?php echo (int) ygo_current_season_id($pdo); ?>#estadisticas">Ver temporada</a></header><section class="public-live-grid" id="public-live-grid"></section></main><script>const grid=document.getElementById('public-live-grid');const esc=(v)=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));async function refresh(){try{const r=await fetch('./?api=live',{cache:'no-store'});const d=await r.json();const rows=(d.matches||[]).filter(m=>!['FINALIZADA','COMPLETED'].includes(String(m.estado||'').toUpperCase())).sort((a,b)=>String(a.scheduled_on+' '+a.starts_at).localeCompare(String(b.scheduled_on+' '+b.starts_at)));grid.innerHTML=rows.length?rows.map(m=>`<article class="public-match ${String(m.estado).toUpperCase().includes('CURSO')?'is-live':''}"><div class="meta"><span>${esc(m.competition_name||'Partida')}</span><b>R${esc(m.ronda||'-')} | Mesa ${esc(m.mesa||'-')}</b></div><div class="teams"><b>${esc(m.a||'Deck A')}<small>${esc(m.team_a||'')}</small></b><span class="score">${esc(m.score||'VS')}</span><b>${esc(m.b||'Deck B')}<small>${esc(m.team_b||'')}</small></b></div><div class="meta"><span>${esc(m.scheduled_on||'Sin fecha')} ${esc(String(m.starts_at||'').slice(0,5))}</span><strong>${esc(m.estado||'PENDIENTE')}</strong></div></article>`).join(''):'<div class="public-empty">No hay mesas activas o próximas en este momento.</div>'}catch(e){grid.innerHTML='<div class="public-empty">No se pudo actualizar el marcador. Se reintentará automáticamente.</div>'}}refresh();setInterval(refresh,<?php echo $refreshSeconds * 1000; ?>);</script><?php echo ygo_ui_labels_script($presentationLabels); ?></body></html><?php
    exit;
}

if ($dbStatus['connected'] && isset($_GET['export'])) {
    $export = preg_replace('/[^a-z0-9_-]/i', '', (string) $_GET['export']);
    $format = preg_replace('/[^a-z0-9_-]/i', '', (string) ($_GET['format'] ?? 'csv'));
    $rows = [['YUGIOH MD', 'Station X Tulua', date('Y-m-d H:i:s')]];
    $title = 'Reporte ' . ucfirst(str_replace('_', ' ', $export));
    if ($format === 'xls') {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="yugioh-' . $export . '.xls"');
    } elseif ($format === 'print') {
        header('Content-Type: text/html; charset=utf-8');
    } elseif ($format === 'pdf') {
        // El generador PDF define sus propios encabezados despues de construir las filas.
    } else {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="yugioh-' . $export . '.csv"');
    }

    $add = static function (array $row) use (&$rows): void {
        $rows[] = $row;
    };

    $add(['']);
    if ($export === 'entity_report') {
        $entityType = preg_replace('/[^a-z_]/', '', strtolower((string) ($_GET['entity_type'] ?? 'deck')));
        $entityId = max(0, (int) ($_GET['entity_id'] ?? 0));
        $allowedEntityTypes = ['deck', 'team', 'event', 'season'];
        if (!in_array($entityType, $allowedEntityTypes, true) || $entityId <= 0) {
            $add(['Error', 'Entidad o identificador invalido']);
        } else {
            $title = 'Informe completo ' . ucfirst($entityType) . ' #' . $entityId;
            $add(['SECCION', 'FICHA']);
            if ($entityType === 'deck') {
                $entity = array_values(array_filter($allDecks, static fn(array $row): bool => (int) ($row['id'] ?? 0) === $entityId))[0] ?? [];
                $add(['Deck', $entity['name'] ?? 'No encontrado']); $add(['Equipo', $entity['team'] ?? 'Sin equipo']); $add(['Formato', $entity['format'] ?? $entity['ruleset'] ?? 'Sin formato']); $add(['Division', $entity['division'] ?? 'Sin division']); $add(['Arquetipo', $entity['archetype'] ?? '']); $add(['Variante', $entity['variations'] ?? '']);
            } elseif ($entityType === 'team') {
                $entity = array_values(array_filter($teams, static fn(array $row): bool => (int) ($row['id'] ?? 0) === $entityId))[0] ?? [];
                $add(['Equipo', $entity['name'] ?? 'No encontrado']); $add(['Codigo', $entity['short_code'] ?? $entity['code'] ?? '']); $add(['Ciudad', $entity['city'] ?? '']);
            } elseif ($entityType === 'event') {
                $entity = array_values(array_filter($competitions, static fn(array $row): bool => (int) ($row['id'] ?? 0) === $entityId))[0] ?? [];
                $add(['Evento', $entity['name'] ?? 'No encontrado']); $add(['Tipo', $entity['type'] ?? '']); $add(['Formato', $entity['event_ruleset'] ?? '']); $add(['Estado', $entity['status'] ?? '']);
                $summary = array_values(array_filter($appData['eventSummaries'] ?? [], static fn(array $row): bool => (int) ($row['competition_id'] ?? 0) === $entityId))[0] ?? [];
                if ($summary) {
                    $add(['Campeon', $summary['champion_name'] ?? '']); $add(['Subcampeon', $summary['runner_up_name'] ?? '']); $add(['Resumen congelado', $summary['finalized_on'] ?? '']);
                    $add(['']); $add(['CLASIFICACION DEFINITIVA']); $add(['Posicion','Deck','Equipo','PTS','Partidas G/E/P','Duelos G/P']);
                    foreach ((json_decode((string)($summary['standings_json'] ?? '[]'),true) ?: []) as $standing) $add([$standing['position'] ?? '',$standing['deck_name'] ?? '',$standing['team_name'] ?? '',$standing['points'] ?? 0,($standing['wins'] ?? 0).'/'.($standing['draws'] ?? 0).'/'.($standing['losses'] ?? 0),($standing['duels_won'] ?? 0).'/'.($standing['duels_lost'] ?? 0)]);
                    $add(['']); $add(['METAGAME DEL EVENTO']); $add(['Deck','Apariciones','Cuota']);
                    foreach ((json_decode((string)($summary['metagame_json'] ?? '[]'),true) ?: []) as $metaRow) $add([$metaRow['deck_name'] ?? '',$metaRow['appearances'] ?? 0,($metaRow['share'] ?? 0).'%']);
                    $stats = json_decode((string)($summary['statistics_json'] ?? '{}'),true) ?: [];
                    $add(['']); $add(['MEJORES ESTADISTICAS']); $add(['Partidas', $stats['matches'] ?? 0]);
                    if (!empty($stats['fastest_victory'])) $add(['Victoria mas rapida',($stats['fastest_victory']['deck'] ?? '').' | '.($stats['fastest_victory']['value'] ?? 0).' s']);
                    if (!empty($stats['fewest_turns_victory'])) $add(['Victoria en menos turnos',($stats['fewest_turns_victory']['deck'] ?? '').' | '.($stats['fewest_turns_victory']['value'] ?? 0).' turnos']);
                }
            } else {
                $entity = array_values(array_filter($appData['seasons'] ?? [], static fn(array $row): bool => (int) ($row['id'] ?? 0) === $entityId))[0] ?? [];
                $add(['Temporada', $entity['name'] ?? 'No encontrada']); $add(['Inicio', $entity['starts_on'] ?? '']); $add(['Fin', $entity['ends_on'] ?? '']); $add(['Estado', $entity['status'] ?? '']);
            }
            $relatedDeckIds = [];
            if ($entityType === 'deck') $relatedDeckIds = [$entityId];
            if ($entityType === 'team') $relatedDeckIds = array_map(static fn(array $row): int => (int) $row['id'], array_filter($allDecks, static fn(array $row): bool => (int) ($row['team_id'] ?? 0) === $entityId));
            $reportStats = ['matches'=>0,'wins'=>0,'draws'=>0,'losses'=>0,'duels_won'=>0,'duels_drawn'=>0,'duels_lost'=>0];
            $reportMatchups = []; $reportSnapshotOrigins = [];
            $add(['']); $add(['SECCION', 'PARTIDAS E HISTORIAL']);
            $add(['Fecha','Evento','Deck A','Deck B','Resultado','Marcador','Duelos','Procedencia snapshot']);
            foreach ($fixtures as $rounds) foreach ($rounds as $round) foreach (($round['matches'] ?? []) as $match) {
                $competition = array_values(array_filter($competitions, static fn(array $row): bool => (int) ($row['id'] ?? 0) === (int) ($match['competition_id'] ?? 0)))[0] ?? [];
                $belongs = $entityType === 'event' ? (int)($match['competition_id'] ?? 0) === $entityId : ($entityType === 'season' ? (int)($competition['season_id'] ?? 0) === $entityId : in_array((int)($match['deck_a_id'] ?? 0),$relatedDeckIds,true) || in_array((int)($match['deck_b_id'] ?? 0),$relatedDeckIds,true));
                if (!$belongs) continue;
                $origins = array_values(array_unique(array_map(static fn(array $row): string => (string)($row['snapshot_status'] ?? $row['data_origin'] ?? 'unknown'), array_filter($appData['matchSnapshots'] ?? [], static fn(array $row): bool => (int)($row['match_id'] ?? 0) === (int)($match['id'] ?? 0)))));
                foreach ($origins as $origin) $reportSnapshotOrigins[$origin] = ($reportSnapshotOrigins[$origin] ?? 0) + 1;
                if ($relatedDeckIds && in_array((string)($match['estado'] ?? $match['status'] ?? ''), ['FINALIZADA','completed','finalizada'], true) && in_array((string)($match['result'] ?? ''), ['A','B','E'], true)) {
                    $onA = in_array((int)($match['deck_a_id'] ?? 0),$relatedDeckIds,true); $onB = in_array((int)($match['deck_b_id'] ?? 0),$relatedDeckIds,true);
                    $side = $onA ? 'A' : ($onB ? 'B' : ''); $rivalName = $onA ? (string)($match['b'] ?? '') : (string)($match['a'] ?? '');
                    if ($side !== '') {
                        $reportStats['matches']++; $resultCode=(string)($match['result'] ?? '');
                        if ($resultCode === $side) $reportStats['wins']++; elseif ($resultCode === 'E') $reportStats['draws']++; else $reportStats['losses']++;
                        if (!isset($reportMatchups[$rivalName])) $reportMatchups[$rivalName]=['wins'=>0,'draws'=>0,'losses'=>0];
                        if ($resultCode === $side) $reportMatchups[$rivalName]['wins']++; elseif ($resultCode === 'E') $reportMatchups[$rivalName]['draws']++; else $reportMatchups[$rivalName]['losses']++;
                        foreach (['d1','d2','d3','d4','d5'] as $duelKey) { $duelResult=(string)($match[$duelKey] ?? ''); if ($duelResult === '') continue; if ($duelResult === $side) $reportStats['duels_won']++; elseif ($duelResult === 'E') $reportStats['duels_drawn']++; else $reportStats['duels_lost']++; }
                    }
                }
                $add([$match['scheduled_on'] ?? '',$competition['name'] ?? $match['competition_name'] ?? '',$match['a'] ?? '',$match['b'] ?? '',$match['result'] ?? '',$match['score'] ?? '',implode('/',array_filter([$match['d1'] ?? '',$match['d2'] ?? '',$match['d3'] ?? '',$match['d4'] ?? '',$match['d5'] ?? ''])),implode(', ',$origins) ?: 'sin snapshot']);
            }
            if ($relatedDeckIds) {
                $add(['']); $add(['SECCION', 'ESTADISTICAS']);
                $add(['Partidas G/E/P',$reportStats['wins'].'/'.$reportStats['draws'].'/'.$reportStats['losses']]); $add(['Duelos G/E/P',$reportStats['duels_won'].'/'.$reportStats['duels_drawn'].'/'.$reportStats['duels_lost']]);
                $add(['Winrate partidas',$reportStats['matches'] ? round($reportStats['wins']/$reportStats['matches']*100,2).'%' : 'N/D']); $add(['Cobertura snapshots',implode(', ',array_map(static fn($origin,$count): string => $origin.': '.$count,array_keys($reportSnapshotOrigins),array_values($reportSnapshotOrigins))) ?: 'Sin snapshots']);
                $add(['']); $add(['SECCION', 'MATCHUPS']); $add(['Rival','Partidas G/E/P']);
                uasort($reportMatchups,static fn(array $a,array $b): int => array_sum($b) <=> array_sum($a)); foreach ($reportMatchups as $rival=>$record) $add([$rival,$record['wins'].'/'.$record['draws'].'/'.$record['losses']]);
                $add(['']); $add(['SECCION', 'MEDALLAS']); $add(['Deck ID','Medalla','Rareza','Fecha','Evento','Rival']);
                foreach (($appData['medalUnlocks'] ?? []) as $medal) if ($medal['entity_type'] === 'deck' && in_array((int)$medal['entity_id'],$relatedDeckIds,true)) $add([$medal['entity_id'],$medal['medal_name'],$medal['rarity'],$medal['unlocked_at'],$medal['competition_id'],$medal['rival_name']]);
                $add(['']); $add(['SECCION', 'CARTAS Y MULTIMEDIA']); $add(['Deck ID','Tipo','Nombre / URL','Seccion','Cantidad']);
                foreach ($cards as $card) if (in_array((int)$card['deck_id'],$relatedDeckIds,true)) $add([$card['deck_id'],'Carta',$card['card_name'],$card['section'],$card['quantity']]);
                foreach ($media as $item) if (in_array((int)$item['deck_id'],$relatedDeckIds,true)) $add([$item['deck_id'],'Media',$item['url'],$item['media_type'],'']);
                if (!array_filter($cards, static fn(array $card): bool => in_array((int)$card['deck_id'],$relatedDeckIds,true))) $add(['','Aviso','Sin lista exacta de cartas cargada; no se infiere contenido.','','']);
            }
        }
    }

    if ($format === 'pdf') {
        ygo_output_simple_pdf($title, $rows, 'yugioh-' . $export . '-' . date('Y-m-d') . '.pdf');
    }
    if ($format === 'xls' || $format === 'print') {
        echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>' . e($title) . '</title><style>
            body{margin:0;font-family:Arial,sans-serif;color:#111827;background:#eef1f6}
            .report{max-width:1180px;margin:0 auto;padding:24px}
            .hero{border-radius:12px;padding:22px 24px;color:#fff;background:linear-gradient(135deg,#07101f,#12356c 60%,#0b1220);box-shadow:0 10px 28px rgba(15,23,42,.18)}
            .hero small{display:block;color:#ffd25f;font-weight:800;text-transform:uppercase;letter-spacing:.05em}
            h1{margin:6px 0 4px;font-size:30px}
            .meta{margin:0;color:#c9d6f0}
            table{border-collapse:collapse;width:100%;margin-top:18px;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 8px 24px rgba(15,23,42,.08)}
            th,td{border:1px solid #d7dce8;padding:9px 10px;text-align:left;font-size:13px}
            th{background:#0b1830;color:#eef5ff;text-transform:uppercase;font-size:11px;letter-spacing:.04em}
            tr:nth-child(even) td{background:#f8fafc}
            .toolbar{margin:16px 0}
            .toolbar button{border:0;border-radius:8px;padding:10px 14px;color:#07101f;background:#ffd25f;font-weight:800}
            @media print{body{background:#fff}.toolbar{display:none}.report{padding:0}.hero{box-shadow:none;border-radius:0}}
        </style></head><body><main class="report">';
        echo '<section class="hero"><small>YUGIOH MD</small><h1>' . e($title) . '</h1><p class="meta">Station X Tulua | Generado ' . e(date('Y-m-d H:i:s')) . '</p></section>';
        if ($format === 'print') {
            echo '<div class="toolbar"><button onclick="window.print()">Imprimir / guardar PDF</button></div>';
        }
        echo '<table>';
        foreach ($rows as $index => $row) {
            echo '<tr>';
            foreach ($row as $cell) {
                $tag = $index <= 2 ? 'th' : 'td';
                echo '<' . $tag . '>' . e((string) $cell) . '</' . $tag . '>';
            }
            echo '</tr>';
        }
        echo '</table></main>'.ygo_ui_labels_script($presentationLabels).'</body></html>';
    } else {
        $out = fopen('php://output', 'w');
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
    }
    exit;
}
