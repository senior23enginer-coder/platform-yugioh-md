<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\News;

/** Item writes and source success metadata share the import transaction. */
interface NewsItemWriter
{
    public function insert(string $source, string $urlHash, array $item, string $contentHash): void;
    public function update(int $id, array $item, string $contentHash): void;
}
