<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Leagues;

use StationX\Yugioh\Application\Shared\TransactionBoundary;

final readonly class EnsureGoatLeague
{
    public function __construct(
        private GoatLeagueStore $store,
        private FormatCategoryStore $categories,
        private TransactionBoundary $transaction,
    ) {}

    public function execute(int $seasonId): array
    {
        if ($seasonId !== $this->store->legacySeasonId()) {
            return ['division_id' => 0, 'league_id' => 0, 'disabled_qualifiers' => 0];
        }
        if ($this->categories->managed($seasonId)) {
            $row = $this->store->division($seasonId);
            return [
                'division_id' => (int) ($row['id'] ?? 0),
                'league_id' => (int) ($row['league_competition_id'] ?? 0),
                'disabled_qualifiers' => 0,
            ];
        }
        return $this->transaction->run(function () use ($seasonId): array {
            $this->store->ensureDivision($seasonId);
            $division = $this->store->division($seasonId);
            $divisionId = (int) ($division['id'] ?? 0);
            $linkedLeagueId = (int) ($division['league_competition_id'] ?? 0);
            $leagueId = $this->store->leagueId($seasonId);
            $linked = 0;
            if ($divisionId > 0 && $leagueId > 0 && $linkedLeagueId <= 0) {
                $linked = $this->store->link($seasonId, $divisionId, $leagueId);
            }
            $qualifierIds = $this->store->qualifierIds($seasonId);
            if ($qualifierIds) {
                $this->store->disableQualifiers($qualifierIds);
            }
            if ($linked > 0 || $qualifierIds) {
                $this->store->audit($divisionId, $leagueId, $qualifierIds);
            }
            return [
                'division_id' => $divisionId,
                'league_id' => $leagueId,
                'disabled_qualifiers' => count($qualifierIds),
            ];
        });
    }
}
