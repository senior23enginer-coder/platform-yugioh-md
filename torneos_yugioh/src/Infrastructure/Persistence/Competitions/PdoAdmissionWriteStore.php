<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\AdmissionWriteStore;

final readonly class PdoAdmissionWriteStore implements AdmissionWriteStore
{
    public function __construct(private PDO $pdo) {}

    public function lockedCompetition(int $target): array
    {
        $q = $this->pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id=? AND active=1 FOR UPDATE');
        $q->execute([$target]);
        return $q->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function requireOpen(int $seasonId): void
    {
        \ygo_season_require_open($this->pdo, $seasonId);
    }

    public function assertMutable(int $target): void
    {
        \ygo_assert_fixture_mutable($this->pdo, $target);
    }

    public function normalizedStatus(string $status): string
    {
        return \ygo_normalize_status_key($status);
    }

    public function assertNoCycle(int $target, array $sources): void
    {
        \ygo_admission_assert_no_cycle($this->pdo, $target, $sources);
    }

    public function resolve(array $competition, array $admission): array
    {
        return \ygo_admission_resolve($this->pdo, $competition, $admission, true);
    }

    public function validateRoster(int $target, array $ids): void
    {
        \ygo_validate_league_roster($this->pdo, $target, $ids);
    }

    public function replaceDecks(int $target, array $ids): void
    {
        $this->pdo->prepare('UPDATE sx_ygo_competition_decks SET active=0 WHERE competition_id=?')->execute([$target]);
        foreach ($ids as $i => $id) {
            $this->pdo->prepare('INSERT INTO sx_ygo_competition_decks(competition_id,deck_id,sort_order,active) VALUES(?,?,?,1) ON DUPLICATE KEY UPDATE sort_order=VALUES(sort_order),active=1')->execute([$target,$id,$i+1]);
        }
    }

    public function clearDependencies(int $target): void
    {
        $this->pdo->prepare('UPDATE sx_ygo_competition_dependencies SET active=0 WHERE competition_id=?')->execute([$target]);
    }

    public function writeDependencies(array $competition, int $target, array $dependencies): void
    {
        foreach ($dependencies as $sid => $take) {
            $this->pdo->prepare("INSERT INTO sx_ygo_competition_dependencies(season_id,competition_id,depends_on_competition_id,condition_type,take_count,active) VALUES(?,?,?,'completed_with_results',?,1) ON DUPLICATE KEY UPDATE condition_type=VALUES(condition_type),take_count=VALUES(take_count),active=1")->execute([$competition['season_id'],$target,$sid,$take]);
        }
    }

    public function setParticipantSource(int $target): void
    {
        $this->pdo->prepare("UPDATE sx_ygo_competitions SET participant_source='PREVIOUS_EVENT',deck_pool='all_active' WHERE id=?")->execute([$target]);
    }

    public function store(array $competition, array $admission): void
    {
        \ygo_admission_store($this->pdo, $competition, $admission);
    }

    public function refreshGate(int $target): void
    {
        \ygo_competition_gate($this->pdo, $target, true);
    }

    public function audit(int $target, array $admission): void
    {
        $this->pdo->prepare('INSERT INTO sx_ygo_audit_log(entity_type,entity_id,action,payload) VALUES(?,?,?,?)')->execute(['competition',$target,'save_event_admission',json_encode($admission,JSON_UNESCAPED_UNICODE)]);
    }
}
