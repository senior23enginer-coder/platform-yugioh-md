<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Presentation;

final class CompetitionDirectoryProjection
{
    public static function build(array $competitions): array
    {
        $byType = [];
        $byRuleset = [];
        $byStatus = [];
        $byId = [];
        $dependencies = [];
        foreach ($competitions as $competition) {
            $type = (string) (($competition['type'] ?? '') ?: 'special');
            $ruleset = (string) (($competition['event_ruleset'] ?? '') ?: 'custom');
            $status = (string) (($competition['status'] ?? '') ?: 'draft');
            $byType[$type] = ($byType[$type] ?? 0) + 1;
            $byRuleset[$ruleset] = ($byRuleset[$ruleset] ?? 0) + 1;
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
            $byId[(int) ($competition['id'] ?? 0)] = $competition;
            foreach ($competition['dependencies'] ?? [] as $dependency) {
                $dependencies[] = [
                    'from' => (string) ($dependency['name'] ?? 'Evento previo'),
                    'to' => (string) ($competition['name'] ?? 'Evento'),
                    'ruleset' => $ruleset,
                    'ok' => !empty($dependency['ok']),
                    'status' => (string) ($dependency['status'] ?? 'draft'),
                    'condition' => (string) ($dependency['condition_type'] ?? 'completed_with_results'),
                ];
            }
        }
        return compact('byType', 'byRuleset', 'byStatus', 'byId', 'dependencies');
    }
}
