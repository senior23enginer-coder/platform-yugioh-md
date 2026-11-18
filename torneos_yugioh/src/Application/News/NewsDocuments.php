<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\News;

/** Provider documents are parsed externally to business rules; no DOM types cross this port. */
interface NewsDocuments
{
    public function parseOrg(string $xml): array;
    public function orgImages(string $html): array;
    public function parseMeta(string $json): array;
    public function parseListing(string $source, string $html): array;
    public function articleMetadata(string $html): array;
}
