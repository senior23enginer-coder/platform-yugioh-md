<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Seasons;

use DateTimeImmutable;
use InvalidArgumentException;

final class SeasonDefinition
{
    public static function periods(): array
    {
        return ['annual' => ['Anual', 12], 'semiannual' => ['Semestral', 6], 'quarterly' => ['Trimestral', 3]];
    }

    public static function end(string $start, string $period): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $start);
        if (!$date || $date->format('Y-m-d') !== $start || !isset(self::periods()[$period])) {
            throw new InvalidArgumentException('Selecciona un ciclo y una fecha de inicio válidos.');
        }
        $target = $date->modify('first day of this month')->modify('+' . self::periods()[$period][1] . ' months');
        $target = $target->setDate(
            (int) $target->format('Y'),
            (int) $target->format('m'),
            min((int) $date->format('d'), (int) $target->format('t')),
        );
        return $target->modify('-1 day')->format('Y-m-d');
    }

    public static function fromInput(array $input): array
    {
        $name = trim((string) ($input['season_name'] ?? ''));
        $period = (string) ($input['season_period'] ?? '');
        $start = (string) ($input['season_starts_on'] ?? '');
        $defaultEnd = self::end($start, $period);
        $end = trim((string) ($input['season_ends_on'] ?? '')) ?: $defaultEnd;
        $endDate = DateTimeImmutable::createFromFormat('!Y-m-d', $end);
        if (!$endDate || $endDate->format('Y-m-d') !== $end || $end < $start) {
            throw new InvalidArgumentException('La fecha de cierre debe ser valida y no puede ser anterior al inicio.');
        }
        $status = (string) ($input['season_status'] ?? 'draft');
        if ($name === '' || mb_strlen($name) > 120 || !in_array($status, ['draft', 'active'], true)) {
            throw new InvalidArgumentException('Indica un nombre de hasta 120 caracteres y un estado válido.');
        }
        return [
            'name' => $name,
            'period_type' => $period,
            'starts_on' => $start,
            'ends_on' => $end,
            'status' => $status,
        ];
    }
}
