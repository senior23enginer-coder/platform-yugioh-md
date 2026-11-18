<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/autoload.php';
use StationX\Yugioh\Application\Combos\ComboInventory;
use StationX\Yugioh\Application\Combos\ComboRepository;
use StationX\Yugioh\Application\Combos\ComboSources;
use StationX\Yugioh\Application\Combos\PrepareComboDocument;
use StationX\Yugioh\Application\Combos\SaveCreatedCombo;
use StationX\Yugioh\Application\Combos\ComboQueries;
use StationX\Yugioh\Application\Shared\Clock;
use StationX\Yugioh\Application\Shared\TransactionBoundary;
use StationX\Yugioh\Infrastructure\Composition\ComboModule;

$count = 0;
function comboPortCheck(bool $ok, string $message): void
{
    global $count;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $count++;
}
function comboPortRejects(callable $operation, string $class): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        comboPortCheck($error instanceof $class, get_class($error));
        return;
    }
    throw new RuntimeException('Expected ' . $class);
}
$transaction = new class implements TransactionBoundary {
    public bool $active = false;
    public int $calls = 0;
    public function run(callable $operation): mixed
    {
        $this->calls++;
        $this->active = true;
        try {
            return $operation();
        } finally {
            $this->active = false;
        }
    }
};
$clock = new class implements Clock {
    public int $calls = 0;
    public function now(): DateTimeImmutable
    {
        $this->calls++;
        return new DateTimeImmutable('2026-11-18 15:00:00+00:00');
    }
};
$inventory = new class ($transaction) implements ComboInventory {
    public array $requests = [];
    public function __construct(private object $transaction) {}
    public function load(int $deckId, bool $lock = false, bool $requireMain = true): array
    {
        if ($lock && !$this->transaction->active) {
            throw new RuntimeException('Inventory lock outside transaction');
        }
        $this->requests[] = [$deckId, $lock, $requireMain];
        return [
            'deck' => ['id' => $deckId],
            'inventory_sha256' => 'known-hash',
            'cards' => [
                ['id' => 11, 'card_name' => 'Starter', 'section' => 'main', 'quantity' => 1],
            ],
            'format' => 'tcg',
        ];
    }
};
$store = new class ($transaction) implements ComboRepository {
    public array $rows = [];
    public array $revisions = [];
    public function __construct(private object $transaction) {}
    public function find(string $id): ?array
    {
        return $this->rows[$id] ?? null;
    }
    public function forDeck(int $deckId): array
    {
        return array_values(
            array_filter($this->rows, static fn($row) => $row['deck_id'] === $deckId),
        );
    }
    public function history(string $id): array
    {
        return array_reverse($this->revisions[$id] ?? []);
    }
    public function persist(array $record, bool $exists, int $expectedRevision): void
    {
        if (!$this->transaction->active) {
            throw new RuntimeException('Write outside transaction');
        }
        comboPortCheck(
            $expectedRevision === ($this->rows[$record['id']]['revision'] ?? 0),
            'Expected revision changed',
        );
        $this->rows[$record['id']] = $record;
    }
    public function appendRevision(array $record, string $createdAt): void
    {
        if (!$this->transaction->active) {
            throw new RuntimeException('History outside transaction');
        }
        $this->revisions[$record['id']][] = [
            'revision' => $record['revision'],
            'snapshot_json' => json_encode($record, JSON_THROW_ON_ERROR),
            'created_at' => $createdAt,
        ];
    }
};
$sources = new class implements ComboSources {
    public int $references = 0;
    public int $renders = 0;
    public function context(int $deckId): array
    {
        return [];
    }
    public function reference(array $input, int $deckId): array
    {
        $this->references++;
        return ['source_route_id' => 'documented-route'];
    }
    public function markdown(array $record): string
    {
        $this->renders++;
        return 'Source revision ' . $record['revision'];
    }
};
$documents = new PrepareComboDocument($sources);
$save = new SaveCreatedCombo($inventory, $store, $documents, $sources, $transaction, $clock);
$input = [
    'deck_id' => 1,
    'revision' => 0,
    'inventory_sha256' => 'known-hash',
    'document' => [
        'id' => 'combo-ports-test-0001',
        'title' => 'Explicit plan',
        'nodes' => [
            [
                'id' => 'a',
                'kind' => 'opening',
                'title' => 'Opening',
                'x' => 20,
                'y' => 20,
                'cards' => [11],
            ],
        ],
        'edges' => [],
    ],
];
$before = serialize($input);
comboPortCheck(!function_exists('ygo_combo_save'), 'Application loaded native persistence');
comboPortRejects(fn() => $save->execute([]), InvalidArgumentException::class);
comboPortCheck($transaction->calls === 0, 'Invalid command opened a transaction');
$first = $save->execute($input);
comboPortCheck(
    $first['revision'] === 1 && $first['updated_at'] === '2026-11-18 15:00:00',
    'Injected clock/revision ignored',
);
comboPortCheck(
    $inventory->requests[0] === [1, true, true],
    'Saving requires locked Main inventory',
);
comboPortCheck(isset($store->revisions[$first['id']][0]), 'Missing immutable revision');
$revision = json_decode($store->revisions[$first['id']][0]['snapshot_json'], true);
comboPortCheck(
    $revision['source_prompt'] === 'Source revision 1',
    'Source snapshot not atomic with history',
);
comboPortCheck(
    $save->execute($input)['revision'] === 1,
    'Identical stale retry created a revision',
);
comboPortCheck(
    $clock->calls === 1 && $sources->renders === 1,
    'Identical retry generated time/source writes',
);
$stale = $input;
$stale['document']['title'] = 'Conflicting work';
comboPortRejects(fn() => $save->execute($stale), DomainException::class);
$stale['revision'] = 1;
$second = $save->execute($stale);
comboPortCheck(
    $second['revision'] === 2 && count($store->revisions[$first['id']]) === 2,
    'Updated revision missing',
);
$wrong = $input;
$wrong['inventory_sha256'] = 'stale';
$references = $sources->references;
comboPortRejects(fn() => $save->execute($wrong), DomainException::class);
comboPortCheck($sources->references === $references, 'Stale inventory reached source validation');
$foreign = $input;
$foreign['deck_id'] = 2;
comboPortRejects(fn() => $save->execute($foreign), InvalidArgumentException::class);
$query = new ComboQueries($inventory, $store);
comboPortCheck(
    count($query->overview(1, $first['id'])['history']) === 2,
    'History query lost revisions',
);
comboPortRejects(fn() => $query->overview(2, $first['id']), InvalidArgumentException::class);
comboPortCheck(serialize($input) === $before, 'Caller command was mutated');
comboPortCheck(!$transaction->active, 'Use case leaked transaction state');

// Real persistence stays local: an isolated SQLite fixture exercises rollback and ownership.
require dirname(__DIR__) . '/includes/decks/combo-creator.php';
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
ComboModule::create($pdo);
comboPortCheck(
    (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table'")->fetchColumn() === 0,
    'Composition created schema',
);
$pdo->exec(
    'CREATE TABLE sx_ygo_decks(id INT PRIMARY KEY,name TEXT); CREATE TABLE sx_ygo_deck_cards(id INT PRIMARY KEY,deck_id INT,card_name TEXT,quantity INT,section TEXT,ygoprodeck_id INT)',
);
$pdo->exec(
    "INSERT INTO sx_ygo_decks VALUES(1,'Fixture'); INSERT INTO sx_ygo_deck_cards VALUES(11,1,'Starter',1,'main',1)",
);
ygo_combo_schema($pdo);
$module = ComboModule::create($pdo, $clock);
$input['inventory_sha256'] = $module->inventory->load(1)['inventory_sha256'];
$module->save->execute($input);
$pdo->exec(
    "CREATE TRIGGER reject_combo_history BEFORE INSERT ON sx_ygo_created_combo_revisions BEGIN SELECT RAISE(ABORT,'fixture history failure'); END",
);
$input['revision'] = 1;
$input['document']['title'] = 'Must roll back';
comboPortRejects(fn() => $module->save->execute($input), PDOException::class);
comboPortCheck(!$pdo->inTransaction(), 'Owned transaction remained open');
comboPortCheck(
    (int) $pdo->query('SELECT revision FROM sx_ygo_created_combos')->fetchColumn() === 1,
    'History failure left a partial current record',
);
comboPortCheck(
    (int) $pdo->query('SELECT COUNT(*) FROM sx_ygo_created_combo_revisions')->fetchColumn() === 1,
    'History failure changed revisions',
);
$pdo->beginTransaction();
comboPortRejects(fn() => $module->save->execute($input), PDOException::class);
comboPortCheck($pdo->inTransaction(), 'Standalone save rolled back the caller transaction');
$pdo->rollBack();
echo "PASS $count checks: injected combo ports, scoped history, retries, source snapshots and atomic rollback.\n";
