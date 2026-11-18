<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/deck_builder.php';
require dirname(__DIR__, 2) . '/scripts/php-source-contract.php';

use StationX\Yugioh\Application\Decklists\{
    DecklistReader,
    DecklistWriter,
    DeckCardCatalog,
    SaveDecklist,
};
use StationX\Yugioh\Infrastructure\Persistence\Decklists\PdoDecklists;
use StationX\Yugioh\Infrastructure\Transactions\{NativeEventWriteLock, PdoTransactionBoundary};
use StationX\Yugioh\Infrastructure\Http\Decklists\DecklistController;
use StationX\Yugioh\Infrastructure\Composition\DecklistModule;

$checks = 0;
function decklistCheck(bool $condition, string $why): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($why);
    }
    $checks++;
}
function decklistThrows(callable $action, string $type): void
{
    try {
        $action();
    } catch (Throwable $error) {
        decklistCheck($error instanceof $type, 'Unexpected exception ' . $error::class);
        return;
    }
    throw new RuntimeException('Expected ' . $type);
}
/** MySQL lock stand-in records ownership order; does not certify GET_LOCK or concurrent workers. */
function ygo_with_event_write_lock(PDO $pdo, callable $callback): mixed
{
    $GLOBALS['decklist_trace'][] = ['lock', $pdo->inTransaction()];
    try {
        return $callback();
    } finally {
        $GLOBALS['decklist_trace'][] = ['release', $pdo->inTransaction()];
    }
}
class DecklistFixturePdo extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options);
    }
}
function decklistFixtureDatabase(): PDO
{
    $pdo = new DecklistFixturePdo('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE sx_ygo_decks(id INTEGER PRIMARY KEY,name TEXT,active INTEGER)');
    $pdo->exec(
        'CREATE TABLE sx_ygo_deck_cards(id INTEGER PRIMARY KEY AUTOINCREMENT,deck_id INTEGER,card_name TEXT,role TEXT,quantity INTEGER,section TEXT,notes TEXT,ygoprodeck_id INTEGER)',
    );
    $pdo->exec(
        'CREATE TABLE sx_ygo_audit_log(id INTEGER PRIMARY KEY AUTOINCREMENT,entity_type TEXT,entity_id INTEGER,action TEXT,payload TEXT)',
    );
    $pdo->exec("INSERT INTO sx_ygo_decks VALUES(1,'Fixture deck',1),(2,'Other',1)");
    $pdo->exec(
        "INSERT INTO sx_ygo_deck_cards VALUES(1,1,'Legacy card','Starter',2,'main','Original note',NULL),(2,2,'Other card',NULL,1,'main',NULL,NULL)",
    );
    return $pdo;
}

foreach (
    json_decode(file_get_contents(__DIR__ . '/fixtures/decklist-originals.json'), true)
    as $name => $original
) {
    decklistCheck(
        architectureBodyHash($original['body']) === $original['original_body_sha256'],
        'Original evidence changed ' . $name,
    );
}
foreach (
    json_decode(file_get_contents(__DIR__ . '/fixtures/decklist-characterization.json'), true)
    as $case
) {
    $pdo = decklistFixtureDatabase();
    $actual = null;
    $error = null;
    try {
        $actual = ygo_deck_builder_save($pdo, 1, $case['input']);
    } catch (Throwable $caught) {
        $error = ['class' => $caught::class, 'message' => $caught->getMessage()];
    }
    decklistCheck(
        $actual === $case['result'] && $error === $case['error'],
        'Original save result/error changed: ' . $case['name'],
    );
    decklistCheck(
        $pdo->query('SELECT * FROM sx_ygo_deck_cards ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) ===
            $case['cards'],
        'Original inventory changed: ' . $case['name'],
    );
    decklistCheck(
        $pdo->query('SELECT * FROM sx_ygo_audit_log ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) ===
            $case['audit'],
        'Original audit changed: ' . $case['name'],
    );
    decklistCheck(!$pdo->inTransaction(), 'Own transaction leaked: ' . $case['name']);
}

/** Real SQLite persistence behind replaceable ports; faults are injected after writes to exercise rollback. */
final class DecklistTestPorts implements DecklistReader, DecklistWriter, DeckCardCatalog
{
    public bool $failAudit = false;
    public bool $failCatalog = false;
    public function __construct(private PDO $pdo, private PdoDecklists $store) {}
    public function snapshot(int $deckId, bool $lock = false): array
    {
        $GLOBALS['decklist_trace'][] = ['snapshot', $this->pdo->inTransaction(), $lock];
        return $this->store->snapshot($deckId, $lock);
    }
    public function replace(int $deckId, array $cards): void
    {
        $GLOBALS['decklist_trace'][] = ['replace', $this->pdo->inTransaction()];
        $this->store->replace($deckId, $cards);
    }
    public function audit(int $deckId, array $before, array $after): void
    {
        $GLOBALS['decklist_trace'][] = ['audit', $this->pdo->inTransaction()];
        $this->store->audit($deckId, $before, $after);
        if ($this->failAudit) {
            throw new PDOException('private storage failure');
        }
    }
    public function lookup(int $cardId): array
    {
        $GLOBALS['decklist_trace'][] = ['catalog', $this->pdo->inTransaction(), $cardId];
        if ($this->failCatalog) {
            throw new RuntimeException('fixture catalog unavailable');
        }
        return ['id' => $cardId, 'name' => 'Exact catalog card'];
    }
}
$pdo = decklistFixtureDatabase();
$store = new PdoDecklists($pdo);
$ports = new DecklistTestPorts($pdo, $store);
$save = new SaveDecklist(
    $ports,
    $ports,
    $ports,
    new NativeEventWriteLock($pdo),
    new PdoTransactionBoundary($pdo),
);
$before = $store->snapshot(1);
$input = [
    'version' => $before['version'],
    'cards' => [['card_id' => 123, 'quantity' => 2, 'section' => 'extra']],
];
$GLOBALS['decklist_trace'] = [];
$answer = $save->execute(1, $input);
decklistCheck(
    array_column($GLOBALS['decklist_trace'], 0) === [
        'lock',
        'snapshot',
        'catalog',
        'replace',
        'snapshot',
        'audit',
        'release',
    ],
    'Lock/snapshot/lookup/write/audit order',
);
decklistCheck(
    $GLOBALS['decklist_trace'][0] === ['lock', false] &&
        $GLOBALS['decklist_trace'][1] === ['snapshot', true, true] &&
        end($GLOBALS['decklist_trace']) === ['release', false],
    'Lock outside owned transaction, rows locked before lookup',
);
decklistCheck(
    $answer['cards'][0]['card_name'] === 'Exact catalog card' &&
        (int) $answer['cards'][0]['ygoprodeck_id'] === 123 &&
        $answer['cards'][0]['section'] === 'extra',
    'Exact catalog identity and inventory zone',
);
decklistCheck(
    $store->snapshot(2)['cards'][0]['card_name'] === 'Other card',
    'Other decks untouched',
);
$current = $store->snapshot(1);
$audits = (int) $pdo->query('SELECT COUNT(*) FROM sx_ygo_audit_log')->fetchColumn();
$ports->failAudit = true;
decklistThrows(
    fn() => $save->execute(1, ['version' => $current['version'], 'cards' => []]),
    PDOException::class,
);
decklistCheck(
    !$pdo->inTransaction() &&
        $store->snapshot(1) === $current &&
        (int) $pdo->query('SELECT COUNT(*) FROM sx_ygo_audit_log')->fetchColumn() === $audits,
    'Failed audit rolls back inventory and audit together',
);
$ports->failAudit = false;
$ports->failCatalog = true;
decklistThrows(
    fn() => $save->execute(1, [
        'version' => $current['version'],
        'cards' => [['card_id' => 456, 'quantity' => 1, 'section' => 'main']],
    ]),
    RuntimeException::class,
);
decklistCheck(
    $store->snapshot(1) === $current && !$pdo->inTransaction(),
    'Provider error precedes all inventory writes',
);
$ports->failCatalog = false;
// Documented name is independent from the optional exact image ID, which is still resolved once.
$result = $save->execute(1, [
    'version' => $current['version'],
    'documented_import' => true,
    'cards' => [
        [
            'card_id' => 456,
            'card_name' => 'Original documentary name',
            'quantity' => 3,
            'section' => 'side',
        ],
    ],
]);
decklistCheck(
    $result['cards'][0]['card_name'] === 'Original documentary name' &&
        (int) $result['cards'][0]['ygoprodeck_id'] === 456,
    'Documented name does not become catalog/image name',
);
$current = $store->snapshot(1);
$pdo->beginTransaction();
$save->execute(1, ['version' => $current['version'], 'cards' => []]);
decklistCheck($pdo->inTransaction(), 'Success does not commit borrowed transaction');
$pdo->rollBack();
decklistCheck(
    $store->snapshot(1) === $current,
    'Caller rollback restores successful borrowed save',
);
$ports->failAudit = true;
$pdo->beginTransaction();
decklistThrows(
    fn() => $save->execute(1, ['version' => $current['version'], 'cards' => []]),
    PDOException::class,
);
decklistCheck($pdo->inTransaction(), 'Failure does not roll back borrowed transaction');
$pdo->rollBack();
$ports->failAudit = false;
decklistCheck($store->snapshot(1) === $current, 'Caller rollback restores partial failed save');
$GLOBALS['decklist_trace'] = [];
decklistThrows(
    fn() => $save->execute(1, ['version' => 'stale', 'cards' => []]),
    InvalidArgumentException::class,
);
decklistCheck(
    array_column($GLOBALS['decklist_trace'], 0) === ['lock', 'snapshot', 'release'],
    'Stale version fails before catalog or write',
);

$controller = new DecklistController($ports, $save);
$permissionCalls = 0;
$bodyCalls = 0;
$canEdit = static function () use (&$permissionCalls): bool {
    $permissionCalls++;
    return true;
};
$body = static function () use (&$bodyCalls): string {
    $bodyCalls++;
    return '{}';
};
$response = $controller->handle('GET', 1, 'fixture-token', $canEdit, $body);
decklistCheck(
    $response->status === 200 &&
        $response->body['token'] === 'fixture-token' &&
        $response->body['can_edit'] === true &&
        $bodyCalls === 0,
    'Read endpoint token/permission without reading body',
);
$response = $controller->handle('POST', 1, 'fixture-token', static fn() => false, $body);
decklistCheck($response->status === 422 && $bodyCalls === 0, 'Permission precedes body capture');
$GLOBALS['decklist_trace'] = [];
$response = $controller->handle('POST', 1, 'fixture-token', $canEdit, $body);
decklistCheck(
    $response->status === 422 && !$GLOBALS['decklist_trace'],
    'Token rejected before lock/lookup/write',
);
decklistCheck(
    $controller->handle(
        'POST',
        1,
        'fixture-token',
        $canEdit,
        static fn() => str_repeat('x', 100001),
    )->status === 422,
    'Body byte bound',
);
$response = $controller->handle('POST', 1, 'fixture-token', $canEdit, static fn() => '{');
decklistCheck(
    $response->status === 503 &&
        $response->body['message'] === 'Los datos de la lista no son válidos.',
    'Malformed JSON retains native generic mapping',
);
$input = ['token' => 'fixture-token', 'version' => $store->snapshot(1)['version'], 'cards' => []];
$response = $controller->handle(
    'POST',
    1,
    'fixture-token',
    $canEdit,
    static fn() => json_encode($input),
);
decklistCheck(
    $response->status === 200 && $response->body['cards'] === [],
    'Valid HTTP save reaches same application use case',
);
decklistCheck(
    $controller->handle('PUT', 1, 'fixture-token', $canEdit, $body)->status === 422,
    'Unsupported method',
);
$response = DecklistController::failure(new PDOException('private credentials fixture'));
decklistCheck(
    $response->status === 503 && !str_contains(json_encode($response->body), 'private'),
    'Storage failures are private',
);
$empty = new PDO('sqlite::memory:');
DecklistModule::reader($empty);
DecklistModule::save($empty);
decklistCheck(
    !$empty->inTransaction() &&
        (int) $empty
            ->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table'")
            ->fetchColumn() === 0,
    'Composition has no schema queries or transaction',
);
echo "PASS $checks decklist architecture checks, including 15 original save/error/inventory/audit scenarios; no production or upstream access.\n";
