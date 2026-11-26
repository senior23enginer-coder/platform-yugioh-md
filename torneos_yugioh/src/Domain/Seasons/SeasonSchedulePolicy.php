<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Seasons;

use DateTimeImmutable;
use InvalidArgumentException;

final class SeasonSchedulePolicy
{
    public static function requireOpen(?array $season, bool $playing, string $today): array
    {
        if (!$season) {
            throw new InvalidArgumentException('El evento debe estar vinculado a una temporada existente.');
        }
        if (
            in_array($season['status'], ['completed', 'archived'], true) ||
            (!empty($season['ends_on']) && $season['ends_on'] < $today)
        ) {
            throw new InvalidArgumentException(
                'La temporada está finalizada. Sus eventos están cerrados y solo permiten consulta.',
            );
        }
        if ($playing && !empty($season['starts_on']) && $season['starts_on'] > $today) {
            throw new InvalidArgumentException('La temporada todavía no ha comenzado.');
        }
        return $season;
    }

    public static function validateEventDates(array $season, ?string $start, ?string $end): void
    {
        foreach ([$start, $end] as $value) {
            if (!$value) {
                continue;
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if (
                !$date ||
                $date->format('Y-m-d') !== $value ||
                (!empty($season['starts_on']) && $value < $season['starts_on']) ||
                (!empty($season['ends_on']) && $value > $season['ends_on'])
            ) {
                throw new InvalidArgumentException(
                    'Las fechas del evento deben estar dentro del período de su temporada.',
                );
            }
        }
        if ($start && $end && $end < $start) {
            throw new InvalidArgumentException('El fin del evento no puede ser anterior a su inicio.');
        }
    }
}
