<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Presentation;

final class QualifierMatchProjection
{
    public static function statistics(array $fixtures, array $qualifierCompetitionIdMap): array
    {
        $qualifierStatsByCompetition = [];
        foreach ($fixtures as $rounds) {
            foreach ($rounds as $round) {
                foreach (($round['matches'] ?? []) as $match) {
                    $competitionId = (int) ($match['competition_id'] ?? 0);
                    if ($competitionId <= 0 || !isset($qualifierCompetitionIdMap[$competitionId])) {
                        continue;
                    }
                    if (!isset($qualifierStatsByCompetition[$competitionId])) {
                        $qualifierStatsByCompetition[$competitionId] = ['matches' => 0, 'finished' => 0];
                    }
                    $qualifierStatsByCompetition[$competitionId]['matches']++;
                    if ((string) ($match['estado'] ?? '') === 'FINALIZADA' || !empty($match['score'])) {
                        $qualifierStatsByCompetition[$competitionId]['finished']++;
                    }
                }
            }
        }
        return $qualifierStatsByCompetition;
    }

    public static function display(array $fixtures, int $displayQualifierId): array
    {
        $displayQualifierMatches = [];
        foreach (($fixtures['CLASIFICATORIO'] ?? []) as $round) {
            foreach (($round['matches'] ?? []) as $match) {
                if ($displayQualifierId > 0 && (int) ($match['competition_id'] ?? 0) !== $displayQualifierId) {
                    continue;
                }
                $displayQualifierMatches[] = $match + ['round_number' => (int) ($round['ronda'] ?? 0)];
            }
        }
        usort($displayQualifierMatches, static function (array $a, array $b): int {
            return strcmp((string) ($a['scheduled_on'] ?? '9999-12-31'), (string) ($b['scheduled_on'] ?? '9999-12-31'))
                ?: strcmp((string) ($a['starts_at'] ?? '23:59:59'), (string) ($b['starts_at'] ?? '23:59:59'))
                ?: ((int) ($a['round_number'] ?? 0) <=> (int) ($b['round_number'] ?? 0))
                ?: ((int) ($a['mesa'] ?? 0) <=> (int) ($b['mesa'] ?? 0));
        });
        $displayQualifierRoundCount = count(array_unique(array_map(static fn(array $match): int => (int) ($match['round_number'] ?? 0), $displayQualifierMatches)));
        $displayQualifierScheduledCount = count(array_filter($displayQualifierMatches, static fn(array $match): bool => !empty($match['scheduled_on'])));
        $displayQualifierFinishedCount = count(array_filter($displayQualifierMatches, static fn(array $match): bool => (($match['estado'] ?? '') === 'FINALIZADA') || !empty($match['score'])));
        return [
            'matches' => $displayQualifierMatches,
            'roundCount' => $displayQualifierRoundCount,
            'scheduledCount' => $displayQualifierScheduledCount,
            'finishedCount' => $displayQualifierFinishedCount,
        ];
    }
}
