<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\News;

/** Existing editorial format/topic labels; these never change sporting rules or deck contents. */
final class NewsClassifier
{
    public static function classify(string $source, string $text, string $category = ''): array
    {
        $v = mb_strtolower($category . ' ' . $text);
        $format = $source === 'masterduelmeta' ? 'master_duel' : 'general';
        if ($source !== 'masterduelmeta') {
            foreach (
                [
                    'rush_duel' => 'rush duel|rush-duel|rd/kp',
                    'master_duel' => 'master duel',
                    'goat' => '\bgoat\b',
                    'genesys' => 'genesys',
                    'ocg' => '\bocg\b',
                    'tcg' => '\btc[g]\b',
                ]
                as $key => $pattern
            ) {
                if (preg_match('~' . $pattern . '~iu', $v)) {
                    $format = $key;
                    break;
                }
            }
        }
        $topic = 'news';
        foreach (
            [
                'banlist' => 'banlist|forbidden|limited list|limits',
                'tournament' => 'tournament|ycs|wcq|regional|duelist cup',
                'cards' =>
                    'card[- ]reveal|new card|new:|alternate art|set[- ]release|secret[- ]pack|rd/kp',
                'guides' => 'guide|strategy|combo|archetype discussion',
            ]
            as $key => $pattern
        ) {
            if (preg_match('~' . $pattern . '~iu', $v)) {
                $topic = $key;
                break;
            }
        }
        return [$format, $topic];
    }
}
