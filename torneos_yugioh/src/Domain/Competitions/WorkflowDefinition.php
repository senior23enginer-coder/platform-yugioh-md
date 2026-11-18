<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

use InvalidArgumentException;

/** Initial state, supported models and bounded execution parameters. No persistence or schedule writes. */
final class WorkflowDefinition
{
    public static function models(): array
    {
        return [
            'swiss',
            'swiss_top_cut',
            'groups',
            'groups_knockout',
            'series',
            'double_elimination',
            'season_playoffs',
            'flights',
            'ladder',
            'king_of_the_hill',
            'gauntlet',
        ];
    }

    public static function executionParameterFields(string $model): array
    {
        return [
            'swiss' => ['swiss_rounds'],
            'swiss_top_cut' => ['swiss_rounds', 'top_n'],
            'groups' => ['group_max_size'],
            'groups_knockout' => ['group_max_size', 'group_qualifiers'],
            'season_playoffs' => ['top_n'],
            'series' => ['series_matches', 'series_wins'],
            'flights' => ['group_max_size'],
        ][$model] ?? [];
    }

    public static function policy(string $model, int $participants): array
    {
        if (
            !in_array(
                $model,
                array_merge(\StationX\Yugioh\Domain\Competitions\WorkflowDefinition::models(), [
                    '_teams_round_robin',
                    '_teams_double_round_robin',
                    '_teams_single_elimination',
                ]),
                true,
            )
        ) {
            throw new InvalidArgumentException('Motor sin politica Station X.');
        }
        if ($participants < 2 || $participants > 64) {
            throw new InvalidArgumentException(
                'Este motor admite entre 2 y 64 participantes individuales.',
            );
        }
        if ($model === 'series' && $participants !== 2) {
            throw new InvalidArgumentException(
                'Una serie requiere exactamente dos decks; para mas participantes usa una llave.',
            );
        }
        $cut = 2;
        while ($cut * 2 <= min(8, max(2, intdiv($participants, 2)))) {
            $cut *= 2;
        }
        return [
            'version' => 'STATION_X_2026_V1',
            'official' => false,
            'model' => $model,
            'participants' => $participants,
            'swiss_rounds' => min($participants - 1, (int) ceil(log($participants, 2))),
            'points' => ['win' => 3, 'draw' => 1, 'loss' => 0, 'bye' => 3],
            'tie_breaks' => ['points', 'buchholz', 'game_difference', 'initial_seed'],
            'bye' => 'lowest_rank_without_previous_bye; no duel, XP or game score',
            'rematch' => 'forbidden; block if no complete pairing exists',
            'top_n' => $cut,
            'cut_tie' => 'initial_seed_after_other_tiebreaks; no substitutions',
            'group_max_size' => 4,
            'group_qualifiers' => 2,
            'group_seeding' => 'snake_by_initial_seed',
            'series_matches' => 3,
            'series_wins' => 2,
            'decisive_draw' => 'resolve_same_match_before_advancing',
            'double_final' => 'reset_if_undefeated_finalist_loses',
            'slot_minutes' => 60,
            // Fixed internal profiles: no unbounded challenges or unscheduled defenders.
            ...$model === 'ladder'
                ? [
                    'ladder_rounds' => 2 * $participants,
                    'ladder_order' => 'adjacent; alternate even/odd; lower winner swaps; no draw',
                ]
                : [],
            ...$model === 'flights'
                ? [
                    'flights' =>
                        'snake groups <=4; local single elimination; flight winners final knockout',
                ]
                : [],
            ...$model === 'king_of_the_hill'
                ? ['hill' => 'initial seed defends; challengers once in seed order; winner stays']
                : [],
            ...$model === 'gauntlet'
                ? [
                    'gauntlet' =>
                        'initial seed faces each rival once; first defeat ends run; no overall champion on failed run',
                ]
                : [],
            'seed' => 'persisted participant order; duplicates rejected',
        ];
    }

    public static function begin(string $model, array $ids, array $parameters = []): array
    {
        $ids = array_map('intval', array_values($ids));
        if (count(array_unique($ids)) !== count($ids) || min($ids ?: [0]) < 1) {
            throw new InvalidArgumentException(
                'Los participantes deben ser decks validos y distintos.',
            );
        }
        $policy = \StationX\Yugioh\Domain\Competitions\WorkflowDefinition::policy(
            $model,
            count($ids),
        );
        foreach ($parameters as $key => $value) {
            if (
                !array_key_exists($key, $policy) ||
                !in_array(
                    $key,
                    \StationX\Yugioh\Domain\Competitions\WorkflowDefinition::executionParameterFields(
                        $model,
                    ),
                    true,
                )
            ) {
                throw new InvalidArgumentException(
                    'Parametro operativo no permitido para este motor: ' . $key,
                );
            }
        }
        $policy = array_replace($policy, $parameters);
        foreach (
            [
                'swiss_rounds',
                'top_n',
                'group_max_size',
                'group_qualifiers',
                'series_matches',
                'series_wins',
            ]
            as $key
        ) {
            if (!is_int($policy[$key]) || $policy[$key] < 1 || $policy[$key] > 64) {
                throw new InvalidArgumentException('Parametro operativo invalido: ' . $key);
            }
        }
        if ($policy['swiss_rounds'] > count($ids) - 1) {
            throw new InvalidArgumentException(
                'Las rondas suizas exceden los rivales disponibles sin repeticion.',
            );
        }
        if (
            in_array($model, ['swiss_top_cut', 'season_playoffs'], true) &&
            ($policy['top_n'] > count($ids) || $policy['top_n'] < 2)
        ) {
            throw new InvalidArgumentException('El corte debe contener entre 2 y N clasificados.');
        }
        if ($policy['group_qualifiers'] > $policy['group_max_size']) {
            throw new InvalidArgumentException('Los cupos por grupo superan su capacidad.');
        }
        if ($policy['group_max_size'] < 2) {
            throw new InvalidArgumentException(
                'Un grupo competitivo requiere al menos dos participantes.',
            );
        }
        if (
            $policy['series_matches'] % 2 !== 1 ||
            $policy['series_wins'] !== intdiv($policy['series_matches'], 2) + 1
        ) {
            throw new InvalidArgumentException(
                'La serie requiere un numero impar de matches y mayoria de victorias.',
            );
        }
        if (!in_array($policy['double_final'], ['reset_if_undefeated_finalist_loses'], true)) {
            throw new InvalidArgumentException(
                'Contrato de final de doble eliminacion no implementado.',
            );
        }
        $s = [
            'policy' => $policy,
            'roster' => $ids,
            'round' => 0,
            'history' => [],
            'byes' => [],
            'groups' => [],
            'champion' => null,
            'runner_up' => null,
        ];
        if (in_array($model, ['groups', 'groups_knockout', 'flights'], true)) {
            $g = (int) ceil(count($ids) / $policy['group_max_size']);
            $s['groups'] = array_fill(0, $g, []);
            foreach ($ids as $i => $id) {
                $row = intdiv($i, $g);
                $col = $i % $g;
                $s['groups'][$row % 2 ? $g - 1 - $col : $col][] = $id;
            }
            if (
                $model === 'groups_knockout' &&
                min(array_map('count', $s['groups'])) < $policy['group_qualifiers']
            ) {
                throw new InvalidArgumentException(
                    'Un grupo no tiene suficientes participantes para los cupos configurados.',
                );
            }
        }
        return $s;
    }
}
