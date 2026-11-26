<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

use InvalidArgumentException;

final class CompetitionCreationPolicy
{
    public static function selection(array $post, string $postedDivision): array
    {
        $postedDivisionIds = $post['competition_division_ids'] ?? [];
        if (!is_array($postedDivisionIds)) {
            $postedDivisionIds = [];
        }
        $postedDivisionIds = array_values(
            array_unique(
                array_filter(
                    array_map('intval', $postedDivisionIds),
                    static fn(int $divisionId): bool => $divisionId > 0,
                ),
            ),
        );
        $competitionRuleset = trim((string) ($post['competition_event_ruleset'] ?? 'tcg_advanced'));
        if (str_starts_with($postedDivision, 'format:')) {
            $competitionRuleset = substr($postedDivision, 7) ?: $competitionRuleset;
        }
        return ['divisionIds' => $postedDivisionIds, 'ruleset' => $competitionRuleset];
    }

    public static function format(array $post, array $selectedRuleset, string $postedDivision, array $postedDivisionIds): array
    {
        $matchFormat = trim(
            (string) ($post['competition_match_format'] ??
                ($selectedRuleset['match_format'] ?? '1v1')),
        );
        if (!in_array($matchFormat, ['1v1', '2v2', '3v3', 'ffa'], true)) {
            $matchFormat = '1v1';
        }
        $participantsPerSide = $matchFormat === 'ffa' ? 1 : (int) substr($matchFormat, 0, 1);
        $postedDivisionId =
            $postedDivision !== '' && ctype_digit($postedDivision) && (int) $postedDivision > 0
                ? (int) $postedDivision
                : null;
        if ($postedDivisionId && !in_array($postedDivisionId, $postedDivisionIds, true)) {
            array_unshift($postedDivisionIds, $postedDivisionId);
        }
        return ['matchFormat' => $matchFormat, 'participantsPerSide' => $participantsPerSide, 'divisionId' => $postedDivisionId, 'divisionIds' => $postedDivisionIds];
    }

    public static function movements(array $post, string $competitionType, ?int $postedDivisionId, array $postedDivisionIds): array
    {
        $deckPoolMode = (string) ($post['competition_deck_pool'] ?? 'all_active');
        if (!in_array($deckPoolMode, ['all_active', 'division'], true)) {
            $deckPoolMode = 'all_active';
        }
        if ($deckPoolMode === 'division' && (!$postedDivisionId || $postedDivisionId <= 0)) {
            $deckPoolMode = 'all_active';
        }
        $hasLeagueMovements = $competitionType === 'league' && count($postedDivisionIds) > 1;
        $leaguePromotionSlots = $hasLeagueMovements
            ? max(0, (int) ($post['competition_promotion_slots'] ?? 0))
            : 0;
        $leagueRelegationSlots = $hasLeagueMovements
            ? max(0, (int) ($post['competition_relegation_slots'] ?? 0))
            : 0;
        $leaguePromotionTo =
            $hasLeagueMovements && ($post['competition_promotion_to'] ?? '') !== ''
                ? (int) $post['competition_promotion_to']
                : null;
        $leagueRelegationTo =
            $hasLeagueMovements && ($post['competition_relegation_to'] ?? '') !== ''
                ? (int) $post['competition_relegation_to']
                : null;
        if ($postedDivisionId && $leaguePromotionTo === $postedDivisionId) {
            $leaguePromotionTo = null;
        }
        if ($postedDivisionId && $leagueRelegationTo === $postedDivisionId) {
            $leagueRelegationTo = null;
        }
        $lockDivisionMoves =
            $hasLeagueMovements && isset($post['competition_lock_division_moves']) ? 1 : 0;
        $qualifierDuelsPerDeck =
            $competitionType === 'qualifier'
                ? max(2, min(5, (int) ($post['qualifier_duels_per_deck'] ?? 3)))
                : 3;
        $qualifierMatchesPerDay =
            $competitionType === 'qualifier'
                ? max(1, min(5, (int) ($post['qualifier_matches_per_day'] ?? 1)))
                : 1;
        return [
            'deckPoolMode' => $deckPoolMode, 'leaguePromotionSlots' => $leaguePromotionSlots,
            'leagueRelegationSlots' => $leagueRelegationSlots, 'leaguePromotionTo' => $leaguePromotionTo,
            'leagueRelegationTo' => $leagueRelegationTo, 'lockDivisionMoves' => $lockDivisionMoves,
            'qualifierDuelsPerDeck' => $qualifierDuelsPerDeck, 'qualifierMatchesPerDay' => $qualifierMatchesPerDay,
        ];
    }

    public static function parameters(array $post): array
    {
        $operationalParameters = [];
        foreach (
            ['swiss_rounds', 'top_n', 'group_max_size', 'group_qualifiers', 'series_matches']
            as $parameter
        ) {
            $posted = trim((string) ($post['competition_parameter_' . $parameter] ?? ''));
            if ($posted === '') {
                continue;
            }
            $value = filter_var($posted, FILTER_VALIDATE_INT);
            if ($value === false || $value < 1 || $value > 64) {
                throw new InvalidArgumentException('Parametro operativo invalido: ' . $parameter);
            }
            $operationalParameters[$parameter] = $value;
        }
        if (isset($operationalParameters['series_matches'])) {
            if ($operationalParameters['series_matches'] % 2 !== 1) {
                throw new InvalidArgumentException(
                    'La serie debe tener un numero impar de matches.',
                );
            }
            $operationalParameters['series_wins'] =
                intdiv($operationalParameters['series_matches'], 2) + 1;
        }
        return $operationalParameters;
    }
}
