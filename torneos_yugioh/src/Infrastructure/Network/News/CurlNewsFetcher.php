<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Network\News;

use RuntimeException;
use StationX\Yugioh\Application\News\NewsFetcher;

/** Native provider transport: strict TLS, no redirects, existing timeouts and bounded decompressed bytes. */
final class CurlNewsFetcher implements NewsFetcher
{
    public function __construct(private readonly \StationX\Yugioh\Domain\News\NewsUrls $urls) {}

    public function fetch(string $url, int $max = 3500000): string
    {
        if (!$this->urls->normalize($url, '', true)) {
            throw new RuntimeException('Dirección de la fuente no admitida.');
        }
        $body = '';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_USERAGENT => 'StationX-News/1.0 (+https://stationxtulua.com/yugioh/)',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_ENCODING => '',
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
        if ($ok === false || $status !== 200) {
            throw new RuntimeException(
                'La fuente no respondió correctamente' .
                    ($status ? ' (HTTP ' . $status . ')' : '') .
                    '.',
            );
        }
        return $body;
    }
}
