<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Teams;

use PDO;
use StationX\Yugioh\Application\Teams\TeamIndividualReader;

final readonly class PdoTeamIndividualReader implements TeamIndividualReader
{
    public function __construct(private PDO $pdo) {}

    public function teamMatchIds(): array
    {
        $teamIds = [];
        foreach (
            $this->pdo
                ->query(
                    "SELECT m.id FROM sx_ygo_matches m JOIN sx_ygo_settings s ON s.setting_key=CONCAT('event_teams_',m.competition_id) JOIN sx_ygo_competitions c ON c.id=m.competition_id AND c.season_id=s.season_id",
                )
                ->fetchAll(PDO::FETCH_COLUMN)
            as $id
        ) {
            $teamIds[(int) $id] = true;
        }
        return $teamIds;
    }

    public function records(): array
    {
        $records = [];
        foreach (
            $this->pdo
                ->query(
                    "SELECT setting_key,setting_value FROM sx_ygo_settings WHERE setting_key LIKE 'event_team_result_%'",
                )
                ->fetchAll(PDO::FETCH_ASSOC)
            as $r
        ) {
            $records[(int) substr($r['setting_key'], 18)] = json_decode($r['setting_value'], true);
        }
        return $records;
    }

    public function decks(): array
    {
        $decks = [];
        foreach (
            $this->pdo
                ->query(
                    'SELECT d.id,d.name,d.archetype,d.variations,d.team_id,t.name team_name FROM sx_ygo_decks d LEFT JOIN sx_ygo_teams t ON t.id=d.team_id',
                )
                ->fetchAll(PDO::FETCH_ASSOC)
            as $d
        ) {
            $decks[(int) $d['id']] = $d;
        }
        return $decks;
    }
}
