<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/news.php';
require dirname(__DIR__) . '/includes/news_http.php';
require dirname(__DIR__, 2) . '/scripts/php-source-contract.php';

use StationX\Yugioh\Application\News\{
    NewsFetcher,
    NewsSyncLock,
    NewsSourceState,
    NewsImageUrls,
    NewsImageCache,
    NewsImageFile,
    CollectNewsItems,
    DiscoverNews,
    NormalizeNewsItem,
    SynchronizeNews,
    ListNews,
    UpdateNewsReading,
    GetNewsImage,
    NewsServices,
};
use StationX\Yugioh\Domain\News\{NewsUrls, NewsContent, NewsFilter};
use StationX\Yugioh\Infrastructure\Composition\NewsModule;
use StationX\Yugioh\Infrastructure\Configuration\News\NativeNewsProviders;
use StationX\Yugioh\Infrastructure\Time\NativePublicationDates;
use StationX\Yugioh\Infrastructure\Network\News\NativeNewsFailureMessage;
use StationX\Yugioh\Infrastructure\Persistence\News\{
    PdoNewsItems,
    PdoNewsReadStore,
    PdoNewsReaderActions,
};
use StationX\Yugioh\Infrastructure\Cache\News\FilesystemNewsImageCache;
use StationX\Yugioh\Infrastructure\Transactions\PdoTransactionBoundary;
use StationX\Yugioh\Infrastructure\Http\News\{NewsController, NewsRequest};

$checks = 0;
function newsCheck(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
}
function newsThrows(callable $action, string $class): void
{
    try {
        $action();
    } catch (Throwable $error) {
        newsCheck($error instanceof $class, 'Unexpected exception: ' . $error::class);
        return;
    }
    throw new RuntimeException('Expected ' . $class);
}

// Golden results were captured from native functions BEFORE replacing their implementations.
$previousTimezone = date_default_timezone_get();
date_default_timezone_set('America/Bogota');
foreach (
    json_decode(
        file_get_contents(__DIR__ . '/fixtures/news-characterization.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    )
    as $case
) {
    $actual = null;
    $error = null;
    try {
        $actual = $case['function'](...$case['input']);
    } catch (Throwable $caught) {
        $error = ['class' => $caught::class, 'message' => $caught->getMessage()];
    }
    newsCheck(
        $actual === $case['result'] && $error === $case['error'],
        'Native characterization mismatch: ' .
            $case['function'] .
            ' ' .
            json_encode([$actual, $error], JSON_UNESCAPED_UNICODE),
    );
}
date_default_timezone_set($previousTimezone);
$originals = json_decode(
    file_get_contents(__DIR__ . '/fixtures/news-originals.json'),
    true,
    512,
    JSON_THROW_ON_ERROR,
);
foreach ($originals as $name => $original) {
    newsCheck(
        architectureBodyHash($original['body']) === $original['original_body_sha256'] ||
            architectureLegacyFixture('news'),
        'Original evidence changed: ' . $name,
    );
}
// Compare copied parser/transport/schema algorithms after restoring only injected dependency names.
$contracts = json_decode(
    file_get_contents(dirname(__DIR__) . '/context/news-extractions.json'),
    true,
    512,
    JSON_THROW_ON_ERROR,
);
$names = [];
foreach ($contracts as $name => $contract) {
    $names[$contract['class'] . '::' . $contract['method']] = $name;
}
$comparisons = [];
foreach ($contracts as $name => $contract) {
    if ($name === 'ygo_news_url') {
        continue;
    } // Host lists are injected now; golden and policy tests cover this change.
    $method = new ReflectionMethod($contract['class'], $contract['method']);
    $source = implode(
        '',
        array_slice(
            file($method->getFileName()),
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1,
        ),
    );
    $body = substr($source, strpos($source, '{'));
    $body = str_replace(
        ['$this->dom(', '$this->urls->normalize('],
        ['ygo_news_dom(', 'ygo_news_url('],
        $body,
    );
    $body = preg_replace(
        '~\(new \\\\?StationX\\\\Yugioh\\\\Infrastructure\\\\Configuration\\\\News\\\\NativeNewsProviders\(\)\)->all\(\)~',
        'ygo_news_sources()',
        $body,
    );
    $comparisons[] = [
        'name' => $name,
        'original' => $originals[$name]['signature'] . $originals[$name]['body'],
        'current' => $originals[$name]['signature'] . architectureRestoreBody($body, $names),
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
    throw new RuntimeException('Cannot start native algorithm comparison');
}
fwrite($pipes[0], json_encode($comparisons, JSON_THROW_ON_ERROR));
fclose($pipes[0]);
newsCheck(proc_close($process) === 0, 'Original parser/transport/schema algorithms changed');

$policy = new NewsUrls(['example.test'], ['images.example.test']);
newsCheck(
    $policy->normalize('/a', 'https://example.test/') === 'https://example.test/a',
    'Injected source hosts',
);
newsCheck(
    $policy->normalize('https://ygoprodeck.com/a', '') === '',
    'No hidden provider catalog in domain',
);
newsCheck(
    $policy->normalize('https://images.example.test/a', '', true) !== '',
    'Injected image hosts',
);
$providers = new NativeNewsProviders();
foreach (
    [
        ['source' => 'wrong'],
        ['format' => 'wrong'],
        ['topic' => 'wrong'],
        ['view' => 'wrong'],
        ['days' => 3],
    ]
    as $input
) {
    newsThrows(
        fn() => NewsFilter::fromArray($input, $providers->all()),
        InvalidArgumentException::class,
    );
}

/** No production hosts: a bounded fixture transport records requests and can fail deterministically. */
final class NewsFixtureFetcher implements NewsFetcher
{
    public array $requests = [];
    public string $body = '[]';
    public bool $fail = false;
    public function fetch(string $url, int $max = 3500000): string
    {
        $this->requests[] = [$url, $max];
        if ($this->fail) {
            throw new RuntimeException('Fixture provider unavailable');
        }
        return $this->body;
    }
}
/** A lock/source probe records transaction timing without MySQL or an external provider. */
final class NewsSyncProbe implements NewsSyncLock, NewsSourceState
{
    public array $trace = [];
    public bool $available = true;
    public bool $due = true;
    public bool $failSuccess = false;
    public function __construct(private PDO $pdo) {}
    public function acquire(): bool
    {
        $this->trace[] = 'acquire';
        return $this->available;
    }
    public function release(): void
    {
        $this->trace[] = 'release';
    }
    public function nextDue(bool $retryFailed): ?array
    {
        $this->trace[] = ['due', $retryFailed];
        return $this->due ? ['source_key' => 'yugiohmeta'] : null;
    }
    public function attempted(string $source): void
    {
        $this->trace[] = ['attempt', $this->pdo->inTransaction()];
    }
    public function succeeded(string $source, int $imported): void
    {
        $this->trace[] = ['success', $this->pdo->inTransaction(), $imported];
        if ($this->failSuccess) {
            throw new PDOException('private storage details');
        }
    }
    public function failed(string $source, string $message): void
    {
        $this->trace[] = ['failure', $this->pdo->inTransaction(), $message];
    }
}
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->sqliteCreateFunction('UTC_TIMESTAMP', static fn() => '2026-11-18 12:00:00');
$fetch = new NewsFixtureFetcher();
NewsModule::create($pdo, $fetch);
newsCheck(
    (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table'")->fetchColumn() ===
        0 &&
        !$pdo->inTransaction() &&
        !$fetch->requests,
    'Composition must not initialize persistence or fetch',
);
$pdo->exec(
    'CREATE TABLE sx_ygo_news_items (id INTEGER PRIMARY KEY AUTOINCREMENT,source_key TEXT,url_hash TEXT UNIQUE,url TEXT,title TEXT,excerpt TEXT,image_url TEXT,published_at TEXT,date_precision TEXT,format_code TEXT,topic TEXT,content_hash TEXT,first_seen_at TEXT,updated_at TEXT,read_at TEXT,bookmarked INTEGER NOT NULL DEFAULT 0)',
);
$pdo->exec('CREATE TABLE sx_ygo_decks (id INTEGER PRIMARY KEY,name TEXT,active INTEGER)');
$pdo->exec("INSERT INTO sx_ygo_decks VALUES(665,'Kashtira Pure',1),(999,'Inactive',0)");
$pdo->exec('CREATE TABLE sx_ygo_news_sources (source_key TEXT PRIMARY KEY)');
$pdo->exec("INSERT INTO sx_ygo_news_sources VALUES('yugiohmeta')");
$items = new PdoNewsItems($pdo);
$parser = NewsModule::parser();
$normalizer = new NormalizeNewsItem(
    new NativePublicationDates(),
    $providers,
    NewsModule::urlPolicy(),
);
$collector = new CollectNewsItems(
    new DiscoverNews($fetch, $parser, $providers),
    $normalizer,
    $items,
    $fetch,
    $parser,
);
// Three valid listing rows; failed article enrichment must still consume the two-attempt budget.
$listingDocuments = new class implements \StationX\Yugioh\Application\News\NewsDocuments {
    public function parseOrg(string $xml): array
    {
        return [['url' => 'https://ygorganization.com/news', 'title' => 'Organization fixture']];
    }
    public function orgImages(string $html): array
    {
        throw new RuntimeException('Optional thumbnails unavailable');
    }
    public function parseMeta(string $json): array
    {
        return [];
    }
    public function parseListing(string $source, string $html): array
    {
        return array_map(
            static fn(int $i) => [
                'url' => 'https://ygoprodeck.com/article/fixture-' . $i,
                'title' => 'Fixture article ' . $i,
            ],
            [1, 2, 3],
        );
    }
    public function articleMetadata(string $html): array
    {
        throw new RuntimeException('Optional enrichment unavailable');
    }
};
$listingFetch = new NewsFixtureFetcher();
$listingCollector = new CollectNewsItems(
    new DiscoverNews($listingFetch, $listingDocuments, $providers),
    $normalizer,
    $items,
    $listingFetch,
    $listingDocuments,
);
newsCheck(
    count($listingCollector->execute('ygoprodeck')) === 3 && count($listingFetch->requests) === 3,
    'Two failed enrichments exhaust budget while retaining all listing items',
);
newsCheck(
    count(
        (new DiscoverNews($listingFetch, $listingDocuments, $providers))->execute('ygorganization'),
    ) === 1,
    'Optional thumbnails cannot discard valid RSS articles',
);

$probe = new NewsSyncProbe($pdo);
$sync = new SynchronizeNews(
    $probe,
    $probe,
    $collector,
    $items,
    $items,
    new PdoTransactionBoundary($pdo, false),
    new NativeNewsFailureMessage(),
);
$probe->available = false;
newsCheck(
    $sync->execute() === ['busy' => true, 'checked' => false] && $probe->trace === ['acquire'],
    'Busy lock does not release another owner',
);
$probe->available = true;
$probe->due = false;
$probe->trace = [];
newsCheck(
    $sync->execute() === ['checked' => false, 'complete' => true] &&
        end($probe->trace) === 'release',
    'No due provider releases lock',
);
$probe->due = true;
$probe->trace = [];
$fetch->fail = true;
$answer = $sync->execute(true);
newsCheck(
    $answer['error'] === 'Fixture provider unavailable' &&
        $probe->trace[1] === ['due', true] &&
        $probe->trace[2] === ['attempt', false] &&
        end($probe->trace) === 'release',
    'Provider failure/backoff order',
);
$fetch->fail = false;
newsCheck(
    isset($sync->execute()['error']) &&
        (int) $pdo->query('SELECT COUNT(*) FROM sx_ygo_news_items')->fetchColumn() === 0,
    'Empty response preserves history',
);
// Use an original captured valid provider payload, rather than inventing a parallel feed format.
foreach (
    json_decode(file_get_contents(__DIR__ . '/fixtures/news-characterization.json'), true)
    as $case
) {
    if ($case['function'] === 'ygo_news_parse_meta' && $case['result']) {
        $fetch->body = $case['input'][0];
        break;
    }
}
$answer = $sync->execute();
newsCheck(
    ($answer['imported'] ?? 0) > 0 && !$pdo->inTransaction(),
    'Valid provider import commits',
);
newsCheck(
    in_array(['success', true, $answer['imported']], $probe->trace, true),
    'Source success shares import transaction',
);
$row = $pdo->query('SELECT * FROM sx_ygo_news_items ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$reading = new UpdateNewsReading(new PdoNewsReaderActions($pdo));
$reading->execute('bookmark', ['id' => $row['id']]);
$reading->execute('read', ['id' => $row['id']]);
newsCheck($sync->execute()['imported'] === 0, 'Identical import is idempotent');
$after = $pdo
    ->query('SELECT * FROM sx_ygo_news_items ORDER BY id LIMIT 1')
    ->fetch(PDO::FETCH_ASSOC);
newsCheck(
    $after['read_at'] !== null &&
        (int) $after['bookmarked'] === 1 &&
        $after['first_seen_at'] === $row['first_seen_at'],
    'Sync preserves operator state and first seen',
);
$merged = NewsContent::merge(
    ['excerpt' => '', 'image_url' => '', 'published_at' => null, 'date_precision' => 'unknown'],
    $after,
);
newsCheck(
    $merged['excerpt'] === $after['excerpt'] && $merged['published_at'] === $after['published_at'],
    'Missing metadata retained',
);
$changed = $after;
$changed['title'] = 'Kashtira updated article';
$items->update((int) $row['id'], $changed, NewsContent::hash($changed));
$updated = $pdo
    ->query('SELECT * FROM sx_ygo_news_items ORDER BY id LIMIT 1')
    ->fetch(PDO::FETCH_ASSOC);
newsCheck(
    $updated['title'] === $changed['title'] &&
        $updated['read_at'] === $after['read_at'] &&
        $updated['bookmarked'] === $after['bookmarked'],
    'Update changes content only',
);
$probe->failSuccess = true;
$answer = $sync->execute();
newsCheck(
    isset($answer['error']) &&
        !str_contains($answer['error'], 'private storage') &&
        !$pdo->inTransaction(),
    'Storage error is private and transaction closes',
);
newsCheck(
    $pdo->query('SELECT title FROM sx_ygo_news_items ORDER BY id LIMIT 1')->fetchColumn() ===
        $changed['title'],
    'Source failure rolls back earlier article updates',
);
$probe->failSuccess = false;
$pdo->beginTransaction();
newsCheck(
    isset($sync->execute()['error']) && $pdo->inTransaction(),
    'Standalone sync cannot commit or roll back borrowed transaction',
);
$pdo->rollBack();
$reading->execute('read', ['id' => $row['id'], 'value' => false]);
newsCheck(
    $pdo->query('SELECT read_at FROM sx_ygo_news_items ORDER BY id LIMIT 1')->fetchColumn() ===
        null,
    'Unread action',
);
$reading->execute('read_all', []);
newsCheck(
    (int) $pdo
        ->query('SELECT COUNT(*) FROM sx_ygo_news_items WHERE read_at IS NULL')
        ->fetchColumn() === 0,
    'Read all',
);
newsThrows(fn() => $reading->execute('read', ['id' => 0]), InvalidArgumentException::class);
newsThrows(fn() => $reading->execute('delete', []), InvalidArgumentException::class);
$list = new ListNews(new PdoNewsReadStore($pdo), $providers);
$answer = $list->execute(['q' => 'Kashtira', 'page' => 999]);
newsCheck(
    $answer['total'] === 1 &&
        $answer['page'] === 1 &&
        $answer['saved'] === 1 &&
        $answer['unread'] === 0,
    'Search paging and global counters',
);
newsCheck(
    !array_key_exists('image_url', $answer['items'][0]) &&
        $answer['items'][0]['related_decks'][0]['id'] === 665,
    'Projection hides upstream image URL and resolves active decks',
);
newsCheck($list->execute(['q' => "' OR 1=1 --"])['total'] === 0, 'Search is parameterized');

$urls = new class implements NewsImageUrls {
    public string $url = '';
    public function forId(int $id): string
    {
        return $this->url;
    }
};
$cache = new class implements NewsImageCache {
    public int $calls = 0;
    public function resolve(string $url): ?NewsImageFile
    {
        $this->calls++;
        return null;
    }
};
$image = new GetNewsImage($urls, $cache, NewsModule::urlPolicy());
newsCheck(
    $image->execute(0) === null && $cache->calls === 0,
    'Invalid image ID does not call cache',
);
$urls->url = 'https://foreign.invalid/x';
newsCheck($image->execute(1) === null && $cache->calls === 0, 'Unapproved image URL rejected');
$directory = sys_get_temp_dir() . '/ygo-news-' . bin2hex(random_bytes(8));
$thumbnail = new NewsFixtureFetcher();
$thumbnail->body = base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j4WQAAAAASUVORK5CYII=',
);
try {
    $disk = new FilesystemNewsImageCache($directory, $thumbnail);
    $file = $disk->resolve('https://images.ygoprodeck.com/one.png');
    newsCheck(
        $file?->mime === 'image/png' && is_file($file->path),
        'Image MIME and atomic cached file',
    );
    $disk->resolve('https://images.ygoprodeck.com/one.png');
    newsCheck(
        count($thumbnail->requests) === 1 && $thumbnail->requests[0][1] === 2000000,
        'Cache hit avoids network; download byte bound',
    );
    $thumbnail->body = '<html>not an image</html>';
    newsThrows(
        fn() => $disk->resolve('https://images.ygoprodeck.com/bad.png'),
        RuntimeException::class,
    );
    newsCheck(
        !glob($directory . '/*.tmp') && count(glob($directory . '/*.img')) === 1,
        'Failed download leaves no image or temporary payload',
    );
} finally {
    // Delete only this uniquely named, flat fixture directory, never application caches.
    newsCheck(
        str_starts_with($directory, sys_get_temp_dir() . '/ygo-news-'),
        'Fixture cleanup scope',
    );
    foreach (glob($directory . '/*') ?: [] as $path) {
        unlink($path);
    }
    if (is_dir($directory)) {
        rmdir($directory);
    }
}
$services = new NewsServices($list, $sync, $reading, $image);
$logs = [];
$released = 0;
$controller = new NewsController($services, static function (string $message) use (&$logs): void {
    $logs[] = $message;
});
$request = static function (
    string $method,
    string $operation,
    string $body = '',
    array $query = [],
) use (&$released): NewsRequest {
    return new NewsRequest(
        $method,
        $operation,
        $query,
        'fixture-token',
        static fn() => $body,
        static function () use (&$released): void {
            $released++;
        },
    );
};
newsCheck(
    $controller->handle($request('POST', 'read', '{"token":"bad"}'))->status === 422 &&
        $released === 0,
    'Token rejected before release or write',
);
newsCheck(
    $controller->handle($request('POST', 'read', str_repeat('x', 10001)))->status === 422,
    'Request size bound',
);
newsCheck(
    $controller->handle($request('POST', 'read', '{'))->status === 503 && count($logs) === 1,
    'Native malformed JSON error mapping and private log',
);
newsCheck($controller->handle($request('PUT', 'list'))->status === 422, 'Unsupported method');
$response = $controller->handle($request('GET', 'list'));
newsCheck(
    $response->status === 200 &&
        $response->body['ok'] &&
        $response->body['token'] === 'fixture-token' &&
        $released === 1,
    'List response/token and session release',
);
$response = $controller->handle(
    $request(
        'POST',
        'bookmark',
        json_encode(['token' => 'fixture-token', 'id' => $row['id'], 'value' => false]),
    ),
);
newsCheck(
    $response->status === 200 && $response->body['value'] === false && $released === 2,
    'Authenticated reader action',
);
newsCheck(
    $controller->handle($request('GET', 'image', '', ['id' => 0]))->status === 404,
    'Missing image returns empty 404',
);
$response = $controller->failure(new PDOException('password fixture private'));
newsCheck(
    $response->status === 503 && !str_contains(json_encode($response->body), 'password'),
    'Internal failures are not disclosed',
);
echo "OK: $checks news architecture checks, including 33 original scenarios; no external providers or production database accessed.\n";
