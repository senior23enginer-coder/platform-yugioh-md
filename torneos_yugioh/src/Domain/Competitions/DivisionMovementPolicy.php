<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

use InvalidArgumentException;

/** Validates explicit division movement settings without changing any participant. */
final class DivisionMovementPolicy
{
    public static function validateDivisionMovements(array $divisions, array $input): array
    {
        if (count($divisions) < 2) {
            if ($input) {
                throw new InvalidArgumentException(
                    'Ascenso y descenso solo se configura en ligas con más de una división.',
                );
            }
            return [];
        }
        usort(
            $divisions,
            static fn($a, $b) => [(int) ($a['tier_order'] ?? 0), (int) $a['id']] <=> [
                (int) ($b['tier_order'] ?? 0),
                (int) $b['id'],
            ],
        );
        $byId = [];
        foreach ($divisions as $index => $division) {
            $byId[(int) $division['id']] = $index;
        }
        foreach ($input as $id => $values) {
            if (!isset($byId[(int) $id]) || !is_array($values)) {
                throw new InvalidArgumentException(
                    'La division de origen debe pertenecer a esta liga.',
                );
            }
        }
        $validated = [];
        foreach ($divisions as $index => $division) {
            $id = (int) $division['id'];
            $values = $input[$id] ?? [];
            $row = ['id' => $id];
            foreach (['promotion' => -1, 'relegation' => 1] as $kind => $direction) {
                $raw = $values[$kind . '_slots'] ?? '0';
                if (filter_var($raw, FILTER_VALIDATE_INT) === false || (int) $raw < 0) {
                    throw new InvalidArgumentException(
                        'Los cupos deben ser numeros enteros mayores o iguales a cero.',
                    );
                }
                $slots = (int) $raw;
                $neighbor = $divisions[$index + $direction] ?? null;
                $target = (int) ($values[$kind . '_to_division_id'] ?? 0);
                if ($slots && !$neighbor) {
                    throw new InvalidArgumentException(
                        $kind === 'promotion'
                            ? 'La primera division no puede ascender: no existe una division superior.'
                            : 'La ultima division no puede descender: no existe una division inferior.',
                    );
                }
                if ($slots && $target !== (int) $neighbor['id']) {
                    throw new InvalidArgumentException(
                        'Los movimientos solo se permiten entre divisiones consecutivas de la misma liga, respetando ascenso y descenso.',
                    );
                }
                $row[$kind . '_slots'] = $slots;
                $row[$kind . '_to_division_id'] = $slots ? $target : null;
            }
            if (
                $row['promotion_slots'] + $row['relegation_slots'] >
                (int) $division['actual_decks']
            ) {
                throw new InvalidArgumentException(
                    'Los cupos de ascenso y descenso no pueden superar los decks de la division.',
                );
            }
            $validated[] = $row;
        }
        for ($index = 0; $index < count($validated) - 1; $index++) {
            if (
                $validated[$index]['relegation_slots'] !== $validated[$index + 1]['promotion_slots']
            ) {
                throw new InvalidArgumentException(
                    'El numero de descensos de una division debe ser igual al numero de ascensos de la division inferior.',
                );
            }
        }
        return $validated;
    }
}
