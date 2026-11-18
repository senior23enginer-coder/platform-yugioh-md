<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\DeckWorlds;

use Closure;

/** Captured HTTP context. The bounded body is read lazily, after authorization. */
final readonly class DeckWorldsRequest
{
    /** @param Closure():string $readBody */
    public function __construct(
        public string $method,
        public array $query,
        public bool $canEdit,
        public string $expectedToken,
        public string $providedToken,
        public int $contentLength,
        public Closure $readBody,
    ) {}
}
