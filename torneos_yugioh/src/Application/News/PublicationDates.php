<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\News;

interface PublicationDates
{
    /** @return array{0:?string,1:string} UTC/date-only/unknown; missing evidence is never invented. */
    public function parse(string $value): array;
}
