<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

final class QualifierFinalizationPolicy
{
    public static function requiredMatches(mixed $value): int
    {
        return max(2, min(5, (int) ($value ?: 3)));
    }

    public static function roundReasons(array $matches): array
    {
        if (!$matches) {
            return ['No hay combates clasificatorios programados.'];
        }
        $rounds = [];
        $reasons = [];
        foreach ($matches as $match) {
            $round = (int) $match['round_number'];
            $rounds[$round][] = (string) $match['status'];
        }
        if (empty($rounds[1])) {
            $reasons[] = 'No existe una primera ronda programada.';
        } elseif (
            array_filter($rounds[1], static fn(string $status): bool => $status !== 'FINALIZADA')
        ) {
            $reasons[] = 'No se puede finalizar: toda la primera ronda debe estar jugada.';
        }
        foreach ($rounds as $round => $statuses) {
            $finished = count(
                array_filter(
                    $statuses,
                    static fn(string $status): bool => $status === 'FINALIZADA',
                ),
            );
            if ($finished > 0 && $finished < count($statuses)) {
                $reasons[] = 'No se puede finalizar: la ronda ' . $round . ' esta en curso.';
            }
        }
        return $reasons;
    }

    public static function result(array $reasons, array $standings, int $requiredMatches): array
    {
        if (
            !$standings ||
            array_filter(
                $standings,
                static fn(array $row): bool => (int) $row['pj'] < $requiredMatches,
            )
        ) {
            $reasons[] =
                'Cada deck debe tener minimo ' . $requiredMatches . ' combates finalizados.';
        }
        return ['ok' => !$reasons, 'reasons' => array_values(array_unique($reasons))];
    }

    public static function divisions(array $official, array $standings): iterable
    {
        if (!$official) {
            return;
        }
        $total = count($standings);
        $base = intdiv($total, count($official));
        $extra = $total % count($official);
        $offset = 0;
        foreach ($official as $index => $division) {
            $size = $base + ($index < $extra ? 1 : 0);
            $slice = array_slice($standings, $offset, $size);
            $assignments = [];
            foreach ($slice as $localRank => $deck) {
                $assignments[] = ['deckId' => (int) $deck['id'], 'rank' => $localRank + 1];
            }
            yield [
                'divisionId' => (int) $division['id'],
                'size' => $size,
                'assignments' => $assignments,
            ];
            $offset += $size;
        }
    }
}
