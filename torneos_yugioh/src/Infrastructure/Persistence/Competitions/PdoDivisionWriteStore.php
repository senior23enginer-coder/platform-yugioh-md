<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\DivisionWriteStore;

final readonly class PdoDivisionWriteStore implements DivisionWriteStore
{
    public function __construct(private PDO $pdo) {}

    public function currentSeasonId(): int
    {
        return \ygo_current_season_id($this->pdo);
    }

    public function save(int $id, int $seasonId, string $code, array $values): int
    {
        if ($id > 0) {
            $this->pdo->prepare(
                'UPDATE sx_ygo_divisions SET code=?, name=?, cup_name=?, tier_order=?, deck_count=?, accent=?, description=?, starts_on=?, ends_on=?, promotion_slots=?, relegation_slots=?, promotion_to_division_id=?, relegation_to_division_id=?, league_competition_id=?, source_qualifier_id=?, rank_from=?, rank_to=? WHERE id=? AND season_id=?',
            )->execute([...$values, $id, $seasonId]);
        } else {
            $this->pdo->prepare(
                '
                INSERT INTO sx_ygo_divisions
                    (season_id, code, name, cup_name, tier_order, deck_count, accent, description, starts_on, ends_on, promotion_slots, relegation_slots, promotion_to_division_id, relegation_to_division_id, league_competition_id, source_qualifier_id, rank_from, rank_to, active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE name=VALUES(name), cup_name=VALUES(cup_name), tier_order=VALUES(tier_order), deck_count=VALUES(deck_count), accent=VALUES(accent), description=VALUES(description), starts_on=VALUES(starts_on), ends_on=VALUES(ends_on), promotion_slots=VALUES(promotion_slots), relegation_slots=VALUES(relegation_slots), promotion_to_division_id=VALUES(promotion_to_division_id), relegation_to_division_id=VALUES(relegation_to_division_id), league_competition_id=VALUES(league_competition_id), source_qualifier_id=VALUES(source_qualifier_id), rank_from=VALUES(rank_from), rank_to=VALUES(rank_to), active=1
            ',
            )->execute([$seasonId, ...$values]);
            $lookup = $this->pdo->prepare(
                'SELECT id FROM sx_ygo_divisions WHERE season_id=? AND code=? LIMIT 1',
            );
            $lookup->execute([$seasonId, $code]);
            $id = (int) $lookup->fetchColumn();
        }
        return $id;
    }

    public function uploadLogo(int $id): string
    {
        return \ygo_store_division_logo_upload($id);
    }

    public function setLogo(int $id, int $seasonId, string $url): void
    {
        $this->pdo->prepare('UPDATE sx_ygo_divisions SET logo_url=? WHERE id=? AND season_id=?')->execute([$url, $id, $seasonId]);
    }

    public function assignDecks(int $id, array $decks): void
    {
        $placeholders = implode(',', array_fill(0, count($decks), '?'));
        $deckStmt = $this->pdo->prepare(
            "UPDATE sx_ygo_decks SET division_id=? WHERE active=1 AND id IN ($placeholders)",
        );
        $deckStmt->execute([$id, ...$decks]);
        $this->pdo->prepare(
            'UPDATE sx_ygo_divisions SET deck_count=(SELECT COUNT(*) FROM sx_ygo_decks WHERE division_id=? AND active=1) WHERE id=?',
        )->execute([$id, $id]);
    }

    public function applyQualifier(int $id): void
    {
        \ygo_apply_division_from_qualifier($this->pdo, $id);
    }
}
