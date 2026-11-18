<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\News;

use Throwable;

/** Selects the native provider route and tolerates missing optional Organization thumbnails. */
final readonly class DiscoverNews
{
    public function __construct(
        private NewsFetcher $fetcher,
        private NewsDocuments $documents,
        private NewsProviders $providers,
    ) {}

    public function execute(string $source): array
    {
        $base = $this->providers->all()[$source]['url'];
        if ($source === 'ygorganization') {
            $rows = $this->documents->parseOrg($this->fetcher->fetch($base . 'feed'));
            try {
                $images = $this->documents->orgImages($this->fetcher->fetch($base));
                foreach ($rows as &$row) {
                    $row['image'] = $images[rtrim($row['url'], '/')] ?? ($row['image'] ?? '');
                }
                unset($row);
            } catch (Throwable) {
            }
            return $rows;
        }
        if ($source === 'yugiohmeta') {
            return $this->documents->parseMeta(
                $this->fetcher->fetch(
                    $base .
                        'api/v1/articles?hidden%5B%24ne%5D=true&limit=20&sort%5Bdate%5D=-1&fields%5Bmarkdown%5D=0',
                ),
            );
        }
        return $this->documents->parseListing($source, $this->fetcher->fetch($base));
    }
}
