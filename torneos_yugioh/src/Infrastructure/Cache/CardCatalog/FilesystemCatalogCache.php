<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Cache\CardCatalog;

use Closure;
use RuntimeException;
use StationX\Yugioh\Application\CardCatalog\CatalogCache;

/** Shared cache keys are internal; the public API never receives filesystem paths. */
final readonly class FilesystemCatalogCache implements CatalogCache
{
    public function __construct(private string $path) {}
    public function directory(): string
    {
        if (!is_dir($this->path) && !mkdir($this->path, 0755, true) && !is_dir($this->path)) {
            throw new RuntimeException('No se pudo preparar el catálogo de cartas.');
        }
        return $this->path;
    }
    /** Read-only inspection must not create an absent local cache directory. */
    public function contains(string $key): bool
    {
        return is_file($this->path . '/' . $key);
    }

    public function read(string $key): ?string
    {
        $file = $this->path . '/' . $key;
        return is_file($file) ? (string) file_get_contents($file) : null;
    }

    public function write(string $key, string $body): void
    {
        self::writeFile($this->directory() . '/' . $key, $body);
    }

    public static function writeFile(string $file, string $body): void
    {
        $tmp = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($tmp, $body, LOCK_EX) === false || !rename($tmp, $file)) {
            @unlink($tmp);
            throw new RuntimeException('No se pudo guardar el catálogo local.');
        }
    }

    public function modifiedAt(string $key): ?int
    {
        $file = $this->path . '/' . $key;
        return is_file($file) ? (int) filemtime($file) : null;
    }

    public function exclusively(string $key, Closure $operation): mixed
    {
        $lock = fopen($this->directory() . '/' . $key . '.lock', 'c+');
        if (!$lock || !flock($lock, LOCK_EX)) {
            throw new RuntimeException('No se pudo abrir el catálogo.');
        }
        try {
            return $operation();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
