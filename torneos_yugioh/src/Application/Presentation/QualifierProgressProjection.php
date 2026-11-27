<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Presentation;

final class QualifierProgressProjection
{
    public static function build(
        array $displayQualifierDecks,
        int $displayQualifierRequiredDuels,
        ?array $displayQualifierCompetition,
        array $fixtures,
        int $displayQualifierId,
        callable $mediaSource,
    ): array {
        $qualifierPairings = [];
        $deckCount = count($displayQualifierDecks);
        if ($deckCount > 1) {
            foreach ($displayQualifierDecks as $index => $deck) {
                $rivals = [];
                for ($offset = 1; $offset <= $displayQualifierRequiredDuels; $offset++) {
                    $rival = $displayQualifierDecks[($index + $offset) % $deckCount];
                    if ($rival['name'] !== $deck['name']) {
                        $rivals[] = $rival['name'];
                    }
                }
                $qualifierPairings[] = [
                    'deck' => $deck['name'],
                    'team' => $deck['team'] ?? 'Sin equipo',
                    'rivals' => array_slice(
                        array_values(array_unique($rivals)),
                        0,
                        $displayQualifierRequiredDuels,
                    ),
                ];
            }
        }
        $qualifierProgress = [];
        $displayQualifierRanking = array_values(
            (array) ($displayQualifierCompetition['ranking'] ?? []),
        );
        $displayQualifierRankingByDeckId = [];
        foreach ($displayQualifierRanking as $position => $standingRow) {
            $displayQualifierRankingByDeckId[(int) ($standingRow['id'] ?? 0)] = $standingRow + [
                'position' => $position + 1,
            ];
        }
        foreach ($displayQualifierDecks as $deck) {
            $deckId = (int) ($deck['id'] ?? 0);
            $standing = $displayQualifierRankingByDeckId[$deckId] ?? [];
            $qualifierProgress[$deck['name']] = [
                'rank' => (int) ($standing['position'] ?? $deck['rank']),
                'seed_rank' => (int) ($deck['rank'] ?? 0),
                'deck_id' => $deckId,
                'deck' => $deck['name'],
                'team' => $deck['team'] ?? 'Sin equipo',
                'code' => $deck['team_code'] ?? '',
                'team_logo' => $mediaSource((string) ($deck['team_logo'] ?? '')),
                'division' => $deck['division'],
                'pts' => (int) ($standing['pts'] ?? 0),
                'pj' => (int) ($standing['pj'] ?? 0),
                'pg' => (int) ($standing['pg'] ?? 0),
                'pe' => (int) ($standing['pe'] ?? 0),
                'pp' => (int) ($standing['pp'] ?? 0),
                'cg' => (int) ($standing['cg'] ?? 0),
                'cp' => (int) ($standing['cp'] ?? 0),
                'dp' => (int) ($standing['dp'] ?? 0),
                'played' => 0,
                'scheduled' => 0,
                'opponents' => [],
                'ready' => false,
            ];
        }
        foreach ($fixtures['CLASIFICATORIO'] ?? [] as $round) {
            foreach ($round['matches'] ?? [] as $match) {
                if (
                    $displayQualifierId > 0 &&
                    (int) ($match['competition_id'] ?? 0) !== $displayQualifierId
                ) {
                    continue;
                }
                foreach (
                    [[$match['a'] ?? '', $match['b'] ?? ''], [$match['b'] ?? '', $match['a'] ?? '']]
                    as $pair
                ) {
                    [$deckName, $opponentName] = $pair;
                    if (!isset($qualifierProgress[$deckName])) {
                        continue;
                    }
                    if (!empty($match['scheduled_on'])) {
                        $qualifierProgress[$deckName]['scheduled']++;
                    }
                    if (($match['estado'] ?? '') === 'FINALIZADA') {
                        $qualifierProgress[$deckName]['played']++;
                        if ($opponentName !== '') {
                            $qualifierProgress[$deckName]['opponents'][$opponentName] = true;
                        }
                    }
                }
            }
        }
        foreach ($qualifierProgress as &$progressRow) {
            $progressRow['opponent_count'] = count($progressRow['opponents']);
            $progressRow['ready'] = $progressRow['played'] >= $displayQualifierRequiredDuels;
            $progressRow['remaining'] = max(
                0,
                $displayQualifierRequiredDuels - $progressRow['played'],
            );
        }
        unset($progressRow);
        usort(
            $qualifierProgress,
            static fn(array $a, array $b): int => $b['pts'] <=> $a['pts'] ?:
            $b['dp'] <=> $a['dp'] ?:
            $b['cg'] <=> $a['cg'] ?:
            $a['seed_rank'] <=> $b['seed_rank'],
        );
        foreach ($qualifierProgress as $position => &$progressRow) {
            $progressRow['rank'] = $position + 1;
        }
        unset($progressRow);
        $qualifierReadyCount = count(
            array_filter($qualifierProgress, static fn(array $row): bool => $row['ready']),
        );
        return [
            $qualifierPairings,
            $qualifierProgress,
            $displayQualifierRanking,
            $displayQualifierRankingByDeckId,
            $qualifierReadyCount,
        ];
    }
}
