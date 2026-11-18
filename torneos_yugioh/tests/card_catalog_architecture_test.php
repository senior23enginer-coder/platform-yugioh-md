<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/deck_builder.php';
require dirname(__DIR__, 2) . '/scripts/php-source-contract.php';

use StationX\Yugioh\Application\CardCatalog\{
    CatalogCache,
    CatalogProvider,
    CatalogImages,
    CatalogImage,
    LookupCard,
    InspectCachedCard,
    SearchCards,
    GetCardImage,
};
use StationX\Yugioh\Application\Shared\Clock;
use StationX\Yugioh\Domain\CardCatalog\CardSearch;
use StationX\Yugioh\Infrastructure\Composition\CardCatalogModule;
use StationX\Yugioh\Infrastructure\Cache\CardCatalog\{
    FilesystemCatalogCache,
    FilesystemCatalogImages,
};
use StationX\Yugioh\Infrastructure\Http\CardCatalog\CatalogController;

$checks = 0;
function catalogCheck(bool $condition, string $why): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($why);
    }
    $checks++;
}
function catalogThrows(callable $operation, string $type): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        catalogCheck($error instanceof $type, 'Unexpected error ' . $error::class);
        return;
    }
    throw new RuntimeException('Expected ' . $type);
}
foreach (
    json_decode(file_get_contents(__DIR__ . '/fixtures/card-catalog-characterization.json'), true)
    as $case
) {
    $actual = null;
    $error = null;
    try {
        $actual = $case['function'](...$case['input']);
    } catch (Throwable $e) {
        $error = ['class' => $e::class, 'message' => $e->getMessage()];
    }
    catalogCheck(
        $actual === $case['result'] && $error === $case['error'],
        'Original scenario changed: ' . $case['function'],
    );
}
$originals = json_decode(
    file_get_contents(__DIR__ . '/fixtures/card-catalog-originals.json'),
    true,
);
foreach ($originals as $name => $original) {
    catalogCheck(
        architectureBodyHash($original['body']) === $original['original_body_sha256'],
        'Original evidence changed: ' . $name,
    );
}
$contracts = [
    'ygo_card_summary' => [
        \StationX\Yugioh\Application\CardCatalog\CardSummary::class,
        'fromProvider',
    ],
    'ygo_card_download' => [
        \StationX\Yugioh\Infrastructure\Network\CardCatalog\ThrottledCatalogProvider::class,
        'download',
    ],
    'ygo_card_cache_write' => [FilesystemCatalogCache::class, 'writeFile'],
];
$comparisons = [];
foreach ($contracts as $name => [$class, $method]) {
    $reflection = new ReflectionMethod($class, $method);
    $source = implode(
        '',
        array_slice(
            file($reflection->getFileName()),
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1,
        ),
    );
    $body = str_replace(
        '$this->cache->directory()',
        'ygo_card_cache_dir()',
        substr($source, strpos($source, '{')),
    );
    $comparisons[] = [
        'name' => $name,
        'original' => $originals[$name]['signature'] . $originals[$name]['body'],
        'current' => $originals[$name]['signature'] . $body,
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
    throw new RuntimeException('Cannot start catalog algorithm comparison');
}
fwrite($pipes[0], json_encode($comparisons, JSON_THROW_ON_ERROR));
fclose($pipes[0]);
catalogCheck(proc_close($process) === 0, 'Original transport/cache/summary comparison');

/** Deterministic cache/provider/image/time ports; no native cache or external requests. */
final class CatalogProbe implements CatalogCache, CatalogProvider, CatalogImages, Clock
{
    public array $entries = [];
    public array $dates = [];
    public array $requests = [];
    public array $writes = [];
    public bool $locked = false;
    public bool $fail = false;
    public bool $full = false;
    public string $remote = '{}';
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-18T12:00:00Z');
    }
    public function read(string $key): ?string
    {
        return $this->entries[$key] ?? null;
    }
    public function write(string $key, string $body): void
    {
        $this->entries[$key] = $body;
        $this->dates[$key] = $this->now()->getTimestamp();
        $this->writes[] = $key;
    }
    public function modifiedAt(string $key): ?int
    {
        return $this->dates[$key] ?? null;
    }
    public function exclusively(string $key, Closure $operation): mixed
    {
        catalogCheck(!$this->locked, 'Unexpected nested lock');
        $this->locked = true;
        try {
            return $operation();
        } finally {
            $this->locked = false;
        }
    }
    public function download(string $url, int $max = 4000000): string
    {
        $this->requests[] = [$url, $max, $this->locked];
        if ($this->fail) {
            throw new RuntimeException('Fixture upstream failure');
        }
        return $this->remote;
    }
    public function hasFull(int $id): bool
    {
        return $this->full;
    }
    public function get(int $id, string $size, bool $localOnly): CatalogImage
    {
        return new CatalogImage('fixture-' . $size . '-' . $id);
    }
}
$probe = new CatalogProbe();
$services = CardCatalogModule::create($probe, $probe, $probe, $probe);
catalogCheck(!$probe->requests && !$probe->writes, 'Composition has no I/O');
$card = [
    'id' => 123,
    'name' => 'Fixture card',
    'desc' => 'Exact original effect.',
    'type' => 'Effect Monster',
    'atk' => 0,
];
$probe->remote = json_encode([
    'data' => [$card],
    'meta' => ['total_rows' => 21, 'total_pages' => 999],
]);
$search = new CardSearch('  Fixture  ', 'tcg', 1);
catalogCheck(
    $search->parameters === [
        'num' => 20,
        'offset' => 20,
        'sort' => 'name',
        'fname' => 'Fixture',
        'format' => 'tcg',
    ],
    'Search trim/filter/offset',
);
catalogCheck(
    (new CardSearch('00123', 'all', 0))->parameters['id'] === '00123',
    'Numeric search keeps original ID text',
);
$result = $services->search->execute('Fixture', 'tcg', 0);
catalogCheck(
    $result['total_pages'] === 2 &&
        $result['has_more'] &&
        $result['search_language'] === 'en' &&
        !$result['spanish_supported'],
    'Paging is based on total_rows; language honest',
);
catalogCheck(
    $result['cards'][0]['description'] === $card['desc'] && $result['cards'][0]['atk'] === 0,
    'Exact effect and zero ATK preserved',
);
catalogCheck(
    count($probe->requests) === 1 && $probe->requests[0][2] && !$probe->locked,
    'Search lock wraps provider work and is released',
);
catalogCheck(
    isset($probe->entries['card-123.json']) && count($probe->writes) === 2,
    'Search caches identity and paginated response',
);
$services->search->execute('Fixture', 'tcg', 0);
catalogCheck(count($probe->requests) === 1, 'Fresh search avoids provider');
$key = (new CardSearch('Fixture', 'tcg', 0))->cacheKey();
$probe->dates[$key] = $probe->now()->getTimestamp() - 172800;
$probe->fail = true;
$result = $services->search->execute('Fixture', 'tcg', 0);
catalogCheck(
    $result['stale'] === true && count($probe->requests) === 2 && !$probe->locked,
    'Expired search fallback includes stale flag and releases lock',
);
catalogThrows(fn() => $services->search->execute('Uncached', 'all', 0), RuntimeException::class);
catalogCheck(!$probe->locked, 'Uncached failure releases lock');
$probe->fail = false;
$probe->remote = '{';
catalogCheck(
    $services->search->execute('Fixture', 'tcg', 0)['stale'] === true,
    'Malformed provider JSON uses existing stale cache',
);
$probe->remote = '{"error":"unexpected upstream format"}';
catalogThrows(fn() => $services->search->execute('Fixture', 'tcg', 0), RuntimeException::class);
$probe->remote = '{"error":"No card matching your query was found"}';
$result = $services->search->execute('Empty', 'all', 0);
catalogCheck(
    $result['cards'] === [] && $result['total_pages'] === 0 && !$result['has_more'],
    'No-match provider answer is a successful empty search',
);
$before = count($probe->requests);
catalogCheck(
    $services->lookup->execute(123)['description'] === $card['desc'] &&
        count($probe->requests) === $before,
    'Lookup cache avoids provider',
);
$probe->remote = json_encode([
    'data' => [['id' => 456, 'name' => 'Second', 'desc' => 'Second exact effect']],
]);
catalogCheck(
    $services->lookup->execute(456)['id'] === 456,
    'Lookup imports exact requested identity',
);
catalogThrows(fn() => $services->lookup->execute(457), InvalidArgumentException::class);
catalogCheck(!isset($probe->entries['card-457.json']), 'Wrong provider identity never cached');
$before = count($probe->requests);
catalogCheck(
    $services->inspect->execute(789) === null && count($probe->requests) === $before,
    'Local missing text does not fetch',
);
catalogCheck(
    $services->inspect->execute(123)['image'] === null,
    'No full local picture yields no fake image',
);
$probe->full = true;
catalogCheck(
    str_contains($services->inspect->execute(123)['image'], 'local=1') &&
        $services->inspect->execute(123)['text_source'] === 'YGOPRODeck',
    'Local original image and text attribution',
);
$probe->entries['card-999.json'] = '{"id":1}';
catalogThrows(fn() => $services->inspect->execute(999), RuntimeException::class);
catalogThrows(fn() => $services->image->execute(0, 'full', false), InvalidArgumentException::class);
catalogThrows(
    fn() => $services->image->execute(123, 'huge', false),
    InvalidArgumentException::class,
);
$http = new CatalogController($services);
catalogCheck($http->handle('POST', [])->status === 422, 'Only GET permitted');
catalogCheck(
    $http->handle('GET', ['op' => 'detail', 'id' => '1e2'])->status === 422,
    'HTTP integer identity validation',
);
catalogCheck(
    $http->handle('GET', ['op' => 'detail', 'id' => 123])->body['card']['text_language'] === 'en',
    'Details default to local',
);
$before = count($probe->requests);
$response = $http->handle('GET', ['op' => 'detail', 'id' => 123, 'local' => '0']);
catalogCheck(
    $response->status === 200 &&
        !str_contains($response->body['card']['image'], 'local=1') &&
        count($probe->requests) === $before,
    'Remote detail keeps exact cached text and full remote image route',
);
catalogCheck(
    $http->handle('GET', ['op' => 'image', 'id' => 123, 'size' => 'full'])->image->path ===
        'fixture-full-123',
    'HTTP image mapping',
);
catalogCheck($http->handle('GET', ['q' => 'a'])->status === 422, 'Invalid search remains 422');
$probe->fail = true;
catalogCheck(
    $http->handle('GET', ['op' => 'detail', 'id' => 890, 'local' => '0'])->status === 503,
    'Upstream failure remains 503',
);

$directory = sys_get_temp_dir() . '/ygo-catalog-' . bin2hex(random_bytes(8));
$disk = new FilesystemCatalogCache($directory);
$images = new FilesystemCatalogImages($disk, $probe);
try {
    $local = new InspectCachedCard($disk, $images);
    catalogCheck(
        $local->execute(123) === null && !is_dir($directory),
        'Local inspection and composition do not create directories',
    );
    $disk->write('card-123.json', json_encode(ygo_card_summary($card)));
    catalogCheck(
        $disk->read('card-123.json') !== null && $disk->modifiedAt('card-123.json') !== null,
        'Atomic filesystem cache read/write/date',
    );
    $before = count($probe->requests);
    catalogThrows(fn() => $images->get(123, 'full', true), InvalidArgumentException::class);
    catalogThrows(fn() => $images->get(999, 'full', false), InvalidArgumentException::class);
    catalogCheck(
        count($probe->requests) === $before,
        'Local-only and uncatalogued pictures cannot download',
    );
    $probe->fail = false;
    $probe->remote = base64_decode(
        '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD5/ooooA//2Q==',
    );
    $image = $images->get(123, 'full', false);
    catalogCheck(is_file($image->path) && $images->hasFull(123), 'Large original JPEG is stored');
    $last = $probe->requests[array_key_last($probe->requests)];
    catalogCheck(
        $last[0] === 'https://images.ygoprodeck.com/images/cards/123.jpg' && $last[1] === 1500000,
        'Fixed full image endpoint and byte bound',
    );
    $before = count($probe->requests);
    $images->get(123, 'full', false);
    catalogCheck(count($probe->requests) === $before, 'Image cache hit avoids download');
    $probe->remote = '<html>not a JPEG</html>';
    catalogThrows(fn() => $images->get(123, 'small', false), RuntimeException::class);
    catalogCheck(
        !glob($directory . '/*.tmp') && !is_file($directory . '/image-123.jpg'),
        'Failed MIME validation leaves no image or temp file',
    );
    catalogThrows(
        fn() => $disk->exclusively(
            'fixture.json',
            static fn() => throw new RuntimeException('test lock'),
        ),
        RuntimeException::class,
    );
    catalogCheck(
        $disk->exclusively('fixture.json', static fn() => true),
        'Failed operation releases filesystem lock',
    );
    catalogCheck(
        ygo_card_inspector_cached(123, $directory)['description'] === $card['desc'],
        'Native local facade preserves exact text and local image',
    );
} finally {
    catalogCheck(
        str_starts_with($directory, sys_get_temp_dir() . '/ygo-catalog-'),
        'Cleanup stays in unique flat fixture directory',
    );
    foreach (glob($directory . '/*') ?: [] as $file) {
        unlink($file);
    }
    if (is_dir($directory)) {
        rmdir($directory);
    }
}
echo "PASS $checks card catalog architecture checks; ten original scenarios and three original algorithms; no provider or production accessed.\n";
