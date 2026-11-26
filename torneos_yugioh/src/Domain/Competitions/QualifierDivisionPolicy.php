<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

final class QualifierDivisionPolicy
{
    public static function range(array $division): ?array
    {
        $qualifierId = (int) ($division['source_qualifier_id'] ?? 0);
        $rankFrom = (int) ($division['rank_from'] ?? 0);
        $rankTo = (int) ($division['rank_to'] ?? 0);
        if ($qualifierId <= 0 || $rankFrom <= 0 || $rankTo <= 0) {
            return null;
        }
        if ($rankTo < $rankFrom) {
            [$rankFrom, $rankTo] = [$rankTo, $rankFrom];
        }
        return [$qualifierId, $rankFrom, $rankTo];
    }

    public static function completed(string $status): bool
    {
        return in_array(
            CompetitionLifecyclePolicy::normalizeStatus($status),
            ['completed', 'finalizado', 'finalizada'],
            true,
        );
    }

    public static function assignments(array $slice): iterable
    {
        foreach ($slice as $index => $row) {
            $deckId = (int) ($row['deck_id'] ?? $row['id'] ?? 0);
            if ($deckId <= 0) {
                continue;
            }
            yield [$deckId, $index + 1];
        }
    }
}
