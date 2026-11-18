<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Cache\CardCatalog;

use InvalidArgumentException;
use RuntimeException;
use StationX\Yugioh\Application\CardCatalog\{CatalogImages, CatalogImage, CatalogProvider};

/** Local originals and catalogued-only remote images, with one lock per picture. */
final readonly class FilesystemCatalogImages implements CatalogImages
{
    public function __construct(
        private FilesystemCatalogCache $cache,
        private CatalogProvider $provider,
    ) {}
    public function hasFull(int $id): bool
    {
        return $this->cache->contains('image-full-' . $id . '.jpg');
    }

    public function get(int $id, string $size, bool $localOnly): CatalogImage
    {
        $dir = $this->cache->directory();
        $file = $dir . '/image-' . ($size === 'full' ? 'full-' : '') . $id . '.jpg';
        if (!is_file($file)) {
            if ($localOnly) {
                throw new InvalidArgumentException(
                    'La imagen original no está guardada localmente.',
                );
            }
            // Only download images for catalogued cards; never accept a client URL.
            if (!is_file($dir . '/card-' . $id . '.json')) {
                throw new InvalidArgumentException('Carta no catalogada.');
            }
            $lock = fopen($file . '.lock', 'c+');
            if (!$lock || !flock($lock, LOCK_EX)) {
                throw new RuntimeException('Imagen ocupada.');
            }
            try {
                if (!is_file($file)) {
                    $image = $this->provider->download(
                        'https://images.ygoprodeck.com/images/' .
                            ($size === 'full' ? 'cards' : 'cards_small') .
                            '/' .
                            $id .
                            '.jpg',
                        1500000,
                    );
                    if (@getimagesizefromstring($image)['mime'] !== 'image/jpeg') {
                        throw new RuntimeException('Imagen no disponible.');
                    }
                    FilesystemCatalogCache::writeFile($file, $image);
                }
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
        return new CatalogImage($file);
    }
}
