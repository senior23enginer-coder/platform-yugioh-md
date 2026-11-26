<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Teams;

use PDO;
use StationX\Yugioh\Application\Teams\TeamEventConfigurationStore;

final readonly class PdoTeamEventConfigurationStore implements TeamEventConfigurationStore
{
    public function __construct(private PDO $pdo) {}

    public function loadJson(array $competition): ?string
    {
        $stmt = $this->pdo->prepare('SELECT setting_value FROM sx_ygo_settings WHERE season_id=? AND setting_key=?');
        $stmt->execute([$competition['season_id'], 'event_teams_' . $competition['id']]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : (string) $value;
    }

    public function storeJson(array $competition, string $json): void
    {
        $this->pdo->prepare('INSERT INTO sx_ygo_settings(season_id,setting_key,setting_value) VALUES(?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')
            ->execute([$competition['season_id'], 'event_teams_' . $competition['id'], $json]);
    }

    public function lockCompetition(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id=? AND active=1 FOR UPDATE');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function replaceRoster(int $id, array $ids): void
    {
        $this->pdo->prepare('UPDATE sx_ygo_competition_decks SET active=0 WHERE competition_id=?')->execute([$id]);
        foreach ($ids as $i => $deck) {
            $this->pdo->prepare('INSERT INTO sx_ygo_competition_decks(competition_id,deck_id,sort_order,active) VALUES(?,?,?,1) ON DUPLICATE KEY UPDATE sort_order=VALUES(sort_order),active=1')
                ->execute([$id, $deck, $i + 1]);
        }
    }

    public function audit(int $id, array $state): void
    {
        $this->pdo->prepare('INSERT INTO sx_ygo_audit_log(entity_type,entity_id,action,payload) VALUES(?,?,?,?)')
            ->execute(['competition', $id, 'save_team_lineups', json_encode($state, JSON_UNESCAPED_UNICODE)]);
    }

    public function bindMatch(int $competitionId, int $matchId, array $binding): void
    {
        $this->pdo->prepare('UPDATE sx_ygo_matches SET match_format=?,participants_per_side=?,deck_c_id=?,deck_d_id=?,deck_e_id=?,deck_f_id=? WHERE id=? AND competition_id=?')
            ->execute([...$binding, $matchId, $competitionId]);
    }
}
