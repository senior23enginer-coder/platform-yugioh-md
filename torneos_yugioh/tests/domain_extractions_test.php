<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/autoload.php';
require dirname(__DIR__, 2) . '/scripts/php-source-contract.php';

$root = dirname(__DIR__);
$contracts = json_decode(
    file_get_contents($root . '/context/domain-extractions.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
$originals = json_decode(
    file_get_contents(__DIR__ . '/fixtures/domain-originals.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
$comparisons = [];
$originalNames = [];
foreach ($contracts as $name => $contract) {
    $originalNames[$contract['class'] . '::' . $contract['method']] = $name;
}

function extractionSource(ReflectionFunctionAbstract $function): string
{
    return implode(
        '',
        array_slice(
            file($function->getFileName()),
            $function->getStartLine() - 1,
            $function->getEndLine() - $function->getStartLine() + 1,
        ),
    );
}

function extractionParameters(ReflectionFunctionAbstract $function): array
{
    return array_map(
        static fn(ReflectionParameter $p): array => [
            $p->getName(),
            (string) $p->getType(),
            $p->isPassedByReference(),
            $p->isVariadic(),
            $p->isDefaultValueAvailable(),
            $p->isDefaultValueAvailable() ? $p->getDefaultValue() : null,
        ],
        $function->getParameters(),
    );
}
foreach ($contracts as $name => $contract) {
    require_once $root . '/' . $contract['facade'];
    $facade = new ReflectionFunction($name);
    $method = new ReflectionMethod($contract['class'], $contract['method']);
    if (!$method->isPublic() || !$method->isStatic()) {
        throw new RuntimeException('Policy visibility changed: ' . $name);
    }
    if (
        extractionParameters($facade) !== extractionParameters($method) ||
        (string) $facade->getReturnType() !== (string) $method->getReturnType() ||
        $facade->returnsReference() !== $method->returnsReference()
    ) {
        throw new RuntimeException('Native signature changed: ' . $name);
    }
    $original = $originals[$name]['signature'] . $originals[$name]['body'];
    if (architectureBodyHash($original) !== $contract['original_body_sha256']) {
        throw new RuntimeException('Original evidence was changed: ' . $name);
    }
    $source = extractionSource($method);
    $body = substr($source, strpos($source, '{'));
    $comparisons[] = [
        'name' => $name,
        'original' => $original,
        'current' =>
            $originals[$name]['signature'] . architectureRestoreBody($body, $originalNames),
    ];
}
echo 'PASS ' . count($contracts) . " native/domain signatures and original algorithm contracts.\n";

// Avoid shell interpolation: pass the exact inputs through stdin to the pinned formatter.
$process = proc_open(
    [
        getenv('NODE_BINARY') ?: 'node',
        dirname(__DIR__, 2) . '/scripts/compare-domain-contracts.cjs',
    ],
    [['pipe', 'r'], STDOUT, STDERR],
    $pipes,
    dirname(__DIR__, 2),
);
if (!is_resource($process)) {
    throw new RuntimeException('Cannot start algorithm comparator');
}
fwrite($pipes[0], json_encode($comparisons, JSON_THROW_ON_ERROR));
fclose($pipes[0]);
if (proc_close($process) !== 0) {
    throw new RuntimeException('Migrated algorithm comparison failed');
}
