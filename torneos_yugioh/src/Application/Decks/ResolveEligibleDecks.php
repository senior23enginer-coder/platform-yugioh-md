<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decks;

final readonly class ResolveEligibleDecks
{
    public function __construct(private CompetitionEligibilityReader $reader) {}

    public function execute(?int $competitionId, int $manualDivisionId): array
    {
        if (!$competitionId) {
            return $this->reader->activeDeckIds($manualDivisionId > 0 ? $manualDivisionId : null);
        }
        $competition = $this->reader->competition($competitionId);
        if (!$competition) {
            return [];
        }
        $admission = $this->reader->admissionSelection($competition);
        if ($admission !== null) {
            return $admission;
        }
        $pool = (string) (($competition['deck_pool'] ?? '') ?: 'all_active');
        $formatIds = $this->reader->formatDeckIds((string) $competition['event_ruleset']);
        $formatFilter = static fn(array $ids): array => array_values(
            array_intersect(array_map('intval', $ids), $formatIds),
        );
        $participantSource = strtoupper((string) (($competition['participant_source'] ?? '') ?: 'MANUAL'));
        $accessType = strtoupper((string) (($competition['access_type'] ?? '') ?: 'OPEN'));
        $manualDeckIds = $this->reader->registeredDeckIds($competitionId);
        $deckPolicy = strtoupper((string) (($competition['deck_policy'] ?? '') ?: 'OPTIONAL_REGISTRATION'));
        if (in_array($deckPolicy, ['REQUIRED_REGISTRATION', 'DECK_LOCK_EVENT', 'DECK_LOCK_PHASE', 'DECK_LOCK_ROUND'], true)) {
            return $formatFilter($manualDeckIds);
        }
        if ($manualDeckIds && in_array($participantSource, ['MANUAL', 'CUSTOM'], true)) {
            return $formatFilter($manualDeckIds);
        }
        if ($manualDeckIds && !in_array($pool, ['division', 'all_active'], true)) {
            return $formatFilter($manualDeckIds);
        }
        $divisionId = $competition['division_id'] !== null ? (int) $competition['division_id'] : $manualDivisionId;
        if (!$divisionId && !empty($competition['event_ruleset'])) {
            $divisionId = $this->reader->divisionForRuleset((string) $competition['event_ruleset']);
        }
        if ($pool === 'division') {
            return $formatIds;
        }
        if (
            in_array($pool, ['qualified', 'top_cut'], true) ||
            in_array($participantSource, ['PREVIOUS_EVENT', 'QUALIFIER', 'RANKING'], true) ||
            in_array($accessType, ['INVITATIONAL', 'QUALIFIER', 'LAST_CHANCE_QUALIFIER'], true)
        ) {
            $dependencyRows = $this->reader->dependencies($competitionId);
            if ($dependencyRows) {
                $qualifiedIds = [];
                foreach ($dependencyRows as $dependencyRow) {
                    $dependencyType = (string) ($dependencyRow['condition_type'] ?? 'completed_with_results');
                    $requiredId = (int) ($dependencyRow['depends_on_competition_id'] ?? 0);
                    if ($requiredId <= 0) {
                        continue;
                    }
                    if (in_array($dependencyType, ['top_n', 'winner_to_final', 'defending_champion'], true)) {
                        $take = $dependencyType === 'top_n' ? max(1, (int) ($dependencyRow['take_count'] ?? 0) ?: 4) : 1;
                        foreach (array_slice($this->reader->standings($requiredId), 0, $take) as $standingRow) {
                            $deckId = (int) ($standingRow['deck_id'] ?? 0);
                            if ($deckId > 0) {
                                $qualifiedIds[] = $deckId;
                            }
                        }
                    }
                }
                if ($qualifiedIds) {
                    return $formatFilter(array_values(array_unique($qualifiedIds)));
                }
                $requiredIds = array_values(array_unique(array_map(static fn(array $row): int => (int) $row['depends_on_competition_id'], $dependencyRows)));
                $qualifiedIds = $this->reader->participantsWithResults($requiredIds);
                if ($qualifiedIds) {
                    return $formatFilter($pool === 'top_cut' ? array_slice($qualifiedIds, 0, 24) : $qualifiedIds);
                }
            }
            if ($divisionId > 0) {
                return $this->reader->activeDeckIds($divisionId);
            }
        }
        return $formatIds;
    }
}
