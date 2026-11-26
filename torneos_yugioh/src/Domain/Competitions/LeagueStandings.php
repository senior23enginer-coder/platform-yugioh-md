<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

final class LeagueStandings
{
    public static function calculate(array $rows, array $matches): array
    {
        $standings = [];
        foreach ($rows as $deck) {
            $standings[(int) $deck['id']] = [
                'id' => (int) $deck['id'],
                'name' => (string) $deck['name'],
                'division' => (string) $deck['division_code'],
                'rank' => (int) $deck['seed_rank'],
                'pts' => 0,
                'pj' => 0,
                'pg' => 0,
                'pe' => 0,
                'pp' => 0,
                'cg' => 0,
                'cp' => 0,
                'dp' => 0,
            ];
        }
        foreach ($matches as $match) {
            $aId = (int) $match['deck_a_id'];
            $bId = (int) $match['deck_b_id'];
            if (!isset($standings[$aId], $standings[$bId])) {
                continue;
            }
            $scoreParts = array_map('intval', explode('-', (string) $match['score']));
            $scoreA = $scoreParts[0] ?? 0;
            $scoreB = $scoreParts[1] ?? 0;
            $standings[$aId]['pj']++;
            $standings[$bId]['pj']++;
            $standings[$aId]['cg'] += $scoreA;
            $standings[$aId]['cp'] += $scoreB;
            $standings[$bId]['cg'] += $scoreB;
            $standings[$bId]['cp'] += $scoreA;
            if ($match['result'] === 'A') {
                $standings[$aId]['pts'] += 3;
                $standings[$aId]['pg']++;
                $standings[$bId]['pp']++;
            } elseif ($match['result'] === 'B') {
                $standings[$bId]['pts'] += 3;
                $standings[$bId]['pg']++;
                $standings[$aId]['pp']++;
            } elseif ($match['result'] === 'E') {
                $standings[$aId]['pts']++;
                $standings[$bId]['pts']++;
                $standings[$aId]['pe']++;
                $standings[$bId]['pe']++;
            }
        }
        foreach ($standings as &$row) {
            $row['dp'] = $row['cg'] - $row['cp'];
        }
        unset($row);
        usort($standings, static fn(array $a, array $b): int => $b['pts'] <=> $a['pts'] ?: $b['dp'] <=> $a['dp'] ?: $b['cg'] <=> $a['cg'] ?: $a['rank'] <=> $b['rank']);
        return $standings;
    }
}
