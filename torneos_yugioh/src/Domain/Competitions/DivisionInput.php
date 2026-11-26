<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

final class DivisionInput
{
    public static function selectedDecks(array $post): array
    {
        $selectedDeckIds = $post['division_deck_ids'] ?? [];
        if (!is_array($selectedDeckIds)) {
            $selectedDeckIds = [];
        }
        $selectedDeckIds = array_values(
            array_unique(
                array_filter(
                    array_map('intval', $selectedDeckIds),
                    static fn(int $deckId): bool => $deckId > 0,
                ),
            ),
        );
        return $selectedDeckIds;
    }

    public static function definition(int $id, array $post, array $selectedDeckIds): ?array
    {
        $code = strtoupper(trim((string) $post['division_code']));
        $name = trim((string) $post['division_name']);
        if ($code === '' || $name === '') {
            return null;
        }
        $promotionTo =
            ($post['division_promotion_to'] ?? '') === ''
                ? null
                : (int) $post['division_promotion_to'];
        $relegationTo =
            ($post['division_relegation_to'] ?? '') === ''
                ? null
                : (int) $post['division_relegation_to'];
        if ($id > 0 && $promotionTo === $id) {
            $promotionTo = null;
        }
        if ($id > 0 && $relegationTo === $id) {
            $relegationTo = null;
        }
        $leagueCompetitionId =
            ($post['division_league_competition_id'] ?? '') === ''
                ? null
                : (int) $post['division_league_competition_id'];
        $sourceQualifierId =
            ($post['division_source_qualifier_id'] ?? '') === ''
                ? null
                : (int) $post['division_source_qualifier_id'];
        $rankFrom =
            ($post['division_rank_from'] ?? '') === ''
                ? null
                : max(1, (int) $post['division_rank_from']);
        $rankTo =
            ($post['division_rank_to'] ?? '') === ''
                ? null
                : max(1, (int) $post['division_rank_to']);
        if ($rankFrom !== null && $rankTo !== null && $rankTo < $rankFrom) {
            [$rankFrom, $rankTo] = [$rankTo, $rankFrom];
        }
        $values = [
            $code,
            $name,
            trim((string) $post['division_cup']),
            max(1, (int) ($post['division_tier'] ?? 1)),
            max(0, (int) ($post['division_size'] ?? count($selectedDeckIds))),
            trim((string) $post['division_accent']),
            trim((string) $post['division_description']),
            $post['division_starts_on'] ?? '' ?: null,
            $post['division_ends_on'] ?? '' ?: null,
            max(0, (int) ($post['division_promotion_slots'] ?? 0)),
            max(0, (int) ($post['division_relegation_slots'] ?? 0)),
            $promotionTo,
            $relegationTo,
            $leagueCompetitionId,
            $sourceQualifierId,
            $rankFrom,
            $rankTo,
        ];
        return [
            'code' => $code,
            'values' => $values,
            'sourceQualifierId' => $sourceQualifierId,
            'rankFrom' => $rankFrom,
            'rankTo' => $rankTo,
        ];
    }
}
