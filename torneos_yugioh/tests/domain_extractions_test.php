<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/autoload.php';
require dirname(__DIR__, 2) . '/scripts/php-source-contract.php';

$root = dirname(__DIR__);
$contractSource = file_get_contents($root . '/context/domain-extractions.json');
$originalSource = file_get_contents(__DIR__ . '/fixtures/domain-originals.json');
$contracts = json_decode($contractSource, true, flags: JSON_THROW_ON_ERROR);
$originals = json_decode($originalSource, true, flags: JSON_THROW_ON_ERROR);

// Historical original_body_sha256 values were recorded with PHP tokenizer
// integer IDs, which are not stable across PHP releases. Never rewrite those
// original fingerprints just to satisfy today's PHP version. In the legacy
// mismatch case, accept ONLY the exact, reviewed original source snapshots
// from the base branch (Git blob SHA-1), then run the full migrated-body
// comparison below. Any byte change to either evidence file fails closed.
$gitBlobSha = static fn(string $bytes): string => sha1('blob ' . strlen($bytes) . "\0" . $bytes);
$legacyEvidenceUnchanged =
    hash_equals('ddb4d96cd034e79c1692c6ab6a914497a1eee74f', $gitBlobSha($originalSource)) &&
    hash_equals('e5c3e9efda05ccd50af97d52fb2da2e3f90b0345', $gitBlobSha($contractSource));
$legacyTokenMismatchCount = 0;
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
        if (!$legacyEvidenceUnchanged) {
            throw new RuntimeException('Original evidence was changed: ' . $name);
        }
        $legacyTokenMismatchCount++;
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
if ($legacyTokenMismatchCount > 0) {
    fwrite(
        STDERR,
        "PHP tokenizer IDs differ for $legacyTokenMismatchCount legacy hashes; both original evidence files match pinned Git blobs.\n",
    );
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
