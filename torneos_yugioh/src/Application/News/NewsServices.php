<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\News;

/** Explicit application entry points, without PDO/HTTP/session dependencies. */
final readonly class NewsServices
{
    public function __construct(
        public ListNews $list,
        public SynchronizeNews $sync,
        public UpdateNewsReading $reading,
        public GetNewsImage $image,
    ) {}
}
