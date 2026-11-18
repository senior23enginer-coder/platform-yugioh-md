<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\News;

/** Preserve prior evidence when an update lacks description, photograph or date. */
final class NewsContent
{
    public static function merge(array $item, ?array $old): array
    {
        if ($old) {
            if (!$item['excerpt']) {
                $item['excerpt'] = $old['excerpt'];
            }
            if (!$item['image_url']) {
                $item['image_url'] = $old['image_url'];
            }
            if (!$item['published_at']) {
                $item['published_at'] = $old['published_at'];
                $item['date_precision'] = $old['date_precision'];
            }
        }
        return $item;
    }

    public static function hash(array $item): string
    {
        return hash('sha256', json_encode($item, JSON_UNESCAPED_UNICODE));
    }
}
