<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Cache\News;

use RuntimeException;
use StationX\Yugioh\Application\News\NewsImageCache;
use StationX\Yugioh\Application\News\NewsImageFile;
use StationX\Yugioh\Application\News\NewsFetcher;

/** Native thumbnail cache: per-image lock, bounded download, MIME check and atomic rename. */
final readonly class FilesystemNewsImageCache implements NewsImageCache
{
    public function __construct(private string $directory, private NewsFetcher $fetcher) {}
    public function resolve(string $url): ?NewsImageFile
    {
        $dir = $this->directory;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('No se pudo preparar la miniatura.');
        }
        $path = $dir . '/' . hash('sha256', $url) . '.img';
        if (!is_file($path)) {
            $lock = fopen($path . '.lock', 'c+');
            if (!$lock || !flock($lock, LOCK_EX)) {
                throw new RuntimeException('Miniatura ocupada.');
            }
            try {
                if (!is_file($path)) {
                    $body = $this->fetcher->fetch($url, 2000000);
                    $type = @getimagesizefromstring($body)['mime'] ?? '';
                    if (
                        !in_array(
                            $type,
                            ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                            true,
                        )
                    ) {
                        throw new RuntimeException('Miniatura no disponible.');
                    }
                    $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
                    if (file_put_contents($tmp, $body, LOCK_EX) === false || !rename($tmp, $path)) {
                        @unlink($tmp);
                        throw new RuntimeException('No se pudo guardar la miniatura.');
                    }
                }
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
        $type = @getimagesize($path)['mime'] ?? '';
        return $type ? new NewsImageFile($path, $type) : null;
    }
}
