<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\News;

use PDO;
use StationX\Yugioh\Application\News\NewsItemLookup;
use StationX\Yugioh\Application\News\NewsItemWriter;

/** No commits here: article updates and source success metadata share one import transaction. */
final readonly class PdoNewsItems implements NewsItemLookup, NewsItemWriter
{
    public function __construct(private PDO $pdo) {}
    public function enrichmentMetadata(string $urlHash): ?array
    {
        $q = $this->pdo->prepare(
            'SELECT date_precision,excerpt,image_url FROM sx_ygo_news_items WHERE url_hash=?',
        );
        $q->execute([$urlHash]);
        return $q->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function find(string $urlHash): ?array
    {
        $q = $this->pdo->prepare(
            'SELECT id,content_hash,excerpt,image_url,published_at,date_precision FROM sx_ygo_news_items WHERE url_hash=?',
        );
        $q->execute([$urlHash]);
        return $q->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function insert(string $source, string $urlHash, array $item, string $contentHash): void
    {
        $this->pdo
            ->prepare(
                'INSERT INTO sx_ygo_news_items(source_key,url_hash,url,title,excerpt,image_url,published_at,date_precision,format_code,topic,content_hash,first_seen_at,updated_at,read_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP(),?)',
            )
            ->execute([
                $source,
                $urlHash,
                $item['url'],
                $item['title'],
                $item['excerpt'],
                $item['image_url'],
                $item['published_at'],
                $item['date_precision'],
                $item['format_code'],
                $item['topic'],
                $contentHash,
                null,
            ]);
    }

    public function update(int $id, array $item, string $contentHash): void
    {
        $this->pdo
            ->prepare(
                'UPDATE sx_ygo_news_items SET title=?,excerpt=?,image_url=?,published_at=?,date_precision=?,format_code=?,topic=?,content_hash=?,updated_at=UTC_TIMESTAMP() WHERE id=?',
            )
            ->execute([
                $item['title'],
                $item['excerpt'],
                $item['image_url'],
                $item['published_at'],
                $item['date_precision'],
                $item['format_code'],
                $item['topic'],
                $contentHash,
                $id,
            ]);
    }
}
