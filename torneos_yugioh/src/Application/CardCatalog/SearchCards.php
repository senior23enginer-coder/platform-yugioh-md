<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\CardCatalog;

use Throwable;
use RuntimeException;
use StationX\Yugioh\Domain\CardCatalog\CardSearch;
use StationX\Yugioh\Application\Shared\Clock;

/** Native 48-hour search cache, stale-on-download-error policy and twenty-card paging. */
final readonly class SearchCards
{
    public function __construct(
        private CatalogCache $cache,
        private CatalogProvider $provider,
        private Clock $clock,
    ) {}
    public function execute(string $query, string $format, int $page): array
    {
        $search = new CardSearch($query, $format, $page);
        $key = $search->cacheKey();
        return $this->cache->exclusively($key, function () use ($search, $key): array {
            $modified = $this->cache->modifiedAt($key);
            if ($modified !== null && $modified > $this->clock->now()->getTimestamp() - 172800) {
                return json_decode($this->cache->read($key), true, 32, JSON_THROW_ON_ERROR);
            }
            try {
                $remote = json_decode(
                    $this->provider->download(
                        'https://db.ygoprodeck.com/api/v7/cardinfo.php?' .
                            http_build_query($search->parameters),
                    ),
                    true,
                    32,
                    JSON_THROW_ON_ERROR,
                );
            } catch (Throwable $error) {
                $body = $this->cache->read($key);
                if ($body !== null) {
                    $cached = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
                    $cached['stale'] = true;
                    return $cached;
                }
                throw $error;
            }
            if (
                !isset($remote['data']) &&
                !str_contains(strtolower((string) ($remote['error'] ?? '')), 'no card matching')
            ) {
                throw new RuntimeException('No se pudo completar esta búsqueda.');
            }
            $rows = array_map(CardSummary::fromProvider(...), $remote['data'] ?? []);
            foreach ($rows as $card) {
                $this->cache->write(
                    'card-' . $card['id'] . '.json',
                    json_encode($card, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                );
            }
            // Provider total_pages counts offsets; display pages derive only from total_rows.
            $total = (int) ($remote['meta']['total_rows'] ?? count($rows));
            $result = [
                'ok' => true,
                'cards' => $rows,
                'page' => $search->page,
                'page_size' => 20,
                'total_rows' => $total,
                'total_pages' => (int) ceil($total / 20),
                'has_more' => ($search->page + 1) * 20 < $total,
                'search_language' => 'en',
                'spanish_supported' => false,
            ];
            $this->cache->write(
                $key,
                json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            );
            return $result;
        });
    }
}
