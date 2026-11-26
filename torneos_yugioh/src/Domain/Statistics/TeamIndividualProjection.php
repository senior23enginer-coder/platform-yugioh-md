<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Statistics;

final class TeamIndividualProjection
{
    public static function expand(array $rows, array $teamIds, array $records, array $decks): array
    {
        $out = [];
        foreach ($rows as $row) {
            $mid = (int) ($row['match_id'] ?? ($row['id'] ?? 0));
            if (!isset($teamIds[$mid])) {
                $out[] = $row;
                continue;
            }
            $r = $records[$mid] ?? null;
            if (!$r || $r['kind'] !== 'team') {
                continue;
            }
            foreach ($r['evaluations'] as $seat => $evaluation) {
                $v = $row;
                $a = $r['lineup_a']['decks'][$seat];
                $b = $r['lineup_b']['decks'][$seat];
                $v['deck_a_id'] = $a;
                $v['deck_b_id'] = $b;
                $v['result'] = $evaluation['winner'];
                $v['score'] = $evaluation['score'];
                $v['duel_details'] = '[]';
                foreach (['c', 'd', 'e', 'f'] as $key) {
                    $v['deck_' . $key . '_id'] = null;
                }
                foreach (['a' => $a, 'b' => $b] as $side => $id) {
                    $v['deck_' . $side] = $decks[$id]['name'];
                    $v['deck_' . $side . '_name'] = $decks[$id]['name'];
                    $v['archetype_' . $side] = $decks[$id]['archetype'];
                    $v['variant_' . $side] = $decks[$id]['variations'];
                    $v['team_' . $side . '_id'] = $decks[$id]['team_id'];
                    $v['team_' . $side . '_name'] = $decks[$id]['team_name'];
                }
                foreach (range(1, 5) as $n) {
                    $v['d' . $n] = $r['sequences'][$seat][$n - 1] ?? null;
                }
                $v['team_seat'] = $seat + 1;
                $out[] = $v;
            }
        }
        return $out;
    }
}
