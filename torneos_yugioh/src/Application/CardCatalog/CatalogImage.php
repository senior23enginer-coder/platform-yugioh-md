<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\CardCatalog;

/** Opaque outgoing file resource; only the HTTP emitter reads its bytes. */
final readonly class CatalogImage
{
    public function __construct(public string $path) {}
}
