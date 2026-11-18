<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Network\CardCatalog;

use RuntimeException;
use StationX\Yugioh\Application\CardCatalog\CatalogProvider;
use StationX\Yugioh\Infrastructure\Cache\CardCatalog\FilesystemCatalogCache;

/** Preserves the shared provider throttle, byte limit, accepted statuses and strict TLS. */
final readonly class ThrottledCatalogProvider implements CatalogProvider
{
    public function __construct(private FilesystemCatalogCache $cache) {}
    public function download(string $url, int $max = 4000000): string
    {
        $dir = $this->cache->directory();
        $lock = fopen($dir . '/upstream.lock', 'c+');
        if (!$lock || !flock($lock, LOCK_EX)) {
            throw new RuntimeException('El catálogo está ocupado. Inténtalo de nuevo.');
        }
        try {
            $last = (float) stream_get_contents($lock);
            $delay = 0.25 - (microtime(true) - $last);
            if ($delay > 0) {
                usleep((int) ($delay * 1000000));
            }
            $body = '';
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_USERAGENT => 'StationX-DeckBuilder/1.0',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_WRITEFUNCTION => static function ($c, $chunk) use (&$body, $max) {
                    if (strlen($body) + strlen($chunk) > $max) {
                        return 0;
                    }
                    $body .= $chunk;
                    return strlen($chunk);
                },
            ]);
            $ok = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            rewind($lock);
            ftruncate($lock, 0);
            fwrite($lock, (string) microtime(true));
            if ($ok === false || !in_array($status, [200, 400], true)) {
                throw new RuntimeException(
                    'YGOPRODeck no está disponible en este momento. Inténtalo de nuevo.',
                );
            }
            return $body;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
