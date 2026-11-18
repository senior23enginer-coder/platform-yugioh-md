<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\News;

interface NewsItemLookup
{
    public function enrichmentMetadata(string $urlHash): ?array;
    public function find(string $urlHash): ?array;
}
