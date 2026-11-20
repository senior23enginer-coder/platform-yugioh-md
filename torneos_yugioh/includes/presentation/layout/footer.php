<?php
$stationxVersion = 'sin registrar';
$stationxReleasePath = dirname(__DIR__, 3) . '/release.json';
if (is_file($stationxReleasePath)) {
    $stationxRelease = json_decode((string) file_get_contents($stationxReleasePath), true);
    if (is_array($stationxRelease) && isset($stationxRelease['version']) &&
        is_string($stationxRelease['version']) &&
        preg_match('/^v[0-9]+\.[0-9]+\.[0-9]+$/', $stationxRelease['version'])) {
        $stationxVersion = $stationxRelease['version'];
    }
}
?>
<footer class="app-footer">
    <span>YUGIOH MD - Station X Tulua <small class="ms-2">Versión desplegada: <?= htmlspecialchars($stationxVersion, ENT_QUOTES, 'UTF-8') ?></small></span>
    <span>PHP + Bootstrap 5 + MySQL editable</span>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js" defer></script>
<script src="./assets/ui-labels.js?v=20261026-labels-101" defer></script>
<script src="./assets/event-admission.js?v=20261018-ranking-47" defer></script>
<script src="./assets/free-for-all.js?v=20261018-ffa-47" defer></script>
<script src="./assets/event-teams.js?v=20261018-ranking-47" defer></script>
<script src="./assets/event-recovery.js?v=20261018-ranking-47" defer></script>
<script src="./assets/event-models.js?v=20261018-ranking-47" defer></script>
<script src="./assets/modules/statistics/global-ranking.js?v=20261117-global-catalog"></script>
<script src="./assets/modules/statistics/workspace.js?v=20261117-global-catalog"></script>
<script src="./assets/statistics-v8.js?v=20261119-profile-summary-1"></script>
<script src="./assets/modules/statistics/live.js?v=20261113-stats-live-180"></script>
<script src="./assets/library-search.js?v=20261018-library-48" defer></script>
<script src="./assets/library.js?v=20261018-library-48" defer></script>
<link href="./assets/modules/cup-bracket/navigation.css?v=20261108-cup-open-155" rel="stylesheet">
<script src="./assets/modules/cup-bracket/navigation.js?v=20261108-cup-open-155" defer></script>
<script src="./assets/cup-tree.js?v=20261108-cup-open-155" defer></script>
<script src="./assets/deck-events.js?v=20261024-event-filters-90" defer></script>
<script src="./assets/daily-duels.js?v=20261026-labels-101" defer></script>
<script src="./assets/card-inspector.js?v=20261119-study-photo-1" defer></script>
<script src="./assets/deck-builder.js?v=20261119-study-photo-1" defer></script>
<script src="./assets/deck-history.js?v=20261024-history-92" defer></script>
<script src="./assets/modules/deck-guide/strategy-workspace.js?v=20261030-depth-122" defer></script>
<script src="./assets/modules/deck-guide/deck-deliveries.js?v=20261102-library-125" defer></script>
<script src="./assets/modules/deck-export/export.js?v=20261102-export-127" defer></script>
<link rel="stylesheet" href="./assets/modules/deck-data/styles.css?v=20261119-import-progress-1">
<script src="./assets/modules/deck-data/ui.js?v=20261119-import-progress-1" defer></script>
<script src="./assets/modules/deck-guide/deck-study.js?v=20261102-library-125" defer></script>
<script src="./assets/modules/deck-guide/learning-guide.js?v=20261102-library-125" defer></script>
<script src="./assets/result-desktop.js?v=20261109-result-button-158" defer></script>
<script src="./assets/participant-labels.js?v=20261027-participants-103" defer></script>
<script src="./assets/lp-workspace.js?v=20261027-lp-105" defer></script>
<script src="./assets/lp-sound.js?v=20261027-sound-106" defer></script>
<script src="./assets/storage-maintenance.js?v=20261028-cleanup-113" defer></script>
<script src="./assets/news.js?v=20261028-news-115" defer></script>
<script src="./assets/modules/play-calculator/engine.js?v=20261120-coverage-1" defer></script>
<script src="./assets/modules/play-calculator/hand-practice.js?v=20261113-hand-practice-170" defer></script>
<script src="./assets/modules/play-calculator/storage.js?v=20261103-board-129" defer></script>
<script src="./assets/modules/play-calculator/card-menu.js?v=20261116-actions-1" defer></script>
<script src="./assets/modules/play-calculator/rules.js?v=20261120-dynamic-summon-1" defer></script>
<script src="./assets/modules/play-calculator/planner.js?v=20261120-portable-horus-1" defer></script>
<script src="./assets/modules/play-calculator/verified-rules.js?v=20261118-memento-effects-5" defer></script>
<script src="./assets/modules/play-calculator/study.js?v=20261120-memento-portable-1" defer></script><script src="./assets/modules/play-calculator/chain.js?v=20261118-chain-2" defer></script><script src="./assets/modules/play-calculator/ui.js?v=20261120-portable-horus-1" defer></script>
<script src="./assets/modules/combo-creator/model.js?v=20261103-combos-133" defer></script>
<script src="./assets/modules/combo-creator/step-lesson.js?v=20261113-step-lessons-173" defer></script>
<script src="./assets/modules/combo-creator/documented-import.js?v=20261116-folder-import-1" defer></script>
<script src="./assets/modules/play-calculator/workspace.js?v=20261120-deck-combobox-1" defer></script>
<script src="./assets/modules/card-inspector/model.js?v=20261116-readable-3" defer></script>
<script src="./assets/modules/card-inspector/ui.js?v=20261116-readable-3" defer></script>
<script src="./assets/modules/combo-creator/ui.js?v=20261119-local-study-photos-1" defer></script>
<script src="./assets/modules/match-sheet/navigation.js?v=20261104-match-sheet-134" defer></script>
<script src="./assets/modules/match-sheet/entity-links.js?v=20261117-entity-links" defer></script>
<link href="./assets/modules/deck-guide/checked-study.css?v=20261104-checked-study-137" rel="stylesheet">
<link href="./assets/modules/deck-guide/study-sections.css?v=20261107-study-eleven-139" rel="stylesheet">
<script src="./assets/modules/deck-guide/study-eleven.js?v=20261107-study-ten-143" defer></script>
<script src="./assets/modules/deck-guide/study-sections.js?v=20261107-study-ten-143" defer></script>
<link href="./assets/modules/deck-guide/curated-study.css?v=20261119-visual-parity-1" rel="stylesheet">
<script src="./assets/modules/deck-guide/photo-viewer.js?v=20261108-reader-148" defer></script>
<link href="./assets/modules/deck-guide/endboard.css?v=20261113-combo-library-164" rel="stylesheet">
<script src="./assets/modules/deck-guide/endboard.js?v=20261113-combo-library-164" defer></script>
<link href="./assets/modules/deck-guide/curated-visual.css?v=20261113-combo-library-164" rel="stylesheet">
<link href="./assets/modules/deck-guide/delivery-extensions.css?v=20261114-delivery-v12-186" rel="stylesheet">
<script src="./assets/modules/deck-guide/delivery-extensions.js?v=20261116-folder-import-1" defer></script>
<script src="./assets/modules/deck-guide/presentation-contract.js?v=20261119-visual-parity-1" defer></script>
<script src="./assets/modules/deck-guide/curated-visual.js?v=20261119-visual-parity-1" defer></script>
<script src="./assets/modules/deck-guide/functional-role.js?v=20261116-folder-import-1" defer></script>
<script src="./assets/modules/deck-guide/card-detail.js?v=20261115-card-detail-196" defer></script>
<script src="./assets/modules/deck-guide/curated-study.js?v=20261119-visual-parity-1" defer></script>
<script src="./assets/modules/combo-creator/editorial-library.js?v=20261113-combo-library-164" defer></script>
<script src="./assets/modules/deck-guide/checked-study.js?v=20261108-curated-147" defer></script>
<script src="./assets/modules/deck-guide/content-state.js?v=20261119-visual-parity-1" defer></script>
<script src="./assets/modules/dashboard/ranking-scopes.js?v=20261107-ranking-divisions-146" defer></script>
<script src="./assets/modules/navigation/global-search.js?v=20261113-global-search-167" defer></script>
<link rel="stylesheet" href="./assets/modules/deck-guide/league-position.css?v=20261113-match-positions-183">
<link rel="stylesheet" href="./assets/modules/deck-attributes/style.css?v=20261118-worlds-hexagonal">
<script src="./assets/modules/deck-attributes/model.js?v=20261118-worlds-hexagonal"></script>
<script src="./assets/modules/deck-attributes/api.js?v=20261118-worlds-hexagonal"></script>
<script src="./assets/modules/deck-attributes/ui.js?v=20261118-worlds-hexagonal"></script>
<script src="./assets/modules/deck-guide/league-position.js?v=20261113-match-positions-182" defer></script>
<script src="./assets/app.js?v=20261119-fullscreen-calc-1" defer></script>
<script src="./assets/match-editor.js?v=20261027-participants-103" defer></script>
<script src="./assets/event-audit.js?v=20261021-audit-55" defer></script>
<script src="./assets/seasons.js?v=20261016-season-editor-4" defer></script>
<link rel="stylesheet" href="./assets/modules/deck-template/style.css?v=20261119-template-deck-1">
<script src="./assets/modules/deck-template/ui.js?v=20261119-template-deck-1" defer></script>
</body>
</html>






