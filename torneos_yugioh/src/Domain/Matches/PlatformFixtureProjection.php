<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Matches;

final class PlatformFixtureProjection
{
    public static function build(array $matchRows, array $divisions): array
    {
        $fixtures = [];
        foreach ($matchRows as $match) {
            $phase = (string) $match['phase'];
            $baseCode = (string) $match['division_code'];
            if ($phase === 'qualifier') {
                $code = 'CLASIFICATORIO';
            } elseif ($phase === 'league') {
                $code = $baseCode !== '' ? $baseCode : 'LIGA';
            } elseif ($phase === 'cup') {
                $code = $baseCode !== '' ? 'COPA_' . $baseCode : 'COPA';
            } elseif ($phase === 'world') {
                $code = 'MUNDIAL';
            } elseif ($phase === 'friendly') {
                $code = 'AMISTOSOS';
            } else {
                $code = strtoupper($phase);
            }
            if ($code === '') {
                $code = strtoupper($phase ?: 'GENERAL');
            }
            $round = (int) $match['round_number'];
            if (!isset($fixtures[$code][$round])) {
                $fixtures[$code][$round] = ['ronda' => $round, 'descansa' => '', 'matches' => []];
            }
            $fixtures[$code][$round]['matches'][] = [
                'id' => (int) $match['id'],
                'phase' => $phase,
                'competition_id' => $match['competition_id'] !== null ? (int) $match['competition_id'] : null,
                'manual_friendly' => $phase === 'friendly' && $match['competition_id'] === null,
                'game_rule_id' => $match['game_rule_id'] !== null ? (int) $match['game_rule_id'] : null,
                'competition_name' => (string) ($match['competition_name'] ?? ''),
                'competition_type' => (string) ($match['competition_type'] ?? $phase),
                'competition_status' => (string) ($match['competition_status'] ?? ''),
                'competition_ruleset' => (string) ($match['competition_ruleset'] ?? ''),
                'competition_ruleset_version' => (string) ($match['competition_ruleset_version'] ?? ''),
                'match_format' => (string) ($match['match_format'] ?? '1v1'),
                'best_of' => (int) ($match['best_of'] ?? 5),
                'participants_per_side' => (int) ($match['participants_per_side'] ?? 1),
                'mesa' => (int) $match['table_number'],
                'bracket_round_label' => (string) ($match['bracket_round_label'] ?? ''),
                'bracket_slot' => $match['bracket_slot'] !== null ? (int) $match['bracket_slot'] : null,
                'bracket_side' => (string) ($match['bracket_side'] ?? ''),
                'display_order' => (int) ($match['display_order'] ?? 0),
                'generation_number' => (int) ($match['generation_number'] ?? 1),
                'deck_a_id' => (int) $match['deck_a_id'],
                'deck_b_id' => (int) $match['deck_b_id'],
                'deck_c_id' => $match['deck_c_id'] !== null ? (int) $match['deck_c_id'] : null,
                'deck_d_id' => $match['deck_d_id'] !== null ? (int) $match['deck_d_id'] : null,
                'deck_e_id' => $match['deck_e_id'] !== null ? (int) $match['deck_e_id'] : null,
                'deck_f_id' => $match['deck_f_id'] !== null ? (int) $match['deck_f_id'] : null,
                'a' => (string) $match['deck_a_name'],
                'b' => (string) $match['deck_b_name'],
                'team_a' => (string) ($match['team_a_name'] ?: 'Sin equipo'),
                'team_b' => (string) ($match['team_b_name'] ?: 'Sin equipo'),
                'team_a_code' => (string) ($match['team_a_code'] ?: ''),
                'team_b_code' => (string) ($match['team_b_code'] ?: ''),
                'team_a_logo' => (string) ($match['team_a_logo'] ?? ''),
                'team_b_logo' => (string) ($match['team_b_logo'] ?? ''),
                'team_a_extra' => array_values(array_filter([(string) ($match['deck_c_name'] ?? ''), (string) ($match['deck_e_name'] ?? '')])),
                'team_b_extra' => array_values(array_filter([(string) ($match['deck_d_name'] ?? ''), (string) ($match['deck_f_name'] ?? '')])),
                'estado' => (string) $match['status'],
                'scheduled_on' => (string) ($match['scheduled_on'] ?? ''),
                'starts_at' => (string) ($match['starts_at'] ?? ''),
                'venue' => (string) ($match['venue'] ?? ''),
                'judge' => (string) ($match['judge'] ?? ''),
                'notes' => (string) ($match['notes'] ?? ''),
                'duels' => array_values(array_filter([$match['d1'], $match['d2'], $match['d3'], $match['d4'], $match['d5']], static fn($v): bool => $v !== null && $v !== '')),
                'result' => (string) ($match['result'] ?? ''),
                'score' => (string) ($match['score'] ?? ''),
                'result_notes' => (string) ($match['result_notes'] ?? ''),
                'dice_roll' => (string) ($match['dice_roll'] ?? ''),
                'turn_choice' => (string) ($match['turn_choice'] ?? ''),
                'dice_winner' => (string) ($match['dice_winner'] ?? ''),
                'turn_chosen_by' => (string) ($match['turn_chosen_by'] ?? ''),
                'turn_decision' => (string) ($match['turn_decision'] ?? ''),
                'starts_player' => (string) ($match['starts_player'] ?? ''),
                'duel_details' => (string) ($match['duel_details'] ?? ''),
                'result_updated_at' => (string) ($match['result_updated_at'] ?? ''),
                'completed_at' => $match['completed_at'] ?? null,
            ];
        }
        foreach ($divisions as $code => $division) {
            if (!isset($fixtures[$code])) {
                $fixtures[$code] = [];
            } else {
                ksort($fixtures[$code]);
                $fixtures[$code] = array_values($fixtures[$code]);
            }
        }
        $deckMatchCounts = [];
        foreach ($matchRows as $match) {
            foreach (['deck_a_id', 'deck_b_id', 'deck_c_id', 'deck_d_id', 'deck_e_id', 'deck_f_id'] as $deckColumn) {
                $matchDeckId = (int) ($match[$deckColumn] ?? 0);
                if ($matchDeckId > 0) {
                    $deckMatchCounts[$matchDeckId] = ($deckMatchCounts[$matchDeckId] ?? 0) + 1;
                }
            }
        }
        return ['fixtures' => $fixtures, 'deckMatchCounts' => $deckMatchCounts];
    }
}
