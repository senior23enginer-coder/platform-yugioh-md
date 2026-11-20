<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>YUGIOH MD - Station X Tulua</title>
    <meta name="description" content="Dashboard competitivo de Yu-Gi-Oh para YUGIOH MD, copas, mundial, fixtures y tablas.">
    <link rel="canonical" href="https://stationxtulua.com/yugioh/">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link rel="manifest" href="./manifest.webmanifest">
<meta name="theme-color" content="#07101f">
<link href="./assets/deck-builder.css?v=20261028-inspector-111" rel="stylesheet">
<link href="./assets/deck-history.css?v=20261024-history-92" rel="stylesheet">
<link href="./assets/modules/statistics/workspace.css?v=20261117-global-catalog" rel="stylesheet">
<link href="./assets/modules/deck-guide/list-metadata.css?v=20261113-deck-metadata-172" rel="stylesheet">
<link href="./assets/modules/deck-guide/strategy-workspace.css?v=20261024-strategy-95" rel="stylesheet">
<link href="./assets/modules/combo-creator/step-lesson.css?v=20261113-step-lessons-173" rel="stylesheet">
<link href="./assets/modules/play-calculator/card-menu.css?v=20261116-actions-1" rel="stylesheet">
<link href="./assets/modules/play-calculator/workspace.css?v=20261120-deck-combobox-1" rel="stylesheet">
<link href="./assets/modules/card-inspector/styles.css?v=20261116-readable-3" rel="stylesheet">
<link href="./assets/modules/combo-creator/styles.css?v=20261116-deck-picker-1" rel="stylesheet">
<link href="./assets/modules/play-calculator/styles.css?v=20261116-workspace-1" rel="stylesheet">
<link href="./assets/modules/deck-guide/learning-guide.css?v=20261102-library-125" rel="stylesheet">
<link href="./assets/result-context.css?v=20261026-divisions-100" rel="stylesheet">
<link href="./assets/app.css?v=20261024-event-filters-90" rel="stylesheet">
<link href="./assets/modules/navigation/sidebar.css?v=20261117-sidebar-arrow" rel="stylesheet">
<link href="./assets/modules/navigation/global-search.css?v=20261113-global-search-166" rel="stylesheet">
<link href="./assets/modules/match-sheet/layout.css?v=20261104-match-sheet-134" rel="stylesheet">
<link href="./assets/modules/match-sheet/entity-links.css?v=20261117-entity-links" rel="stylesheet">
<link href="./assets/library.css?v=20261018-library-48" rel="stylesheet">
<link href="./assets/match-editor.css?v=20261020-match-49" rel="stylesheet">
<link rel="stylesheet" href="./assets/result-desktop.css?v=20261109-result-button-158">
<link rel="stylesheet" href="./assets/lp-workspace.css?v=20261027-lp-105">
<link rel="stylesheet" href="./assets/alert-colors.css?v=20261109-alert-colors-156">
<link rel="stylesheet" href="./assets/alert-order.css?v=20261027-alert-order-109">
<link rel="stylesheet" href="./assets/news.css?v=20261028-news-116">
</head>
<body class="<?php echo $publicSeasonId > 0 ? 'public-season-mode' : ''; ?>" data-public-season="<?php echo e((string) $publicSeasonId); ?>">
<script>
    window.DML_DATA = <?php echo json_encode($appData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>

<?php
$routeTitle = static fn(string $key, string $fallback): string => $uiText('route.' . $key, 'title', $fallback);
$routes = [
    'dashboard' => [$routeTitle('dashboard', 'Dashboard'), 'bi-grid-1x2-fill'],
    'buscar' => [$routeTitle('buscar', 'Buscar'), 'bi-search'],
    'mundos' => ['Mundos', 'bi-globe2'],
    'mundo_decks' => ['Decks del mundo', 'bi-globe2'],
    'mundos_deck_editar' => ['Asignar mundos', 'bi-globe2'],
    'calculadora_jugadas' => ['Calculadora de jugadas', 'bi-calculator'],
    'inspector_cartas' => ['Inspector de cartas', 'bi-search'],
    'creador_combos' => ['Creador de combos y estrategias', 'bi-bezier2'],
    'biblioteca' => ['Biblioteca', 'bi-book'],
    'clasificatorio' => [$routeTitle('clasificatorio', 'Clasificatorio'), 'bi-diagram-3'],
    'divisiones' => [$routeTitle('divisiones', 'Divisiones'), 'bi-trophy'],
    'temporadas' => ['Temporadas', 'bi-calendar2-range'],
    'calendario' => [$routeTitle('calendario', 'Calendario'), 'bi-calendar-event'],
    'resultados' => [$routeTitle('resultados', 'Resultados'), 'bi-list-check'],
    'tabla' => [$routeTitle('tabla', 'Tablas'), 'bi-table'],
    'deck' => [$routeTitle('deck', 'Decks'), 'bi-journal-richtext'],
    'deck_ficha' => [$routeTitle('deck_ficha', 'Ficha del deck'), 'bi-journal-richtext'],
    'equipos' => [$routeTitle('equipos', 'Equipos'), 'bi-shield-check'],
    'equipo_ficha' => [$routeTitle('equipo_ficha', 'Ficha del equipo'), 'bi-shield-check'],
    'estadisticas' => [$routeTitle('estadisticas', 'Estadisticas'), 'bi-graph-up-arrow'],
    'torneos' => [$routeTitle('torneos', 'Torneos'), 'bi-diagram-3-fill'],
    'ligas' => [$routeTitle('ligas', 'Ligas'), 'bi-calendar2-range'],
    'eventos_especiales' => [$routeTitle('eventos_especiales', 'Eventos especiales'), 'bi-stars'],
    'crear_evento' => [$routeTitle('crear_evento', 'Crear evento'), 'bi-plus-circle'],
    'copas' => [$routeTitle('copas', 'Copas'), 'bi-award'],
    'mundial' => [$routeTitle('mundial', 'Mundial'), 'bi-globe-americas'],
    'herramienta_lp' => [$routeTitle('herramienta_lp', 'Calculadora LP'), 'bi-calculator'],
    'noticias' => ['Noticias', 'bi-newspaper'],
    'reglamento' => [$routeTitle('reglamento', 'Reglamento'), 'bi-scroll'],
    'reglas' => [$routeTitle('reglas', 'Reglas'), 'bi-magic'],
    'gestion' => [$routeTitle('gestion', 'Gestion'), 'bi-sliders2-vertical'],
];
?>

<header class="app-header">
    <div class="container-fluid px-lg-4">
        <div class="topbar">
            <button class="icon-button sidebar-mobile-open" type="button" data-sidebar-open title="Abrir menu"><i class="bi bi-list"></i></button>
            <a class="brand" href="#dashboard" data-route="dashboard" aria-label="YUGIOH MD">
                <img class="brand-logo" src="./assets/img/dml-logo.svg" alt="YUGIOH MD">
                <span class="brand-copy"><strong>YUGIOH MD</strong><small>Station X Tulua</small></span>
            </a>
            <div class="searchbox">
                <i class="bi bi-search"></i>
                <input type="search" placeholder="Buscar decks, equipos o eventos" aria-label="Buscar decks, equipos o eventos" data-global-search>
                <div class="search-results" data-search-results hidden></div>
            </div>
            <div class="header-actions">
                <button class="icon-button" type="button" title="Noticias" aria-label="Abrir noticias" data-route="noticias"><i class="bi bi-newspaper"></i><span class="news-header-badge" data-news-badge hidden>0</span></button>
                <button class="icon-button" type="button" title="Notificaciones" data-alert-modal-open><i class="bi bi-bell"></i><span data-alert-badge="total">0</span></button>
            </div>
        </div>
    </div>
</header>

<div class="app-shell">
    <aside class="side-nav" aria-label="Menu principal">
        <div class="side-card side-header">
            <img class="side-logo" src="./assets/img/dml-logo.svg" alt="YUGIOH MD">
            <div class="side-heading">
                <strong>YUGIOH MD</strong>
                <small><?php echo e($dashboardSeasonName); ?></small>
            </div>
            <button class="side-tool side-collapse" type="button" data-sidebar-toggle title="Contraer menu" aria-label="Contraer menu" aria-expanded="true"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
        </div>
        <nav class="side-menu" aria-label="Pantallas principales">
            <details class="side-group">
                <summary><i class="bi bi-house-door"></i><span>Inicio</span></summary>
                <button class="nav-pill side-pill" type="button" data-route="dashboard"><i class="bi <?php echo e($routes['dashboard'][1]); ?>"></i> <span><?php echo e($routes['dashboard'][0]); ?></span></button>
                <button class="nav-pill side-pill" type="button" data-route="buscar"><i class="bi <?php echo e($routes['buscar'][1]); ?>"></i> <span><?php echo e($routes['buscar'][0]); ?></span></button>
                <button class="nav-pill side-pill" type="button" data-route="noticias"><i class="bi bi-newspaper"></i> <span>Noticias</span><em class="nav-badge" data-news-badge hidden>0</em></button>
            </details>
            <details class="side-group">
                <summary><i class="bi bi-controller"></i><span>Eventos</span></summary>
                <button class="nav-pill side-pill" type="button" data-route="torneos"><i class="bi <?php echo e($routes['torneos'][1]); ?>"></i> <span><?php echo e($routes['torneos'][0]); ?></span></button>
                <button class="nav-pill side-pill" type="button" data-route="temporadas"><i class="bi <?php echo e($routes['temporadas'][1]); ?>"></i> <span><?php echo e($routes['temporadas'][0]); ?></span></button>
                <button class="nav-pill side-pill" type="button" data-route="calendario"><i class="bi <?php echo e($routes['calendario'][1]); ?>"></i> <span><?php echo e($routes['calendario'][0]); ?></span><em class="nav-badge" data-alert-badge="calendar" hidden>0</em></button>
                <button class="nav-pill side-pill" type="button" data-route="resultados"><i class="bi <?php echo e($routes['resultados'][1]); ?>"></i> <span><?php echo e($routes['resultados'][0]); ?></span><em class="nav-badge" data-alert-badge="results" hidden>0</em></button>
            </details>
            <details class="side-group">
                <summary><i class="bi bi-people"></i><span>Decks y equipos</span></summary>
                <button class="nav-pill side-pill" type="button" data-route="equipos"><i class="bi <?php echo e($routes['equipos'][1]); ?>"></i> <span><?php echo e($routes['equipos'][0]); ?></span></button>
                <button class="nav-pill side-pill" type="button" data-route="deck"><i class="bi <?php echo e($routes['deck'][1]); ?>"></i> <span><?php echo e($routes['deck'][0]); ?></span></button>
                <button class="nav-pill side-pill" type="button" data-route="mundos"><i class="bi bi-globe2"></i> <span>Mundos</span></button>
                <button class="nav-pill side-pill" type="button" data-route="estadisticas"><i class="bi <?php echo e($routes['estadisticas'][1]); ?>"></i> <span><?php echo e($routes['estadisticas'][0]); ?></span></button>
            </details>
            <details class="side-group">
                <summary><i class="bi bi-wrench-adjustable-circle"></i><span>Herramientas</span></summary>
                <button class="nav-pill side-pill" type="button" data-route="calculadora_jugadas"><i class="bi bi-calculator"></i> <span>Calculadora de jugadas</span></button>
                <button class="nav-pill side-pill" type="button" data-route="inspector_cartas"><i class="bi bi-search"></i> <span>Inspector de cartas</span></button>
                <button class="nav-pill side-pill" type="button" data-route="creador_combos"><i class="bi bi-bezier2"></i> <span>Creador de combos y estrategias</span></button>
                <button class="nav-pill side-pill" type="button" data-route="herramienta_lp"><i class="bi <?php echo e($routes['herramienta_lp'][1]); ?>"></i> <span><?php echo e($routes['herramienta_lp'][0]); ?></span></button>
                <button class="nav-pill side-pill" type="button" data-route="biblioteca"><i class="bi bi-book"></i> <span>Biblioteca</span></button>
            </details>
            <details class="side-group">
                <summary><i class="bi bi-tools"></i><span>Configuracion</span></summary>
                <button class="nav-pill side-pill" type="button" data-route="gestion"><i class="bi <?php echo e($routes['gestion'][1]); ?>"></i> <span><?php echo e($routes['gestion'][0]); ?></span></button>
                <button class="nav-pill side-pill" type="button" data-route="reglamento"><i class="bi <?php echo e($routes['reglamento'][1]); ?>"></i> <span><?php echo e($routes['reglamento'][0]); ?></span></button>
            </details>
        </nav>
    </aside>
    <button class="sidebar-backdrop" type="button" data-sidebar-close aria-label="Cerrar menu"></button>

