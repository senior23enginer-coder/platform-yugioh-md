<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Matches;

/** Evaluates the configured best-of and exact sequence conditions without manufacturing extra duels. */
final class DuelSequence
{
    public static function evaluateSequence(
        array $values,
        int $bestOf = 3,
        array $conditions = [],
    ): array {
        $invalid = static fn(string $message): array => [
            'status' => 'INVALID',
            'winner' => null,
            'score' => '0-0',
            'validation' => $message,
            'rule' => 'Integridad D1-D5',
        ];
        if (count($values) > 5) {
            return $invalid('No se permite D6.');
        }
        $played = [];
        $gap = false;
        foreach ($values as $value) {
            if ($value === '' || $value === null) {
                $gap = true;
                continue;
            }
            if ($gap) {
                return $invalid('No se pueden saltar duelos.');
            }
            if (!in_array($value, ['A', 'B', 'E'], true)) {
                return $invalid('Valor invalido');
            }
            $played[] = $value;
        }
        $active = array_values(
            array_filter(
                $conditions,
                static fn($c) => (int) ($c['active'] ?? 1) !== 0 &&
                    ($c['operator'] ?? '') === 'sequence_equals' &&
                    ($c['target_value'] ?? '') !== '',
            ),
        );
        usort(
            $active,
            static fn($a, $b) => [(int) ($a['sort_order'] ?? 0), (int) ($a['id'] ?? 0)] <=> [
                (int) ($b['sort_order'] ?? 0),
                (int) ($b['id'] ?? 0),
            ],
        );
        $span = max(1, min(5, $bestOf));
        foreach ($active as $c) {
            $span = max($span, min(5, count(explode(',', (string) $c['target_value']))));
        }
        if (!$played) {
            return [
                'status' => 'PENDIENTE',
                'winner' => null,
                'score' => '0-0',
                'validation' => 'OK',
                'rule' => 'Sin duelos cargados',
            ];
        }
        $a = 0;
        $b = 0;
        $result = [];
        $prefix = [];
        foreach ($played as $i => $value) {
            $prefix[] = $value;
            $a += (int) ($value === 'A');
            $b += (int) ($value === 'B');
            $exact = null;
            foreach ($active as $condition) {
                if ($condition['target_value'] === implode(',', $prefix)) {
                    $exact = $condition;
                    break;
                }
            }
            if ($exact !== null) {
                $winner = (string) ($exact['result_value'] ?? '');
                $state =
                    (string) ($exact['status_value'] ?? '' ?:
                    ($winner === ''
                        ? 'REQUIERE D5'
                        : 'FINALIZADA'));
                $result = [
                    'status' => $state,
                    'winner' => in_array($winner, ['A', 'B', 'E'], true) ? $winner : null,
                    'score' => (string) ($exact['score_value'] ?? '' ?: '0-0'),
                    'validation' =>
                        $state === 'INVALID'
                            ? (string) ($exact['failure_message'] ?? '' ?:
                            'Secuencia invalida por regla')
                            : 'OK',
                    'rule' => (string) ($exact['condition_label'] ?? 'Regla configurada'),
                ];
            } else {
                $closed =
                    $a >= intdiv($span, 2) + 1 ||
                    $b >= intdiv($span, 2) + 1 ||
                    count($prefix) >= 5 ||
                    ($span === 1 && count($prefix) === 1);
                $result = [
                    'status' => $closed ? 'FINALIZADA' : 'EN CURSO',
                    'winner' => $closed ? ($a === $b ? 'E' : ($a > $b ? 'A' : 'B')) : null,
                    'score' => "$a-$b",
                    'validation' => 'OK',
                    'rule' => 'Umbral BO' . $span . ' de la regla aplicada',
                ];
            }
            if (
                $i < count($played) - 1 &&
                in_array($result['status'], ['FINALIZADA', 'INVALID'], true)
            ) {
                return array_replace($invalid('Duelos despues de cierre.'), ['score' => '-']);
            }
        }
        return $result;
    }
}
