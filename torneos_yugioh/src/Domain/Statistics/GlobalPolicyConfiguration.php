<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Statistics;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class GlobalPolicyConfiguration
{
    public static function build(string $anchor, array $mapping): array
    {
        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $anchor,
            new DateTimeZone('America/Bogota'),
        );
        if (!$date || $date->format('Y-m-d') !== $anchor) {
            throw new InvalidArgumentException(
                'Selecciona una fecha inicial valida para los cortes globales.',
            );
        }
        $policy = StatisticsPolicy::defaults();
        $policy['global_anchor'] = $anchor;
        foreach ($mapping as $code => $k) {
            if (
                !in_array($code, ['FULL', 'REDUCED', 'BONUS'], true) ||
                !in_array($k, [20, 25, 30], true)
            ) {
                throw new InvalidArgumentException('Mapa de importancia no admitido.');
            }
            $policy['k_mapping'][$code] = $k;
        }
        return $policy;
    }

    public static function assertUnchanged(?string $existing, array $policy): void
    {
        if ($existing && json_decode($existing, true) !== $policy) {
            throw new InvalidArgumentException(
                'La politica global ya esta fijada. Un cambio requiere una nueva version y revision explicita.',
            );
        }
    }
}
