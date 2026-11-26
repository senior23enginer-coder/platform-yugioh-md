<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

use InvalidArgumentException;
use StationX\Yugioh\Domain\Matches\CompletionPolicy;

final class TeamResultPolicy
{
    public static function currentMatch(?array $state, ?array $workflow, int $matchId): array
    {
        if ($state !== ($workflow['teams'] ?? null)) {
            throw new InvalidArgumentException('Las alineaciones no coinciden con el reglamento fijado.');
        }
        $match = null;
        foreach ($workflow['pending'] ?? [] as $candidate) {
            if ($candidate['match_id'] === $matchId) {
                $match = $candidate;
            }
        }
        if (!$state || !$state['frozen'] || !$match) {
            throw new InvalidArgumentException('Solo se registran resultados de la fase actual y su alineacion fijada.');
        }
        return $match;
    }

    public static function assertSeats(array $match, array $left, array $right): void
    {
        foreach (['a', 'c', 'e'] as $i => $slot) {
            if ((int) ($match['deck_' . $slot . '_id'] ?? 0) !== (int) ($left['decks'][$i] ?? 0)) {
                throw new InvalidArgumentException('Cambio la alineacion del lado A.');
            }
        }
        foreach (['b', 'd', 'f'] as $i => $slot) {
            if ((int) ($match['deck_' . $slot . '_id'] ?? 0) !== (int) ($right['decks'][$i] ?? 0)) {
                throw new InvalidArgumentException('Cambio la alineacion del lado B.');
            }
        }
    }

    public static function assertRevision(?array $old, int $revision): void
    {
        if (($old['revision'] ?? 0) !== $revision) {
            throw new InvalidArgumentException('Otro registro actualizo este resultado. Recarga antes de guardar.');
        }
    }

    public static function record(array $state, array $left, array $right, array $sequences, array $evaluations, array $aggregate, ?array $old, int $revision, string $status, string $now): array
    {
        $record = [
            'version' => 'STATION_X_TEAMS_V1',
            'revision' => $revision + 1,
            'kind' => $state['kind'],
            'lineup_a' => $left,
            'lineup_b' => $right,
            'sequences' => $sequences,
            'evaluations' => $evaluations,
            'aggregate' => $aggregate,
            'identities' => $state['identities'] ?? [],
        ];
        foreach ($sequences as $seat => $sequence) {
            $record['game_details'][$seat] = CompletionPolicy::completionGames([], $old['game_details'][$seat] ?? [], $old['sequences'][$seat] ?? [], $sequence, $now);
        }
        $record['completed_at'] = $old['completed_at'] ?? ($status !== 'FINALIZADA' ? $now : null);
        return $record;
    }

    public static function collectiveDuels(string $kind, array $sequences): array
    {
        return $kind === 'tag' ? array_pad($sequences[0], 5, null) : array_fill(0, 5, null);
    }
}
