<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Presentation;

final class AuditSummaryProjection
{
    public static function build(array $audit): array
    {
        $auditSummary = [];
        foreach ($audit as $auditRow) {
            $action = (string) ($auditRow['action'] ?? 'accion');
            $auditSummary[$action] = ($auditSummary[$action] ?? 0) + 1;
        }
        arsort($auditSummary);
        return $auditSummary;
    }
}
