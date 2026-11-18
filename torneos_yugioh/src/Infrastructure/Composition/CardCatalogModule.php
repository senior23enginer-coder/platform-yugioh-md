<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use StationX\Yugioh\Application\CardCatalog\{
    CatalogCache,
    CatalogProvider,
    CatalogImages,
    CatalogServices,
    LookupCard,
    InspectCachedCard,
    SearchCards,
    GetCardImage,
};
use StationX\Yugioh\Application\Shared\Clock;
use StationX\Yugioh\Infrastructure\Cache\CardCatalog\{
    FilesystemCatalogCache,
    FilesystemCatalogImages,
};
use StationX\Yugioh\Infrastructure\Network\CardCatalog\ThrottledCatalogProvider;
use StationX\Yugioh\Infrastructure\Time\SystemClock;

/** Lazy composition; no cache directory, network call or schema is initialized in constructors. */
final class CardCatalogModule
{
    public static function cache(?string $directory = null): FilesystemCatalogCache
    {
        return new FilesystemCatalogCache(
            $directory ?? dirname(__DIR__, 3) . '/assets/cache/ygoprodeck',
        );
    }
    public static function provider(): CatalogProvider
    {
        return new ThrottledCatalogProvider(self::cache());
    }
    public static function create(
        ?CatalogCache $cache = null,
        ?CatalogProvider $provider = null,
        ?CatalogImages $images = null,
        ?Clock $clock = null,
    ): CatalogServices {
        $native = self::cache();
        $cache ??= $native;
        $provider ??= new ThrottledCatalogProvider($native);
        $images ??= new FilesystemCatalogImages($native, $provider);
        $clock ??= new SystemClock();
        return new CatalogServices(
            new LookupCard($cache, $provider),
            new InspectCachedCard($cache, $images),
            new SearchCards($cache, $provider, $clock),
            new GetCardImage($images),
        );
    }
}
