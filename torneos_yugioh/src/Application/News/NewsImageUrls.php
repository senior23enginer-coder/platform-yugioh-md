<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\News;

interface NewsImageUrls
{
    public function forId(int $id): string;
}
