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
    require __DIR__ . '/../presentation/pages/player-duel.php';
    exit;
}

if($dbStatus['connected']&&(string)($_GET['api']??'')==='ffa_history'){
    $history=\StationX\Yugioh\Infrastructure\Composition\LiveQueryModule::matches($pdo)->ffaHistory((int)($_GET['ffa_match_id']??0));
    header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($history,JSON_UNESCAPED_UNICODE);exit;
}
if ($dbStatus['connected'] && (string)($_GET['api']??'')==='statistics') {
    $stats=\StationX\Yugioh\Infrastructure\Composition\LiveQueryModule::statistics($pdo)->execute($_GET);
    if($stats===null){http_response_code(404);header('Content-Type: application/json');echo json_encode(['status'=>'PENDIENTE','reason'=>'Corte publicado inexistente']);exit;}
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
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
    echo json_encode(\StationX\Yugioh\Infrastructure\Composition\LiveQueryModule::matches($pdo)->execute($fixtures, $appData['medalUnlocks'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($dbStatus['connected'] && isset($_GET['public_live'])) {
    $refreshSeconds = max(5, min(30, (int) ($settings['live_refresh_seconds'] ?? 15)));
    header('Content-Type: text/html; charset=utf-8');
    $publicSeasonId = (int) ygo_current_season_id($pdo);
    require __DIR__ . '/../presentation/pages/public-live.php';
    exit;
}

if ($dbStatus['connected'] && isset($_GET['export'])) {
    $export = preg_replace('/[^a-z0-9_-]/i', '', (string) $_GET['export']);
    $format = preg_replace('/[^a-z0-9_-]/i', '', (string) ($_GET['format'] ?? 'csv'));
    $generatedAt = date('Y-m-d H:i:s');
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

    ['title' => $title, 'rows' => $rows] = \StationX\Yugioh\Application\Reports\EntityReportProjection::build($export, $_GET, $allDecks, $teams, $competitions, $fixtures, $cards, $media, $appData, $generatedAt);

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
