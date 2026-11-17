<?php
declare(strict_types=1);
// Deploy only to /.well-known/oauth-authorization-server/yugioh/mcp/oauth/index.php.
// The web root is five ancestors above that deployment directory.
ini_set('display_errors','0');header('Content-Type: application/json');header('Cache-Control: no-store');
try {
    require_once dirname(__DIR__,5).'/yugioh/includes/chatgpt/auth.php';
    echo json_encode(YgoMcpAuth::load()->metadata(),JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
} catch(Throwable) { http_response_code(503);echo '{"error":"connector_unavailable"}'; }
