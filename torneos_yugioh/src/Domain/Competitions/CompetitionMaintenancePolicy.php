<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

final class CompetitionMaintenancePolicy
{
    public static function qualifierStatus(array $post): string
    {
        $status = (string) ($post['status'] ?? 'active');
        return in_array($status, ['draft', 'active', 'completed'], true) ? $status : 'active';
    }

    public static function canFinalize(string $status, array $ready): bool
    {
        return $ready['ok'] &&
            in_array($status, ['active', 'activo', 'pending_event', 'pendiente_evento'], true);
    }
}
