<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native save_division adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class SaveDivisionHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $id = (int) ($post['division_id'] ?? 0);
        $seasonId = ygo_current_season_id($pdo);
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
        if (count($selectedDeckIds) > 0 && count($selectedDeckIds) < 4) {
            return 'division_minimo_4_decks';
        }
        $code = strtoupper(trim((string) $post['division_code']));
        $name = trim((string) $post['division_name']);
        if ($code !== '' && $name !== '') {
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
            if ($id > 0) {
                $pdo->prepare(
                    'UPDATE sx_ygo_divisions SET code=?, name=?, cup_name=?, tier_order=?, deck_count=?, accent=?, description=?, starts_on=?, ends_on=?, promotion_slots=?, relegation_slots=?, promotion_to_division_id=?, relegation_to_division_id=?, league_competition_id=?, source_qualifier_id=?, rank_from=?, rank_to=? WHERE id=? AND season_id=?',
                )->execute([...$values, $id, $seasonId]);
            } else {
                $pdo->prepare(
                    '
                    INSERT INTO sx_ygo_divisions
                        (season_id, code, name, cup_name, tier_order, deck_count, accent, description, starts_on, ends_on, promotion_slots, relegation_slots, promotion_to_division_id, relegation_to_division_id, league_competition_id, source_qualifier_id, rank_from, rank_to, active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                    ON DUPLICATE KEY UPDATE name=VALUES(name), cup_name=VALUES(cup_name), tier_order=VALUES(tier_order), deck_count=VALUES(deck_count), accent=VALUES(accent), description=VALUES(description), starts_on=VALUES(starts_on), ends_on=VALUES(ends_on), promotion_slots=VALUES(promotion_slots), relegation_slots=VALUES(relegation_slots), promotion_to_division_id=VALUES(promotion_to_division_id), relegation_to_division_id=VALUES(relegation_to_division_id), league_competition_id=VALUES(league_competition_id), source_qualifier_id=VALUES(source_qualifier_id), rank_from=VALUES(rank_from), rank_to=VALUES(rank_to), active=1
                ',
                )->execute([$seasonId, ...$values]);
                $lookup = $pdo->prepare(
                    'SELECT id FROM sx_ygo_divisions WHERE season_id=? AND code=? LIMIT 1',
                );
                $lookup->execute([$seasonId, $code]);
                $id = (int) $lookup->fetchColumn();
            }
            if ($id > 0) {
                $uploadedLogo = ygo_store_division_logo_upload($id);
                if ($uploadedLogo !== '') {
                    $pdo->prepare(
                        'UPDATE sx_ygo_divisions SET logo_url=? WHERE id=? AND season_id=?',
                    )->execute([$uploadedLogo, $id, $seasonId]);
                }
            }
            if ($id > 0 && $selectedDeckIds) {
                $placeholders = implode(',', array_fill(0, count($selectedDeckIds), '?'));
                $deckStmt = $pdo->prepare(
                    "UPDATE sx_ygo_decks SET division_id=? WHERE active=1 AND id IN ($placeholders)",
                );
                $deckStmt->execute([$id, ...$selectedDeckIds]);
                $pdo->prepare(
                    'UPDATE sx_ygo_divisions SET deck_count=(SELECT COUNT(*) FROM sx_ygo_decks WHERE division_id=? AND active=1) WHERE id=?',
                )->execute([$id, $id]);
            } elseif ($id > 0 && $sourceQualifierId && $rankFrom && $rankTo) {
                ygo_apply_division_from_qualifier($pdo, $id);
            }
        }
        return 'division';

        return null;
    }
}
