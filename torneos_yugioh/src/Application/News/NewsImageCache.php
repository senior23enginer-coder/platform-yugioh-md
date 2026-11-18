<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\News;

interface NewsImageCache
{
    public function resolve(string $url): ?NewsImageFile;
}
