<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\CardCatalog;

/** Explicit entry points composed outside application rules. */
final readonly class CatalogServices
{
    public function __construct(
        public LookupCard $lookup,
        public InspectCachedCard $inspect,
        public SearchCards $search,
        public GetCardImage $image,
    ) {}
}
