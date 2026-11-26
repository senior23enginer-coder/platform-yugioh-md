<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/autoload.php';
require dirname(__DIR__, 2) . '/scripts/php-source-contract.php';
use StationX\Yugioh\Application\Commands\FormCommand;
use StationX\Yugioh\Application\Commands\FormCommandHandler;
use StationX\Yugioh\Application\Commands\DispatchFormCommand;
use StationX\Yugioh\Infrastructure\Composition\NativeFormCommands;
use StationX\Yugioh\Infrastructure\Http\FormActions\NativeFormHandler;

$count = 0;
function formCheck(bool $value, string $message): void
{
    global $count;
    if (!$value) {
        throw new RuntimeException($message);
    }
    $count++;
}
$fake = new class implements FormCommandHandler {
    public ?FormCommand $seen = null;
    public function execute(FormCommand $command): ?string
    {
        $this->seen = $command;
        return 'saved';
    }
};
$dispatcher = new DispatchFormCommand(['save' => $fake]);
$command = new FormCommand('save', ['id' => 1], ['image' => ['name' => 'test.png']]);
formCheck(
    $dispatcher->supports('save') && !$dispatcher->supports('unknown'),
    'Exact action lookup changed',
);
formCheck(
    $dispatcher->execute($command) === 'saved' && $fake->seen === $command,
    'Command was mutated or not delegated',
);
formCheck(
    $dispatcher->execute(new FormCommand('unknown', [])) === null,
    'Unknown action gained behavior',
);
try {
    new DispatchFormCommand(['bad' => new stdClass()]);
    throw new RuntimeException('Invalid port accepted');
} catch (InvalidArgumentException) {
    $count++;
}
formCheck(!function_exists('ygo_handle_post'), 'Application dispatcher loaded native HTTP');

$pdo = new PDO('sqlite::memory:');
$_POST = ['outer' => true];
$_FILES = ['outer' => true];
$adapter = new class ($pdo) extends NativeFormHandler {
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        formCheck(
            $_POST === $post && $_FILES === $files,
            'Legacy callees did not receive captured input',
        );
        if ($action === 'throw') {
            $_POST = ['temporary' => true];
            throw new RuntimeException('fixture');
        }
        $inner = new class ($pdo) extends NativeFormHandler {
            protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
            {
                formCheck($_POST === ['inner' => true], 'Nested request context missing');
                return 'inner';
            }
        };
        formCheck(
            $inner->execute(new FormCommand('inner', ['inner' => true])) === 'inner',
            'Nested command result lost',
        );
        formCheck($_POST === $post && $_FILES === $files, 'Nested command leaked its context');
        return 'ok';
    }
};
formCheck($adapter->execute($command) === 'ok', 'Native scoped adapter failed');
formCheck(
    $_POST === ['outer' => true] && $_FILES === ['outer' => true],
    'Native input context leaked',
);
try {
    $adapter->execute(new FormCommand('throw', ['throw' => true]));
} catch (RuntimeException $e) {
    formCheck($e->getMessage() === 'fixture', 'Exception replaced');
}
formCheck(
    $_POST === ['outer' => true] && $_FILES === ['outer' => true],
    'Exceptional context leaked',
);

$root = dirname(__DIR__);
$manifest = json_decode(
    file_get_contents($root . '/context/form-command-extractions.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
$originals = json_decode(
    file_get_contents(__DIR__ . '/fixtures/form-command-originals.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
$early = NativeFormCommands::early($pdo);
$regular = NativeFormCommands::regular($pdo);
foreach ($manifest['actions'] as $action => $entry) {
    formCheck(
        ($entry['phase'] === 'early' ? $early : $regular)->supports($action),
        'Missing action ' . $action,
    );
    formCheck(
        !($entry['phase'] === 'early' ? $regular : $early)->supports($action),
        'Action changed guard phase ' . $action,
    );
}
try {
    $regular->execute(
        new FormCommand('save_event_admission', ['admission_sources_json' => '{broken']),
    );
    throw new RuntimeException('Malformed admission accepted');
} catch (InvalidArgumentException $error) {
    formCheck(
        $error->getMessage() === 'Las fuentes recibidas no tienen un formato valido.',
        'JSON validation changed after namespacing',
    );
}
formCheck(
    (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table'")->fetchColumn() === 0,
    'Command composition initialized persistence',
);
$portedCommands = json_decode(
    file_get_contents(__DIR__ . '/fixtures/ported-form-commands.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
$comparisons = [];
foreach ($originals['branches'] as $branch) {
    $original = 'function action(){' . $branch['body'] . '}';
    formCheck(
        architectureBodyHash($original) === $branch['original_body_sha256'] ||
            architectureFormCommandPinnedEvidence($root),
        'Original action evidence changed',
    );
    $reflection = new ReflectionMethod($branch['class'], 'perform');
    $source = implode(
        '',
        array_slice(
            file($reflection->getFileName()),
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1,
        ),
    );
    $referenceSource = file_get_contents($reflection->getFileName());
    $ported = $portedCommands[$branch['actions'][0]] ?? null;
    if ($ported !== null) {
        formCheck(
            architectureBodyTokens($source) === architectureBodyTokens($ported['expected_perform']),
            'Ported command delegation changed: ' . $branch['actions'][0],
        );
        $referenceSource = $ported['original_source'];
        $start = strpos($referenceSource, '    protected function perform(');
        if ($start === false) {
            throw new RuntimeException('Missing original handler evidence');
        }
        $source = substr($referenceSource, $start, strrpos($referenceSource, "\n}") - $start);
    }
    preg_match_all(
        '/\b(?:new|instanceof)\s+([A-Za-z_]\w*)|\bcatch\s*\(\s*([A-Za-z_]\w*)/',
        $branch['body'],
        $references,
        PREG_SET_ORDER,
    );
    foreach ($references as $reference) {
        $class = $reference[1] ?: $reference[2];
        if (!in_array($class, ['self', 'static', 'parent'], true)) {
            formCheck(
                str_contains($referenceSource, 'use ' . $class . ';'),
                'Global class resolution changed: ' . $class,
            );
        }
    }
    $body = substr($source, strpos($source, '{'));
    if ($branch['actions'][0] !== 'sync_offline_form') {
        $tokens = token_get_all('<?php ' . $body);
        $body = '';
        foreach ($tokens as $token) {
            if (is_array($token) && $token[0] === T_OPEN_TAG) {
                continue;
            }
            if (
                is_array($token) &&
                $token[0] === T_VARIABLE &&
                in_array($token[1], ['$post', '$files'], true)
            ) {
                $token[1] = $token[1] === '$post' ? '$_POST' : '$_FILES';
            }
            $body .= is_array($token) ? $token[1] : $token;
        }
    }
    $comparisons[] = [
        'name' => $branch['actions'][0],
        'original' => 'function action(){' . $branch['body'] . "\nreturn null;\n}",
        'current' => 'function action()' . $body,
    ];
}
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
    throw new RuntimeException('Cannot start command comparator');
}
fwrite($pipes[0], json_encode($comparisons, JSON_THROW_ON_ERROR));
fclose($pipes[0]);
if (proc_close($process) !== 0) {
    throw new RuntimeException('Native command comparison failed');
}
echo "PASS $count checks: exact command routing, guard phases, nested context and native action evidence.\n";
