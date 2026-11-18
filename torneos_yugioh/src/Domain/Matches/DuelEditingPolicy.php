<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Matches;

use InvalidArgumentException;

/** Validates numeric limits and chooses the unfinished duel without changing sequence rules. */
final class DuelEditingPolicy
{
    public static function int(mixed $value, int $min, int $max, string $label): int
    {
        $n = filter_var($value, FILTER_VALIDATE_INT);
        if ($n === false || $n < $min || $n > $max) {
            throw new InvalidArgumentException(
                $label . ' debe estar entre ' . $min . ' y ' . $max . '.',
            );
        }
        return $n;
    }

    public static function current(array $values, string $status): int
    {
        if ($status === 'FINALIZADA') {
            return max(1, count(array_filter($values)));
        }
        foreach ($values as $i => $value) {
            if (!in_array($value, ['A', 'B', 'E'], true)) {
                return $i + 1;
            }
        }
        return 5;
    }
}
