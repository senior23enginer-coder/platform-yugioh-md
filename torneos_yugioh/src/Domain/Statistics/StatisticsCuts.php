<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Statistics;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use InvalidArgumentException;

final class StatisticsCuts
{
    public static function globalCuts(string $start, string $now): array
    {
        $tz = new DateTimeZone('America/Bogota');
        $first = new DateTimeImmutable(substr($start, 0, 10) . ' 00:00:00', $tz);
        $limit = new DateTimeImmutable($now, $tz);
        $cuts = [];
        for (
            $month = $first->modify('first day of this month');
            ;
            $month = $month->modify('first day of next month')
        ) {
            $cut = $month->modify('last day of this month')->setTime(23, 59, 59);
            if ($cut > $limit) {
                break;
            }
            $cuts[] = ['at' => $cut->format('Y-m-d H:i:s'), 'final' => false];
        }
        return $cuts;
    }

    public static function cuts(string $start, string $end, string $now): array
    {
        $tz = new DateTimeZone('America/Bogota');
        $s = new DateTimeImmutable(substr($start, 0, 10) . ' 00:00:00', $tz);
        $e = new DateTimeImmutable(substr($end, 0, 10) . ' 23:59:59', $tz);
        $n = new DateTimeImmutable($now, $tz);
        $cuts = [];
        for ($d = $s->modify('+14 days'); $d < $e && $d <= $n; $d = $d->modify('+14 days')) {
            $cuts[] = ['at' => $d->format('Y-m-d H:i:s'), 'final' => false];
        }
        if ($e <= $n) {
            $cuts[] = ['at' => $e->format('Y-m-d H:i:s'), 'final' => true];
        }
        return $cuts;
    }
}
