<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Statistics;

use InvalidArgumentException;

/** Rating and uncertainty formulas with explicit inputs; no publication or result persistence. */
final class RatingMath
{
    public static function ratio(float $n, float $d): ?float
    {
        return $d > 0 ? (100 * $n) / $d : null;
    }

    public static function wilson(float $p, int $n): ?float
    {
        if ($n < 1) {
            return null;
        }
        $z = 1.96;
        $z2 = $z * $z;
        return ($p + $z2 / (2 * $n) - $z * sqrt(($p * (1 - $p) + $z2 / (4 * $n)) / $n)) /
            (1 + $z2 / $n);
    }

    public static function elo(
        float $a,
        float $b,
        float $s,
        float $k = 20,
        float $divisor = 600,
    ): array {
        $e = 1 / (1 + 10 ** (($b - $a) / $divisor));
        $d = $k * ($s - $e);
        return [
            'before_a' => $a,
            'before_b' => $b,
            'expectation_a' => $e,
            'expectation_b' => 1 - $e,
            'delta_a' => $d,
            'delta_b' => -$d,
            'after_a' => $a + $d,
            'after_b' => $b - $d,
            'k' => $k,
        ];
    }

    public static function tagElo(array $left, array $right, float $score, float $k = 20): array
    {
        if (count($left) !== 2 || count($right) !== 2 || !in_array($score, [0.0, 0.5, 1.0], true)) {
            throw new InvalidArgumentException(
                'Tag Elo requiere dos integrantes por lado y resultado valido.',
            );
        }
        return \StationX\Yugioh\Domain\Statistics\RatingMath::elo(
            array_sum($left) / 2,
            array_sum($right) / 2,
            $score,
            $k,
            600,
        );
    }
}
