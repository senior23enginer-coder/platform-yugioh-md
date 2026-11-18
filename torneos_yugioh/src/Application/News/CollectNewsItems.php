<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\News;

use Throwable;

/** Normalizes discovered rows and enriches at most two eligible articles, as before. */
final readonly class CollectNewsItems
{
    public function __construct(
        private DiscoverNews $discovery,
        private NormalizeNewsItem $normalize,
        private NewsItemLookup $lookup,
        private NewsFetcher $fetcher,
        private NewsDocuments $documents,
    ) {}

    public function execute(string $source): array
    {
        $rows = $this->discovery->execute($source);
        $items = [];
        $enriched = 0;
        foreach ($rows as $row) {
            $item = $this->normalize->execute($source, $row);
            if (!$item) {
                continue;
            }
            if (in_array($source, ['ygoprodeck', 'masterduelmeta'], true) && $enriched < 2) {
                $old = $this->lookup->enrichmentMetadata(hash('sha256', $item['url']));
                if (!$old || $old['date_precision'] === 'unknown' || !$old['excerpt']) {
                    try {
                        $extra = $this->documents->articleMetadata(
                            $this->fetcher->fetch($item['url']),
                        );
                        foreach ($extra as $field => $value) {
                            if ($value !== '') {
                                $row[$field] = $value;
                            }
                        }
                        $item = $this->normalize->execute($source, $row);
                        $enriched++;
                    } catch (Throwable) {
                        $enriched++;
                    }
                }
            }
            if ($item) {
                $items[] = $item;
            }
        }
        return $items;
    }
}
