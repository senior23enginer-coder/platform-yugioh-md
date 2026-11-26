<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Decks;

final class PlatformDeckProjection
{
    public static function divisions(array $dbDivisions, array $fallbackDivisions): array
    {
        $divisions = [];
        foreach ($dbDivisions as $division) {
            $code = (string) $division['code'];
            $divisions[$code] = [
                'id' => (int) $division['id'],
                'name' => (string) $division['name'],
                'short' => $code,
                'cup' => (string) $division['cup_name'],
                'deck_count' => (int) ($division['deck_count'] ?? 0),
                'tier_order' => (int) ($division['tier_order'] ?? 0),
                'accent' => (string) ($division['accent'] ?: ($fallbackDivisions[$code]['accent'] ?? '#36d8ff')),
                'description' => (string) ($division['description'] ?: ($fallbackDivisions[$code]['description'] ?? 'Evento editable.')),
                'starts_on' => (string) ($division['starts_on'] ?? ''),
                'ends_on' => (string) ($division['ends_on'] ?? ''),
                'promotion_slots' => (int) ($division['promotion_slots'] ?? 2),
                'relegation_slots' => (int) ($division['relegation_slots'] ?? 2),
                'promotion_to_division_id' => $division['promotion_to_division_id'] !== null ? (int) $division['promotion_to_division_id'] : null,
                'relegation_to_division_id' => $division['relegation_to_division_id'] !== null ? (int) $division['relegation_to_division_id'] : null,
                'league_competition_id' => $division['league_competition_id'] !== null ? (int) $division['league_competition_id'] : null,
                'source_qualifier_id' => $division['source_qualifier_id'] !== null ? (int) $division['source_qualifier_id'] : null,
                'rank_from' => $division['rank_from'] !== null ? (int) $division['rank_from'] : null,
                'rank_to' => $division['rank_to'] !== null ? (int) $division['rank_to'] : null,
                'logo_url' => (string) ($division['logo_url'] ?? ''),
                'created_at' => (string) ($division['created_at'] ?? ''),
                'active' => (int) ($division['active'] ?? 1),
                'decks' => [],
            ];
        }
        return $divisions;
    }

    public static function decks(array $divisions, array $deckRows): array
    {
        $allDecks = [];
        foreach ($deckRows as $deck) {
            $code = (string) ($deck['division_code'] ?? '');
            $hasDivision = $code !== '' && isset($divisions[$code]);
            $row = [
                (int) $deck['seed_rank'],
                (string) $deck['name'],
                (string) ($deck['reason'] ?: 'Ficha editable'),
                'id' => (int) $deck['id'],
                'team_id' => $deck['team_id'] !== null ? (int) $deck['team_id'] : null,
                'team' => (string) ($deck['team_name'] ?: 'Sin equipo'),
                'team_code' => (string) ($deck['team_code'] ?: ''),
                'team_logo' => (string) ($deck['team_logo'] ?: ''),
                'team_primary_color' => (string) ($deck['team_primary_color'] ?: ''),
                'team_secondary_color' => (string) ($deck['team_secondary_color'] ?: ''),
                'team_symbol' => (string) ($deck['team_symbol'] ?: ''),
                'archetype' => (string) ($deck['archetype'] ?: $deck['name']),
                'style' => (string) ($deck['style'] ?: 'Editable'),
                'key_cards' => (string) ($deck['key_cards'] ?: ''),
                'variations' => (string) ($deck['variations'] ?: ''),
                'notes' => (string) ($deck['notes'] ?: ''),
                'cover_url' => (string) ($deck['cover_url'] ?: ''),
                'logo_url' => (string) ($deck['logo_url'] ?: ''),
            ];
            if ($hasDivision) {
                $divisions[$code]['decks'][] = $row;
            }
            $allDecks[] = [
                'id' => (int) $deck['id'],
                'rank' => (int) $deck['seed_rank'],
                'name' => (string) $deck['name'],
                'division' => $hasDivision ? $code : 'Sin division',
                'division_id' => $hasDivision ? (int) $deck['division_id'] : null,
                'team' => (string) ($deck['team_name'] ?: 'Sin equipo'),
                'team_code' => (string) ($deck['team_code'] ?: ''),
                'team_logo' => (string) ($deck['team_logo'] ?: ''),
                'team_primary_color' => (string) ($deck['team_primary_color'] ?: ''),
                'team_secondary_color' => (string) ($deck['team_secondary_color'] ?: ''),
                'team_symbol' => (string) ($deck['team_symbol'] ?: ''),
                'team_id' => $deck['team_id'] !== null ? (int) $deck['team_id'] : null,
                'reason' => (string) ($deck['reason'] ?: 'Ficha editable'),
                'archetype' => (string) ($deck['archetype'] ?: $deck['name']),
                'style' => (string) ($deck['style'] ?: 'Editable'),
                'key_cards' => (string) ($deck['key_cards'] ?: ''),
                'variations' => (string) ($deck['variations'] ?: ''),
                'notes' => (string) ($deck['notes'] ?: ''),
                'cover_url' => (string) ($deck['cover_url'] ?: ''),
                'logo_url' => (string) ($deck['logo_url'] ?: ''),
            ];
        }

        $qualifierDeckRows = [];
        foreach ($allDecks as $deck) {
            $qualifierDeckRows[] = [
                (int) $deck['rank'],
                (string) $deck['name'],
                (string) $deck['reason'],
                'id' => (int) $deck['id'],
                'team_id' => $deck['team_id'],
                'team' => $deck['team'],
                'team_code' => $deck['team_code'],
                'team_logo' => $deck['team_logo'] ?? '',
                'team_primary_color' => $deck['team_primary_color'],
                'team_secondary_color' => $deck['team_secondary_color'],
                'team_symbol' => $deck['team_symbol'],
                'archetype' => $deck['archetype'],
                'style' => $deck['style'],
                'key_cards' => $deck['key_cards'],
                'variations' => $deck['variations'],
                'notes' => $deck['notes'],
                'cover_url' => $deck['cover_url'],
                'logo_url' => $deck['logo_url'] ?? '',
            ];
        }
        $divisions = ['CLASIFICATORIO' => [
            'id' => 0,
            'name' => 'Torneo clasificatorio inicial',
            'short' => 'CLASIFICATORIO',
            'cup' => 'Ranking de entrada a divisiones',
            'accent' => '#36d8ff',
            'description' => 'Fase previa gestionable con minimo 3 combates por deck.',
            'decks' => $qualifierDeckRows,
        ]] + $divisions;
        return ['divisions' => $divisions, 'allDecks' => $allDecks];
    }
}
