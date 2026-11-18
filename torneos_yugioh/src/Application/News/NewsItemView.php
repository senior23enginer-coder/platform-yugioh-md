<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\News;

/** Existing textual deck associations are navigational hints, never compatibility assertions. */
final class NewsItemView
{
    public static function present(array $item, array $decks): array
    {
        $item['id'] = (int) $item['id'];
        $item['bookmarked'] = (bool) $item['bookmarked'];
        $item['unread'] = $item['read_at'] === null;
        $item['has_image'] = !empty($item['image_url']);
        unset($item['image_url']);
        $item['related_decks'] = [];
        foreach ($decks as $deck) {
            $term = trim(preg_replace('/\s+(Pure|K9|Horus)$/i', '', $deck['name']));
            if (
                mb_strlen($term) >= 4 &&
                mb_stripos($item['title'] . ' ' . $item['excerpt'], $term) !== false
            ) {
                $item['related_decks'][] = ['id' => (int) $deck['id'], 'name' => $deck['name']];
            }
        }
        return $item;
    }
}
