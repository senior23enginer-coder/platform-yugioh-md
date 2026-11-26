<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

use InvalidArgumentException;

final class PhaseAdvancePolicy
{
    public static function finished(array $row): bool
    {
        $total = (int) ($row['total'] ?? 0);
        return $total > 0 && $total === (int) ($row['closed'] ?? 0);
    }

    public static function winners(array $rows): array
    {
        $winners = [];
        foreach ($rows as $row) {
            $winner = $row['result'] === 'A'
                ? (int) $row['deck_a_id']
                : ($row['result'] === 'B' ? (int) $row['deck_b_id'] : 0);
            if ($winner <= 0) {
                throw new InvalidArgumentException('La llave requiere un ganador valido en todos sus combates; no se avanzara con empate.');
            }
            $winners[] = $winner;
        }
        return $winners;
    }

    public static function blockedQualifier(array $competition, string $type, string $execution, string $phaseStructure): bool
    {
        return $type === 'qualifier'
            && $execution === 'swiss'
            && $phaseStructure === 'NONE'
            && strtoupper((string) (($competition['event_flow'] ?? '') ?: '')) === 'QUALIFIES_TO_EVENT'
            && strtoupper((string) (($competition['advancement_rule'] ?? '') ?: '')) === 'RANKING_THRESHOLD';
    }

    public static function requireOpenStatus(array $competition): void
    {
        if (in_array(CompetitionLifecyclePolicy::normalizeStatus((string) $competition['status']), ['completed', 'closed', 'cancelled', 'archived'], true)) {
            throw new InvalidArgumentException('El evento esta cerrado y no admite avance.');
        }
    }
}
