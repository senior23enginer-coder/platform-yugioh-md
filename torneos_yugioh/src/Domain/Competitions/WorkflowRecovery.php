<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

use InvalidArgumentException;

/** Reconstructs a previous phase from immutable history without inferring missing results. */
final class WorkflowRecovery
{
    public static function recoveryPlan(array $state): array
    {
        if (
            !empty($state['complete']) ||
            empty($state['pending']) ||
            empty($state['history']) ||
            $state['round'] < 2
        ) {
            throw new InvalidArgumentException(
                'Solo se puede revertir la ultima fase pendiente de un evento abierto.',
            );
        }
        $replay = \StationX\Yugioh\Domain\Competitions\WorkflowDefinition::begin(
            $state['policy']['model'],
            $state['roster'],
        );
        if ($replay['policy'] !== $state['policy']) {
            throw new InvalidArgumentException(
                'La version del reglamento no permite reconstruir esta fase.',
            );
        }
        $grouped = [];
        foreach ($state['history'] as $m) {
            $grouped[$m['round']][] = $m;
        }
        for ($round = 1; $round < $state['round']; $round++) {
            $next = \StationX\Yugioh\Domain\Competitions\WorkflowEngine::next($replay);
            $stored = $grouped[$round] ?? [];
            if ($next['complete'] || count($next['matches']) !== count($stored)) {
                throw new InvalidArgumentException(
                    'El historial no coincide con el reglamento; se conserva sin modificarlo.',
                );
            }
            foreach ($next['matches'] as $i => $m) {
                foreach (['a', 'b', 'phase', 'side', 'round', 'group'] as $key) {
                    if ($m[$key] !== $stored[$i][$key]) {
                        throw new InvalidArgumentException(
                            'Los cruces historicos no coinciden con el reglamento.',
                        );
                    }
                }
            }
            $replay = $next['state'];
            $replay['pending'] = array_map(static function (array $m): array {
                unset($m['winner'], $m['game_difference']);
                return $m;
            }, $stored);
            if ($round === $state['round'] - 1) {
                $replay['schedule'] = $state['schedule'];
                if (isset($state['teams'])) {
                    $replay['teams'] = $state['teams'];
                }
                return [
                    'state' => $replay,
                    'remove_ids' => array_map(
                        'intval',
                        array_column($state['pending'], 'match_id'),
                    ),
                    'restore_ids' => array_map('intval', array_column($stored, 'match_id')),
                ];
            }
            $replay = \StationX\Yugioh\Domain\Competitions\WorkflowEngine::accept($replay, $stored);
        }
        throw new InvalidArgumentException('No existe una fase anterior que recuperar.');
    }

    public static function recoveryToken(array $state): string
    {
        return hash('sha256', json_encode($state, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
