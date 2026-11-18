<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Combos;

use InvalidArgumentException;

/** Validates text, costs, sources and zone snapshots against the cards already attached to a step. */
final class ComboStepPolicy
{
    public static function text(mixed $value, int $limit, bool $required = false): string
    {
        if (!is_string($value) || mb_strlen($value) > $limit) {
            throw new InvalidArgumentException('Texto no válido o demasiado largo.');
        }
        $value = trim($value);
        if ($required && $value === '') {
            throw new InvalidArgumentException('Completa el título del combo y de cada paso.');
        }
        return $value;
    }

    public static function details(array $node, array $cards, string $format): array
    {
        $result = [];
        if (isset($node['details'])) {
            if (!is_array($node['details'])) {
                throw new InvalidArgumentException('Detalles técnicos no válidos.');
            }
            $result['details'] = [];
            foreach (
                [
                    'cost',
                    'target',
                    'origin',
                    'destination',
                    'timing',
                    'restrictions',
                    'remaining',
                    'source',
                ]
                as $field
            ) {
                $result['details'][$field] = \StationX\Yugioh\Domain\Combos\ComboStepPolicy::text(
                    $node['details'][$field] ?? '',
                    1500,
                );
            }
        }
        if (isset($node['placements'])) {
            if (
                !is_array($node['placements']) ||
                !array_is_list($node['placements']) ||
                count($node['placements']) > 8
            ) {
                throw new InvalidArgumentException('Estado de zonas no válido.');
            }
            $seen = [];
            $occupied = [];
            $result['placements'] = [];
            foreach ($node['placements'] as $p) {
                if (
                    !is_array($p) ||
                    !is_int($p['card_index'] ?? null) ||
                    !array_key_exists($p['card_index'], $cards) ||
                    isset($seen[$p['card_index']])
                ) {
                    throw new InvalidArgumentException(
                        'La posición no corresponde a una carta del paso.',
                    );
                }
                $zone = $p['zone'] ?? 'unknown';
                $position = $p['position'] ?? 'unknown';
                if (
                    !is_string($zone) ||
                    !preg_match(
                        '/^(unknown|hand|gy|banished|deck|extra|side|reserve|field|monster_[1-5]|spell_[1-5]|extra_[12])$/D',
                        $zone,
                    ) ||
                    !in_array($position, ['unknown', 'attack', 'defense', 'face_down'], true)
                ) {
                    throw new InvalidArgumentException('Zona o posición no válida.');
                }
                if (
                    ($format === 'rush' &&
                        preg_match('/^(?:monster_[45]|spell_[45]|extra_[12])$/D', $zone)) ||
                    ($format === 'goat' && str_starts_with($zone, 'extra_'))
                ) {
                    throw new InvalidArgumentException(
                        'Esa zona no pertenece al formato seleccionado.',
                    );
                }
                if (
                    $format === 'unspecified' &&
                    preg_match('/^(?:monster_|spell_|extra_)/', $zone)
                ) {
                    throw new InvalidArgumentException(
                        'Selecciona el formato del tablero antes de indicar zonas numeradas.',
                    );
                }
                if (
                    preg_match('/^(?:monster_|spell_|extra_|field)/', $zone) &&
                    isset($occupied[$zone])
                ) {
                    throw new InvalidArgumentException(
                        'La zona ya contiene una carta. Describe materiales por separado.',
                    );
                }
                $seen[$p['card_index']] = true;
                $occupied[$zone] = true;
                $result['placements'][] = [
                    'card_index' => $p['card_index'],
                    'zone' => $zone,
                    'position' => $position,
                    'materials' => \StationX\Yugioh\Domain\Combos\ComboStepPolicy::text(
                        $p['materials'] ?? '',
                        500,
                    ),
                ];
            }
        }
        return $result;
    }
}
