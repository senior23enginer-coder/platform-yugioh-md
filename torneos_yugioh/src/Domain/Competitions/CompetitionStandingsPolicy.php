<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

final class CompetitionStandingsPolicy
{
    public static function phase(array $competition, array $workflowRanking, ?string $phaseFilter): ?string
    {
        if ($workflowRanking && $phaseFilter === null) {
            return [
                'swiss' => 'swiss',
                'swiss_top_cut' => 'swiss',
                'groups' => 'groups',
                'groups_knockout' => 'groups',
                'season_playoffs' => 'league',
            ][(string) $competition['execution_model']] ?? null;
        }
        return $phaseFilter;
    }

    public static function rankingImpact(array $competition): string
    {
        return strtoupper((string) (($competition['ranking_impact'] ?? '') ?: 'FULL'));
    }

    public static function excluded(array $competition, array $workflowRanking): bool
    {
        $level = strtoupper((string) (($competition['level'] ?? '') ?: 'COMPETITIVE'));
        return !$workflowRanking && (
            self::rankingImpact($competition) === 'NONE' ||
            in_array($level, ['CASUAL', 'BEGINNER', 'ACADEMY', 'PROMOTIONAL', 'EXHIBITION'], true)
        );
    }

    public static function calculate(
        array $deckRows,
        array $matches,
        string $rankingImpact,
        array $workflowRanking,
    ): array {
        $winPoints = $rankingImpact === 'REDUCED' ? 1 : ($rankingImpact === 'BONUS' ? 4 : 3);
        $drawPoints = $rankingImpact === 'REDUCED' ? 0 : 1;
        $rows = [];
        foreach ($deckRows as $deck) {
            $rows[(int) $deck['id']] = [
                'id' => (int) $deck['id'],
                'name' => (string) $deck['name'],
                'team' => (string) $deck['team'],
                'team_code' => (string) $deck['team_code'],
                'team_primary_color' => (string) $deck['team_primary_color'],
                'team_secondary_color' => (string) $deck['team_secondary_color'],
                'seed_rank' => (int) $deck['seed_rank'],
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
            if (!isset($rows[$aId], $rows[$bId])) {
                continue;
            }
            [$cgA, $cgB] = array_pad(array_map('intval', explode('-', (string) ($match['score'] ?: '0-0'))), 2, 0);
            $rows[$aId]['pj']++;
            $rows[$bId]['pj']++;
            $rows[$aId]['cg'] += $cgA;
            $rows[$aId]['cp'] += $cgB;
            $rows[$bId]['cg'] += $cgB;
            $rows[$bId]['cp'] += $cgA;
            if ($match['result'] === 'A') {
                $rows[$aId]['pts'] += $winPoints;
                $rows[$aId]['pg']++;
                $rows[$bId]['pp']++;
            } elseif ($match['result'] === 'B') {
                $rows[$bId]['pts'] += $winPoints;
                $rows[$bId]['pg']++;
                $rows[$aId]['pp']++;
            } elseif ($match['result'] === 'E') {
                $rows[$aId]['pts'] += $drawPoints;
                $rows[$bId]['pts'] += $drawPoints;
                $rows[$aId]['pe']++;
                $rows[$bId]['pe']++;
            }
        }
        foreach ($rows as &$row) {
            $row['dp'] = $row['cg'] - $row['cp'];
        }
        unset($row);
        usort($rows, static fn(array $a, array $b): int => $b['pts'] <=> $a['pts'] ?: $b['dp'] <=> $a['dp'] ?: $b['cg'] <=> $a['cg'] ?: $a['seed_rank'] <=> $b['seed_rank']);
        if ($workflowRanking) {
            $byId = [];
            foreach ($rows as $r) {
                $byId[$r['id']] = $r;
            }
            $rows = [];
            foreach ($workflowRanking as $r) {
                if (isset($byId[$r['id']])) {
                    $row = $byId[$r['id']];
                    $row['pts'] = $r['points'];
                    $row['buchholz'] = $r['buchholz'];
                    $row['group'] = $r['group'] ?? null;
                    $row['group_position'] = $r['group_position'] ?? null;
                    $rows[] = $row;
                }
            }
        }
        return $rows;
    }

    public static function teams(array $rows, array $teamConfig): array
    {
        $lineups = [];
        foreach ($teamConfig['lineups'] as $lineup) {
            $lineups[$lineup['captain']] = $lineup;
        }
        $rows = array_values(array_filter($rows, static fn($r) => isset($lineups[$r['id']])));
        foreach ($rows as &$r) {
            $r['name'] = $lineups[$r['id']]['name'];
            $r['team'] = 'Equipo ' . $teamConfig['size'] . 'v' . $teamConfig['size'];
            $r['member_deck_ids'] = $lineups[$r['id']]['decks'];
        }
        unset($r);
        return $rows;
    }
}
