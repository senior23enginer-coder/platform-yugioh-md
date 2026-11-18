<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\News;

/** Approved HTTPS provider/image hosts; redirects and arbitrary targets are not introduced. */
final readonly class NewsUrls
{
    public function __construct(private array $sourceHosts, private array $imageHosts) {}

    public function normalize(string $value, string $base, bool $image = false): string
    {
        $value = html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (str_starts_with($value, '//')) {
            $value = 'https:' . $value;
        } elseif (str_starts_with($value, '/')) {
            $value = rtrim($base, '/') . $value;
        }
        $host = strtolower((string) parse_url($value, PHP_URL_HOST));
        $hosts = $image ? $this->imageHosts : $this->sourceHosts;
        if (
            parse_url($value, PHP_URL_SCHEME) !== 'https' ||
            !in_array($host, $hosts, true) ||
            parse_url($value, PHP_URL_USER) !== null ||
            parse_url($value, PHP_URL_PORT) !== null
        ) {
            return '';
        }
        return strtok($value, '#') ?: '';
    }
}
