<?php
declare(strict_types=1);

// The Reports module was retired; native statistics and entity PDFs are independent.
if ((string)($_GET['api'] ?? '') === 'reports' ||
    (isset($_GET['export']) && (string)($_GET['api'] ?? '') !== 'statistics' && (string)$_GET['export'] !== 'entity_report')) {
    http_response_code(410);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['ok' => false, 'message' => 'El módulo de Reportes fue eliminado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/src/autoload.php';
require_once __DIR__ . '/src/legacy-functions.php';

// Load existing adapters before executing the request.
require YGO_APP_ROOT . '/includes/bootstrap/dependencies.php';

require YGO_APP_ROOT . '/includes/bootstrap/defaults.php';

// Preserve native request transactions, then derive presentation projections.
require YGO_APP_ROOT . '/includes/bootstrap/load-state.php';

require YGO_APP_ROOT . '/includes/presentation/read-model.php';

// Endpoints terminate their response; only page requests continue to rendering.
require YGO_APP_ROOT . '/includes/http/endpoints.php';

require YGO_APP_ROOT . '/includes/presentation/page-context.php';

require YGO_APP_ROOT . '/includes/presentation/page.php';
