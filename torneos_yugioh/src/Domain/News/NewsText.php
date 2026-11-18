<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\News;

/** Plain-text normalization and truncation without persistence or I/O. */
final class NewsText
{
    public static function plain(mixed $value, int $max = 350): string
    {
        if (!is_scalar($value) && $value !== null) {
            return '';
        }
        $s = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return mb_substr(trim(preg_replace('/\s+/u', ' ', $s) ?? ''), 0, $max);
    }
}
