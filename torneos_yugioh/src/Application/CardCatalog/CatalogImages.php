<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\CardCatalog;

/** Original pictures are separate from cached catalog text and never generated descriptions. */
interface CatalogImages
{
    public function hasFull(int $id): bool;
    public function get(int $id, string $size, bool $localOnly): CatalogImage;
}
