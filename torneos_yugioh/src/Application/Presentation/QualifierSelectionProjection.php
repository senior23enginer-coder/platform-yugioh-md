<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Presentation;

final class QualifierSelectionProjection
{
    public static function select(
        array $competitions,
        int $requestedId,
        callable $isPending,
        callable $normalizeStatus,
    ): array {
        $qualifierCompetition = null;
        $qualifierCompetitions = [];
        foreach ($competitions as $competition) {
            if (($competition['type'] ?? '') === 'qualifier') {
                $qualifierCompetitions[] = $competition;
                if ($qualifierCompetition === null) {
                    $qualifierCompetition = $competition;
                }
            }
        }
        $pendingEventQualifiers = array_values(array_filter($qualifierCompetitions, $isPending));
        $activeQualifierCompetition = null;
        foreach ($qualifierCompetitions as $competition) {
            $statusKey = $normalizeStatus((string) ($competition['status'] ?? ''));
            if (
                in_array($statusKey, ['active', 'activo', 'en_curso'], true) &&
                ($competition['can_execute'] ?? true) !== false
            ) {
                $activeQualifierCompetition = $competition;
                break;
            }
        }
        $requestedQualifierId = max(0, $requestedId);
        $requestedQualifierCompetition = null;
        foreach ($qualifierCompetitions as $competition) {
            if ((int) ($competition['id'] ?? 0) === $requestedQualifierId) {
                $requestedQualifierCompetition = $competition;
                break;
            }
        }
        $showQualifierDetail = $requestedQualifierCompetition !== null;
        $qualifierCompetitionIdMap = array_fill_keys(
            array_map(
                static fn(array $competition): int => (int) ($competition['id'] ?? 0),
                $qualifierCompetitions,
            ),
            true,
        );
        return [
            $qualifierCompetition,
            $qualifierCompetitions,
            $pendingEventQualifiers,
            $activeQualifierCompetition,
            $requestedQualifierId,
            $requestedQualifierCompetition,
            $showQualifierDetail,
            $qualifierCompetitionIdMap,
        ];
    }

    public static function display(
        ?array $requestedQualifierCompetition,
        ?array $activeQualifierCompetition,
        ?array $qualifierCompetition,
        array $allDecks,
    ): array {
        $displayQualifierCompetition =
            $requestedQualifierCompetition ??
            ($activeQualifierCompetition ?? $qualifierCompetition);
        $displayQualifierId = (int) ($displayQualifierCompetition['id'] ?? 0);
        $displayQualifierSelectedIds = array_map(
            'intval',
            (array) ($displayQualifierCompetition['selected_deck_ids'] ?? []),
        );
        $displayQualifierSelectedMap = array_fill_keys($displayQualifierSelectedIds, true);
        $displayQualifierDecks = $displayQualifierSelectedIds
            ? array_values(
                array_filter(
                    $allDecks,
                    fn(array $deck): bool => isset(
                        $displayQualifierSelectedMap[(int) ($deck['id'] ?? 0)],
                    ),
                ),
            )
            : [];
        $displayQualifierRequiredDuels = max(
            2,
            min(5, (int) ($displayQualifierCompetition['qualifier_duels_per_deck'] ?? 0 ?: 3)),
        );
        $displayQualifierMatchesPerDay = max(
            1,
            min(5, (int) ($displayQualifierCompetition['qualifier_matches_per_day'] ?? 0 ?: 1)),
        );
        return [
            $displayQualifierCompetition,
            $displayQualifierId,
            $displayQualifierSelectedIds,
            $displayQualifierSelectedMap,
            $displayQualifierDecks,
            $displayQualifierRequiredDuels,
            $displayQualifierMatchesPerDay,
        ];
    }

    public static function status(
        ?array $displayQualifierCompetition,
        callable $normalizeStatus,
    ): array {
        $qualifierStatus = $normalizeStatus(
            (string) ($displayQualifierCompetition['status'] ?? 'draft'),
        );
        $qualifierFinished = in_array(
            $qualifierStatus,
            ['completed', 'finalizada', 'finalizado', 'closed', 'archived'],
            true,
        );
        $qualifierStatusLabel = $qualifierFinished
            ? 'Finalizado'
            : ($qualifierStatus === 'pending_event'
                ? 'Pendiente evento'
                : (in_array($qualifierStatus, ['active', 'activo', 'en_curso'], true)
                    ? 'Activo'
                    : 'Pendiente configuracion'));
        $divisionModeLabel = $qualifierFinished
            ? 'Formatos oficiales'
            : 'Formatos pendientes del clasificatorio';
        return [$qualifierStatus, $qualifierFinished, $qualifierStatusLabel, $divisionModeLabel];
    }
}
