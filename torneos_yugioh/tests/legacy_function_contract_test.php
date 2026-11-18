<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/autoload.php';
require dirname(__DIR__) . '/src/legacy-functions.php';

// These hashes cover entire function bodies, including SQL, lock and transaction calls.
// Paths are normalized to the application root; line endings are normalized for Git checkouts.
$root = dirname(__DIR__);
$contracts = json_decode(file_get_contents($root . '/context/source-function-map.json'), true, flags: JSON_THROW_ON_ERROR);
foreach ($contracts as $name => $contract) {
    if (!function_exists($name)) throw new RuntimeException('Missing compatibility function: ' . $name);
    $function = new ReflectionFunction($name);
    if (realpath($root . '/' . $contract['file']) !== realpath($function->getFileName())) {
        throw new RuntimeException('Unexpected adapter for ' . $name);
    }
    $lines = file($function->getFileName());
    $body = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));
    $body = rtrim(str_replace("\r\n", "\n", $body), "\r\n");
    if (hash('sha256', $body) !== $contract['sha256']) {
        throw new RuntimeException('Compatibility body changed without reviewing its contract: ' . $name);
    }
}
echo 'OK: ' . count($contracts) . " declaration-only legacy adapters retain their complete bodies.\n";
