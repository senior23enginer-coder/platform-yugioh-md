<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Decks;

final class MatchDeckPolicy
{
    /** Returns the normalized selection or the original validation error. */
    public static function selection(array $allowed, array $deckIds): array
    {
        if (!$allowed) {
            return [
                'ok' => false,
                'message' => 'No hay decks permitidos para el formato del evento.',
            ];
        }
        $allowedSet = array_fill_keys(array_map('strval', $allowed), true);
        $cleanDeckIds = [];
        foreach ($deckIds as $deckId) {
            if ($deckId !== null && $deckId > 0 && !isset($allowedSet[(string) $deckId])) {
                return [
                    'ok' => false,
                    'message' => 'Hay decks que no pertenecen al formato/pool del evento.',
                ];
            }
            if ($deckId !== null && $deckId > 0) {
                $cleanDeckIds[] = (int) $deckId;
            }
        }
        if (count($cleanDeckIds) !== count(array_unique($cleanDeckIds))) {
            return ['ok' => false, 'message' => 'No se puede repetir un deck en el mismo match.'];
        }
        if (!$cleanDeckIds) {
            return ['ok' => false, 'message' => 'Debes seleccionar decks para crear el match.'];
        }
        return ['ok' => true, 'ids' => $cleanDeckIds];
    }

    public static function validateRows(
        array $rows,
        array $cleanDeckIds,
        array $deckIds,
        int $manualDivisionId,
        int $participantsPerSide,
    ): array {
        if (count($rows) !== count($cleanDeckIds)) {
            return ['ok' => false, 'message' => 'Uno o mas decks no existen o estan inactivos.'];
        }
        $byId = [];
        foreach ($rows as $row) {
            if (empty($row['team_id'])) {
                return ['ok' => false, 'message' => 'Todo deck debe pertenecer a un equipo.'];
            }
            $byId[(int) $row['id']] = $row;
        }
        $divisionIds = array_unique(
            array_map(static fn(array $row): int => (int) $row['division_id'], $rows),
        );
        if (
            count($divisionIds) > 1 ||
            ($manualDivisionId > 0 && (int) $divisionIds[0] !== $manualDivisionId)
        ) {
            return [
                'ok' => false,
                'message' =>
                    'Todos los decks del match deben pertenecer al mismo formato del evento.',
            ];
        }
        $participantsPerSide = max(1, min(3, $participantsPerSide));
        $sideA = array_values(
            array_filter(
                [$deckIds[0] ?? null, $deckIds[2] ?? null, $deckIds[4] ?? null],
                static fn($id): bool => $id !== null && (int) $id > 0,
            ),
        );
        $sideB = array_values(
            array_filter(
                [$deckIds[1] ?? null, $deckIds[3] ?? null, $deckIds[5] ?? null],
                static fn($id): bool => $id !== null && (int) $id > 0,
            ),
        );
        if (count($sideA) !== $participantsPerSide || count($sideB) !== $participantsPerSide) {
            return [
                'ok' => false,
                'message' => "El match {$participantsPerSide}v{$participantsPerSide} requiere {$participantsPerSide} decks por lado.",
            ];
        }
        if ($participantsPerSide > 1) {
            $teamA = (int) $byId[(int) $sideA[0]]['team_id'];
            $teamB = (int) $byId[(int) $sideB[0]]['team_id'];
            foreach ($sideA as $deckId) {
                if ((int) $byId[(int) $deckId]['team_id'] !== $teamA) {
                    return [
                        'ok' => false,
                        'message' =>
                            'En 2v2/3v3 todos los decks del lado A deben ser del mismo equipo.',
                    ];
                }
            }
            foreach ($sideB as $deckId) {
                if ((int) $byId[(int) $deckId]['team_id'] !== $teamB) {
                    return [
                        'ok' => false,
                        'message' =>
                            'En 2v2/3v3 todos los decks del lado B deben ser del mismo equipo.',
                    ];
                }
            }
            if ($teamA === $teamB) {
                return [
                    'ok' => false,
                    'message' => 'En 2v2/3v3 los dos lados deben ser equipos distintos.',
                ];
            }
        }
        return ['ok' => true, 'message' => 'OK'];
    }
}
