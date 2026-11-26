<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

final class CompetitionGatePolicy
{
    public static function seasonOpen(?array $season, string $today): bool
    {
        return $season &&
            !in_array($season['status'], ['completed', 'archived'], true) &&
            (empty($season['ends_on']) || $season['ends_on'] >= $today);
    }

    public static function completed(array $dependency): bool
    {
        $status = strtolower((string) ($dependency['depends_on_status'] ?? 'draft'));
        return in_array(
            $status,
            ['completed', 'finalizada', 'finalizado', 'closed', 'archived'],
            true,
        );
    }

    public static function item(array $dependency, int $resultCount, bool $ok): array
    {
        return [
            'id' => (int) $dependency['id'],
            'depends_on_competition_id' => (int) $dependency['depends_on_competition_id'],
            'name' => (string) $dependency['depends_on_name'],
            'type' => (string) $dependency['depends_on_type'],
            'status' => (string) $dependency['depends_on_status'],
            'condition_type' =>
                (string) ($dependency['condition_type'] ?: 'completed_with_results'),
            'take_count' => (int) ($dependency['take_count'] ?? 0),
            'result_count' => $resultCount,
            'ok' => $ok,
        ];
    }

    public static function summarize(array $resolved): array
    {
        if (!$resolved) {
            return [
                'can_execute' => true,
                'message' => 'Sin prerrequisitos.',
                'dependencies' => [],
            ];
        }
        $blocked = array_values(
            array_filter($resolved, static fn(array $item): bool => !$item['ok']),
        );
        return [
            'can_execute' => !$blocked,
            'message' => !$blocked
                ? 'Prerrequisitos cumplidos.'
                : 'Evento bloqueado: primero debe cumplirse ' .
                    implode(
                        ', ',
                        array_map(static fn(array $item): string => $item['name'], $blocked),
                    ) .
                    '.',
            'dependencies' => $resolved,
        ];
    }
}
