<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\News;

use PDO;
use StationX\Yugioh\Application\News\NewsImageUrls;

final readonly class PdoNewsImageUrls implements NewsImageUrls
{
    public function __construct(private PDO $pdo) {}
    public function forId(int $id): string
    {
        $q = $this->pdo->prepare('SELECT image_url FROM sx_ygo_news_items WHERE id=?');
        $q->execute([$id]);
        return (string) $q->fetchColumn();
    }
}
