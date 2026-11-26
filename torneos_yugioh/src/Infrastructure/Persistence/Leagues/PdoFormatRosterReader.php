<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Leagues;

use PDO;
use StationX\Yugioh\Application\Leagues\FormatRosterReader;
use StationX\Yugioh\Domain\Competitions\LeagueRosterPolicy;

final readonly class PdoFormatRosterReader implements FormatRosterReader
{
    public function __construct(private PDO $pdo) {}

    public function deckIds(string $ruleset): array
    {
        if ($ruleset === '') {
            return [];
        }
        if ($ruleset === 'custom') {
            return array_map(
                'intval',
                $this->pdo
                    ->query(
                        'SELECT id FROM sx_ygo_decks WHERE active=1 AND team_id IS NOT NULL ORDER BY seed_rank,name,id',
                    )
                    ->fetchAll(PDO::FETCH_COLUMN),
            );
        }
        $stmt = $this->pdo->prepare("SELECT DISTINCT d.id,d.seed_rank,d.name
            FROM sx_ygo_decks d
            WHERE d.active=1 AND d.team_id IS NOT NULL AND (
                EXISTS (SELECT 1 FROM sx_ygo_divisions dv JOIN sx_ygo_competitions c ON c.id=dv.league_competition_id AND c.active=1 WHERE dv.id=d.division_id AND dv.active=1 AND c.event_ruleset=?)
                OR EXISTS (SELECT 1 FROM sx_ygo_competition_decks cd JOIN sx_ygo_competitions c ON c.id=cd.competition_id AND c.active=1 WHERE cd.deck_id=d.id AND cd.active=1 AND c.event_ruleset=?)
                OR EXISTS (SELECT 1 FROM sx_ygo_matches m JOIN sx_ygo_competitions c ON c.id=m.competition_id AND c.active=1 WHERE c.event_ruleset=? AND d.id IN (m.deck_a_id,m.deck_b_id,m.deck_c_id,m.deck_d_id,m.deck_e_id,m.deck_f_id))
                OR LOWER(COALESCE(d.style,'')) IN (?,?)
            ) ORDER BY d.seed_rank,d.name,d.id");
        $styles = LeagueRosterPolicy::styleAliases($ruleset);
        $stmt->execute([$ruleset, $ruleset, $ruleset, ...$styles]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function activeFormats(): array
    {
        return $this->pdo
            ->query("SELECT DISTINCT code FROM sx_ygo_rulesets WHERE active=1 AND code<>'custom'")
            ->fetchAll(PDO::FETCH_COLUMN);
    }
}
