<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

use StationX\Yugioh\Domain\Competitions\CompetitionLifecyclePolicy;
use StationX\Yugioh\Domain\Competitions\CompetitionMaintenancePolicy;

final readonly class MaintainCompetitions
{
    public function __construct(private CompetitionMaintenanceStore $store, private CompetitionMaintenanceServices $services) {}

    public function archive(array $post): string
    {
        $id = (int) ($post['competition_id'] ?? 0);
        if ($id > 0) {
            $this->store->archive($id);
        }
        return 'evento_archivado';
    }

    public function qualifierStatus(array $post): string
    {
        $seasonId = $this->store->currentSeasonId();
        $postedId = (int) ($post['qualifier_competition_id'] ?? 0);
        $id = $this->services->selectedQualifier($postedId);
        $status = CompetitionMaintenancePolicy::qualifierStatus($post);
        if ($status === 'completed' && !$this->services->finalizeQualifier($id, $postedId)) {
            return 'clasificatorio_incompleto';
        }
        if ($id) {
            $from = $this->store->qualifierStatus($id, $seasonId);
            $this->store->setQualifierStatus($id, $seasonId, $status);
            $this->services->history($seasonId, $id, $from, $status, 'set_qualifier_status');
        }
        return 'clasificatorio_' . $status;
    }

    public function recalculate(array $post): string
    {
        $id = (int) ($post['competition_id'] ?? 0);
        $seasonId = $this->store->currentSeasonId();
        $exists = $id > 0 && $this->store->rankingExists($id, $seasonId);
        if ($exists) {
            $this->services->standings($seasonId, $id, 'manual_recalculate');
            $this->store->audit($id, 'recalculate_standings', ['source' => 'manual_button']);
        }
        return 'ranking_recalculado_' . $id . '_' . ($exists ? 1 : 0);
    }

    private function applyDivisions(int $id, int $seasonId): int
    {
        $moves = 0;
        foreach ($this->store->qualifierDivisions($id, $seasonId) as $division) {
            $moves += $this->services->applyDivision($division);
        }
        return $moves;
    }

    public function qualifierDivisions(array $post): string
    {
        $id = (int) ($post['competition_id'] ?? 0);
        $seasonId = $this->store->currentSeasonId();
        if (!$this->store->typeExists($id, $seasonId, 'qualifier')) {
            return 'divisiones_aplicadas_' . $id . '_0';
        }
        $moves = $this->applyDivisions($id, $seasonId);
        $this->store->audit($id, 'apply_qualifier_divisions', ['moved' => $moves]);
        return 'divisiones_aplicadas_' . $id . '_' . $moves;
    }

    public function leagueMovements(array $post): string
    {
        $id = (int) ($post['competition_id'] ?? 0);
        $seasonId = $this->store->currentSeasonId();
        $moves = $this->store->typeExists($id, $seasonId, 'league') ? $this->services->leagueMovements($id) : 0;
        return 'movimientos_liga_' . $id . '_' . $moves;
    }

    public function finalize(array $post): string
    {
        $id = (int) ($post['competition_id'] ?? 0);
        $seasonId = $this->store->currentSeasonId();
        $competition = $this->store->competition($id, $seasonId);
        if ($competition) {
            $status = CompetitionLifecyclePolicy::normalizeStatus((string) ($competition['status'] ?? ''));
            $ready = $this->services->readiness($id);
            if (CompetitionMaintenancePolicy::canFinalize($status, $ready)) {
                $this->store->complete($id, $seasonId);
                $this->services->standings($seasonId, $id, 'finalize_competition');
                $divisionMoves = $this->applyDivisions($id, $seasonId);
                $leagueMoves = $this->services->leagueMovements($id);
                $honors = $this->services->honor($id);
                $this->services->history($seasonId, $id, (string) ($competition['status'] ?? ''), 'completed', 'finalize_competition', $ready);
                $this->store->audit($id, 'finalize_competition', $ready + ['division_moves' => $divisionMoves, 'league_moves' => $leagueMoves, 'honors' => $honors]);
            } else {
                $this->store->audit($id, 'blocked_finalize_competition', $ready + ['status' => $status]);
            }
        }
        return 'evento_configurado_' . $id;
    }
}
