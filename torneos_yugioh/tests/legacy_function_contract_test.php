<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/autoload.php';
require dirname(__DIR__) . '/src/legacy-functions.php';

// These hashes cover entire function bodies, including SQL, lock and transaction calls.
// Paths are normalized to the application root; line endings are normalized for Git checkouts.
$root = dirname(__DIR__);
require dirname(__DIR__, 2) . '/scripts/php-source-contract.php';
$formExtractions = json_decode(file_get_contents($root . '/context/form-command-extractions.json'), true, flags: JSON_THROW_ON_ERROR);
$formOriginals = json_decode(file_get_contents(__DIR__ . '/fixtures/form-command-originals.json'), true, flags: JSON_THROW_ON_ERROR);
$extractions = json_decode(file_get_contents($root . '/context/domain-extractions.json'), true, flags: JSON_THROW_ON_ERROR);
$originals = json_decode(file_get_contents(__DIR__ . '/fixtures/domain-originals.json'), true, flags: JSON_THROW_ON_ERROR);
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
    if ($name === $formExtractions['function']) {
        $original = rtrim(str_replace("\r\n", "\n", $formOriginals['signature'] . $formOriginals['body']), "\r\n");
        if (hash('sha256', $original) !== $contract['sha256']) throw new RuntimeException('Original dispatcher evidence changed');
        if (hash('sha256', $body) !== $formExtractions['dispatcher_sha256']) throw new RuntimeException('Dispatcher guards/order changed without review');
        continue;
    }
    if (isset($extractions[$name])) {
        // Preserve the original byte-level evidence. Its algorithm is also checked against the domain method.
        $original = rtrim(str_replace("\r\n", "\n", $originals[$name]['signature'] . $originals[$name]['body']), "\r\n");
        if (hash('sha256', $original) !== $contract['sha256']) throw new RuntimeException('Original native evidence changed: ' . $name);
        $entry = $extractions[$name];
        $arguments = implode(', ', array_map(static fn($p) => '$' . $p->getName(), $function->getParameters()));
        $return = (string) $function->getReturnType() === 'void' ? '' : 'return ';
        $expected = 'function expected() { ' . $return . '\\' . $entry['class'] . '::' . $entry['method'] . '(' . $arguments . '); }';
        if (architectureBodyTokens($body) !== architectureBodyTokens($expected)) throw new RuntimeException('Native delegation changed: ' . $name);
        continue;
    }
    if (hash('sha256', $body) !== $contract['sha256']) {
        throw new RuntimeException('Compatibility body changed without reviewing its contract: ' . $name);
    }
}
echo 'OK: ' . count($contracts) . " native contracts retain their original bodies or verified domain delegation.\n";
