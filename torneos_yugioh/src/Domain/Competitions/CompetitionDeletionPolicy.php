<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

final class CompetitionDeletionPolicy
{
    public static function confirmed(int $competitionId, string $confirmation): bool
    {
        return $competitionId > 0 && strtolower(trim($confirmation)) === 'eliminar';
    }

    public static function deletableStatus(array $competition): ?string
    {
        if (!$competition) {
            return null;
        }
        $status = CompetitionLifecyclePolicy::normalizeStatus((string) ($competition['status'] ?? ''));
        return in_array($status, ['active', 'completed', 'cancelled'], true) ? null : $status;
    }
}
