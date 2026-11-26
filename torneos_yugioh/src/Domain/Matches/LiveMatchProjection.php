<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Matches;

final class LiveMatchProjection
{
    public static function matches(array $fixtures): array
    {
        $liveMatches = [];
        foreach ($fixtures as $rounds) {
            foreach ($rounds as $round) {
                foreach ($round['matches'] ?? [] as $match) {
                    $liveMatches[] = [
                        'id' => (int) ($match['id'] ?? 0),
                        'competition_id' => (int) ($match['competition_id'] ?? 0),
                        'competition_name' => (string) ($match['competition_name'] ?? ''),
                        'ronda' => (string) ($match['ronda'] ?? ''),
                        'mesa' => (string) ($match['mesa'] ?? ''),
                        'a' => (string) ($match['a'] ?? ''),
                        'b' => (string) ($match['b'] ?? ''),
                        'team_a' => (string) ($match['team_a'] ?? ''),
                        'team_b' => (string) ($match['team_b'] ?? ''),
                        'venue' => (string) ($match['venue'] ?? ''),
                        'estado' => (string) ($match['estado'] ?? ''),
                        'score' => (string) ($match['score'] ?? ''),
                        'd1' => $match['d1'] ?? null,
                        'd2' => $match['d2'] ?? null,
                        'd3' => $match['d3'] ?? null,
                        'd4' => $match['d4'] ?? null,
                        'd5' => $match['d5'] ?? null,
                        'duel_details' => (string) ($match['duel_details'] ?? ''),
                        'scheduled_on' => (string) ($match['scheduled_on'] ?? ''),
                        'starts_at' => (string) ($match['starts_at'] ?? ''),
                        'result_updated_at' => (string) ($match['result_updated_at'] ?? ''),
                    ];
                }
            }
        }
        return $liveMatches;
    }
}
