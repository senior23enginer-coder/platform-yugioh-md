<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

final class DivisionRankPolicy
{
    public static function category(int $rank, array &$divisionDefinitions): string
    {
        $cursor = 0;
        foreach ($divisionDefinitions as $definition) {
            $cursor += (int) ($definition['size'] ?? 0);
            if ($rank <= $cursor) {
                return (string) $definition['short'];
            }
        }
        $last = end($divisionDefinitions);
        return (string) ($last['short'] ?? '' ?: 'Genesis');
    }
}
