<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\CardCatalog;

/** Bounded catalog/image transport; endpoint construction stays in the consuming use case/adapter. */
interface CatalogProvider
{
    public function download(string $url, int $max = 4000000): string;
}
