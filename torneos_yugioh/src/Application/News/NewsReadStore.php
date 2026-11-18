<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\News;

use StationX\Yugioh\Domain\News\NewsFilter;

interface NewsReadStore
{
    public function count(NewsFilter $filter): int;
    public function items(NewsFilter $filter, int $page): array;
    public function activeDecks(): array;
    public function sourceStates(): array;
    public function sourceCount(string $source): int;
    public function counts(): array;
}
