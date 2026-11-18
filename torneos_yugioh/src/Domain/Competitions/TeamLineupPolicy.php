<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

use InvalidArgumentException;

/** Validates team lineups and aggregates their existing match results. */
final class TeamLineupPolicy
{
    public static function lineups(array $rows, string $kind): array
    {
        if (!in_array($kind, ['team', 'tag'], true) || count($rows) < 2 || count($rows) > 64) {
            throw new InvalidArgumentException('Configura entre 2 y 64 equipos.');
        }
        $size = $kind === 'tag' ? 2 : 3;
        $seen = [];
        $names = [];
        $out = [];
        foreach (array_values($rows) as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('Alineacion invalida.');
            }
            if (!is_string($row['name'] ?? null)) {
                throw new InvalidArgumentException('El nombre del equipo debe ser texto.');
            }
            $name = trim($row['name']);
            $ids = $row['decks'] ?? [];
            if ($name === '' || mb_strlen($name) > 80 || isset($names[mb_strtolower($name)])) {
                throw new InvalidArgumentException(
                    'Cada equipo necesita un nombre diferente de hasta 80 caracteres.',
                );
            }
            $names[mb_strtolower($name)] = true;
            if (!is_array($ids) || count($ids) !== $size) {
                throw new InvalidArgumentException(
                    'Cada equipo necesita exactamente ' . $size . ' decks y puestos fijos.',
                );
            }
            $clean = [];
            foreach ($ids as $id) {
                $id = filter_var($id, FILTER_VALIDATE_INT);
                if (!$id || $id < 1 || isset($seen[$id])) {
                    throw new InvalidArgumentException(
                        'Un deck solo puede ocupar un puesto en un equipo del evento.',
                    );
                }
                $seen[$id] = true;
                $clean[] = $id;
            }
            $out[] = ['name' => $name, 'decks' => $clean, 'captain' => $clean[0]];
        }
        return [
            'version' => 'STATION_X_TEAMS_V1',
            'kind' => $kind,
            'size' => $size,
            'lineups' => $out,
            'frozen' => false,
        ];
    }

    public static function allIds(array $s): array
    {
        return array_merge(...array_column($s['lineups'], 'decks'));
    }

    public static function lineup(array $s, int $captain): array
    {
        foreach ($s['lineups'] as $row) {
            if ($row['captain'] === $captain) {
                return $row;
            }
        }
        throw new InvalidArgumentException('El combate no corresponde a una alineacion guardada.');
    }

    public static function aggregate(string $kind, array $evaluations, bool $drawAllowed): array
    {
        if (count($evaluations) !== ($kind === 'tag' ? 1 : 3)) {
            throw new InvalidArgumentException(
                'Registra todos los resultados por puesto de la modalidad.',
            );
        }
        $wins = ['A' => 0, 'B' => 0, 'E' => 0];
        foreach ($evaluations as $e) {
            if (($e['status'] ?? '') !== 'FINALIZADA' || !isset($wins[$e['winner'] ?? ''])) {
                throw new InvalidArgumentException(
                    'Cada puesto debe tener una secuencia completa y valida.',
                );
            }
            $wins[$e['winner']]++;
        }
        $winner =
            $kind === 'tag'
                ? $evaluations[0]['winner']
                : ($wins['A'] >= 2
                    ? 'A'
                    : ($wins['B'] >= 2
                        ? 'B'
                        : 'E'));
        if ($winner === 'E' && !$drawAllowed) {
            throw new InvalidArgumentException(
                'Esta fase requiere ganador colectivo. Resuelve los empates por puesto antes de avanzar.',
            );
        }
        return [
            'winner' => $winner,
            'score' => $kind === 'tag' ? $evaluations[0]['score'] : $wins['A'] . '-' . $wins['B'],
            'status' => 'FINALIZADA',
        ];
    }
}
