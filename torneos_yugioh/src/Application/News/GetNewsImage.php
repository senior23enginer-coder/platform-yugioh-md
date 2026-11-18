<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\News;

use StationX\Yugioh\Domain\News\NewsUrls;

/** Only a catalogued, approved image URL can reach the outgoing cache. */
final readonly class GetNewsImage
{
    public function __construct(
        private NewsImageUrls $urls,
        private NewsImageCache $cache,
        private NewsUrls $policy,
    ) {}
    public function execute(int $id): ?NewsImageFile
    {
        $url = $this->urls->forId($id);
        return !$url || !$this->policy->normalize($url, '', true)
            ? null
            : $this->cache->resolve($url);
    }
}
