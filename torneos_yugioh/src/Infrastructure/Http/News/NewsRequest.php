<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\News;

use Closure;

/** Captures transport/session dependencies; application use cases never read these globals. */
final readonly class NewsRequest
{
    /** @param Closure():string $readBody @param Closure():void $releaseSession */
    public function __construct(
        public string $method,
        public string $operation,
        public array $query,
        public string $token,
        public Closure $readBody,
        public Closure $releaseSession,
    ) {}
}
