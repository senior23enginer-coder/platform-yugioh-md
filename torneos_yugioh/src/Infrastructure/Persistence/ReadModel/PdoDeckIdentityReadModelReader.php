<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\ReadModel;

use PDO;
use StationX\Yugioh\Application\ReadModel\DeckIdentityReadModelReader;

final readonly class PdoDeckIdentityReadModelReader implements DeckIdentityReadModelReader
{
    public function __construct(private PDO $pdo) {}

    public function decks(): array
    {
        $deckRows = $this->pdo
            ->query(
                "
            SELECT d.*, dv.code AS division_code, t.name AS team_name
                 , t.logo_url AS team_logo, t.short_code AS team_code, t.primary_color AS team_primary_color, t.secondary_color AS team_secondary_color, t.symbol AS team_symbol
            FROM sx_ygo_decks d
            LEFT JOIN sx_ygo_divisions dv ON dv.id = d.division_id AND dv.active = 1
            LEFT JOIN sx_ygo_teams t ON t.id = d.team_id
            WHERE d.active = 1
            ORDER BY COALESCE(dv.tier_order, 9999), d.seed_rank, d.name
        ",
            )
            ->fetchAll(PDO::FETCH_ASSOC);
        foreach ($deckRows as &$deck) {
            foreach (['team_logo', 'cover_url', 'logo_url'] as $field) {
                $deck[$field] = \ygo_media_src((string) ($deck[$field] ?: ''));
            }
        }
        unset($deck);
        return $deckRows;
    }

    public function teams(): array
    {
        return $this->pdo
            ->query('SELECT * FROM sx_ygo_teams WHERE active = 1 ORDER BY name')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function players(): array
    {
        return \ygo_table_exists($this->pdo, 'sx_ygo_players')
            ? $this->pdo
                ->query(
                    'SELECT p.*, t.name AS team_name FROM sx_ygo_players p LEFT JOIN sx_ygo_teams t ON t.id=p.team_id WHERE p.active=1 ORDER BY p.display_name',
                )
                ->fetchAll(PDO::FETCH_ASSOC)
            : [];
    }

    public function playerDecks(): array
    {
        return \ygo_table_exists($this->pdo, 'sx_ygo_player_decks')
            ? $this->pdo
                ->query('SELECT player_id, deck_id FROM sx_ygo_player_decks WHERE active=1')
                ->fetchAll(PDO::FETCH_ASSOC)
            : [];
    }

    public function cards(): array
    {
        return $this->pdo
            ->query('SELECT * FROM sx_ygo_deck_cards ORDER BY deck_id, section, id')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function media(): array
    {
        return $this->pdo
            ->query('SELECT * FROM sx_ygo_deck_media ORDER BY deck_id, id')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function logoHistory(): array
    {
        return $this->pdo
            ->query('SELECT * FROM sx_ygo_logo_history ORDER BY created_at DESC, id DESC')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function matchupAnalyses(): array
    {
        return \ygo_table_exists($this->pdo, 'sx_ygo_matchup_analyses')
            ? $this->pdo
                ->query(
                    'SELECT a.*, d.name AS deck_name, o.name AS opponent_name FROM sx_ygo_matchup_analyses a JOIN sx_ygo_decks d ON d.id=a.deck_id JOIN sx_ygo_decks o ON o.id=a.opponent_deck_id WHERE a.active=1 ORDER BY a.updated_at DESC, a.id DESC',
                )
                ->fetchAll(PDO::FETCH_ASSOC)
            : [];
    }

    public function matchupAnalysisHistory(): array
    {
        return \ygo_table_exists($this->pdo, 'sx_ygo_matchup_analysis_history')
            ? $this->pdo
                ->query(
                    'SELECT * FROM sx_ygo_matchup_analysis_history ORDER BY created_at DESC, id DESC LIMIT 2000',
                )
                ->fetchAll(PDO::FETCH_ASSOC)
            : [];
    }

    public function deckAnalysisProfiles(): array
    {
        return \ygo_table_exists($this->pdo, 'sx_ygo_deck_analysis_profiles')
            ? $this->pdo
                ->query(
                    'SELECT * FROM sx_ygo_deck_analysis_profiles ORDER BY updated_at DESC, deck_id',
                )
                ->fetchAll(PDO::FETCH_ASSOC)
            : [];
    }

    public function deckAnalysisProfileHistory(): array
    {
        return \ygo_table_exists($this->pdo, 'sx_ygo_deck_analysis_profile_history')
            ? $this->pdo
                ->query(
                    'SELECT * FROM sx_ygo_deck_analysis_profile_history ORDER BY created_at DESC, id DESC LIMIT 2000',
                )
                ->fetchAll(PDO::FETCH_ASSOC)
            : [];
    }
}
