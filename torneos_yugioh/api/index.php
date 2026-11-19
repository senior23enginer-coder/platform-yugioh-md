<?php
declare(strict_types=1);

// Documentation and rejected requests must never initialize persistence or the application seed.
$routes = require __DIR__ . '/routes.php';
$path = rtrim((string) ($_SERVER['PATH_INFO'] ?? ''), '/') ?: '/';
if ($path === '/') {
    header('Location: ../api-docs/', true, 302);
    exit;
}
$route = $routes[$path] ?? null;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($route === null || !in_array($method, $route['methods'], true)) {
    http_response_code($route === null ? 404 : 405);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if ($route !== null) header('Allow: ' . implode(', ', $route['methods']));
    echo json_encode(['ok' => false, 'message' => $route === null ? 'Ruta no encontrada.' : 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Only documented query parameters may reach native routing. Do not accept api/op overrides,
// public-view switches or the internal source-prompts feed through this adapter.
$query = array_intersect_key($_GET, array_flip($route['query']));
$query['api'] = $route['api'];
if (isset($route['op'])) $query['op'] = $route['op'];
$_GET = $query;
// The original body, upload, cookies, permission checks, locks and revisions are unchanged.
require dirname(__DIR__) . '/index.php';
