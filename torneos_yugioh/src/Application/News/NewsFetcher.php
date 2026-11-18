<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\News;

/** Fetches an approved response with a hard byte limit. */
interface NewsFetcher
{
    public function fetch(string $url, int $max = 3500000): string;
}
