<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\CardCatalog;

use Closure;

/** Atomic entries and exclusive per-search lock. Construction never touches the filesystem. */
interface CatalogCache
{
    public function read(string $key): ?string;
    public function write(string $key, string $body): void;
    public function modifiedAt(string $key): ?int;
    /** @template T @param Closure():T $operation @return T */
    public function exclusively(string $key, Closure $operation): mixed;
}
