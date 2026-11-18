<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\News;

/** Opaque cached resource; only the outgoing cache and HTTP adapter access its file. */
final readonly class NewsImageFile
{
    public function __construct(public string $path, public string $mime) {}
}
