<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

final class CompetitionConfigurationPolicy
{
    public static function rosterLocked(
        bool $hasFixtures,
        array $competition,
        string $currentStatusKey,
        string $competitionType,
    ): bool {
        return $hasFixtures ||
            !empty($competition['configuration_locked']) ||
            in_array(
                $currentStatusKey,
                [
                    'completed',
                    'finalizado',
                    'finalizada',
                    'cancelled',
                    'canceled',
                    'anulado',
                    'anulada',
                ],
                true,
            ) ||
            (!in_array($competitionType, ['cup', 'tournament', 'final'], true) &&
                (!empty($competition['configuration_locked']) ||
                    in_array($currentStatusKey, ['active', 'activo'], true)));
    }

    public static function readiness(
        string $competitionType,
        array $rosterGroups,
        int $selectedDeckCount,
        int $participantsPerSide,
        int $qualifierDuelsPerDeck,
        ?int $divisionId,
    ): array {
        $groupCount = count($rosterGroups);
        $maxQualifierDuels = max(0, $groupCount - 1);
        if (
            $competitionType === 'qualifier' &&
            $maxQualifierDuels >= 2 &&
            $qualifierDuelsPerDeck > $maxQualifierDuels
        ) {
            $qualifierDuelsPerDeck = $maxQualifierDuels;
        }
        $minimumDecks =
            $competitionType === 'qualifier'
                ? max(4, $participantsPerSide * 2)
                : $participantsPerSide * 2;
        $hasEnoughDecks = $selectedDeckCount >= $minimumDecks;
        $hasEvenTournamentRoster = $selectedDeckCount >= $participantsPerSide * 2;
        $hasValidQualifierDuelCount =
            $competitionType !== 'qualifier' ||
            ($maxQualifierDuels >= 2 && $qualifierDuelsPerDeck <= $maxQualifierDuels);
        $hasRequiredDivision =
            $competitionType !== 'league' || ($divisionId !== null && $divisionId > 0);
        $canRunCompetition =
            $hasRequiredDivision &&
            $hasEnoughDecks &&
            $groupCount >= 2 &&
            $hasValidQualifierDuelCount &&
            $hasEvenTournamentRoster;
        return [
            'qualifierDuelsPerDeck' => $qualifierDuelsPerDeck,
            'canRunCompetition' => $canRunCompetition,
        ];
    }

    public static function transition(
        string $postedStatus,
        string $competitionType,
        bool $canRunCompetition,
        bool $canStartCompetition,
        string $mode,
        int $scheduledQualifierMatches,
    ): array {
        $status = $postedStatus;
        if ($postedStatus === 'cancelled') {
            $status = 'cancelled';
        } elseif ($competitionType === 'qualifier' && !$canRunCompetition) {
            $status = 'pending_configuration';
        }
        if ($status === 'cancelled') {
            $mode = 'save';
        } elseif (
            !$canRunCompetition &&
            in_array($mode, ['start', 'schedule_qualifier', 'schedule_cup'], true)
        ) {
            $status = 'pending_configuration';
        } elseif ($mode === 'start' && $canStartCompetition) {
            $status = 'active';
        } elseif ($mode === 'start' && $competitionType === 'qualifier' && !$canStartCompetition) {
            $status = $scheduledQualifierMatches > 0 ? 'pending_event' : 'pending_configuration';
        } elseif (
            $mode === 'schedule_qualifier' &&
            $competitionType === 'qualifier' &&
            $canRunCompetition
        ) {
            $status = 'pending_event';
        } elseif (
            $mode === 'schedule_cup' &&
            in_array($competitionType, ['cup', 'final'], true) &&
            $canRunCompetition &&
            !in_array(
                CompetitionLifecyclePolicy::normalizeStatus($postedStatus),
                ['active', 'activo'],
                true,
            )
        ) {
            $status = 'pending_event';
        }
        return ['status' => $status, 'mode' => $mode];
    }
}
