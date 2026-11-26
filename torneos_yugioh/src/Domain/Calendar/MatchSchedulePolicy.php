<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Calendar;

use DateTimeImmutable;
use StationX\Yugioh\Domain\Settings\SettingValue;

final class MatchSchedulePolicy
{
    public static function resultDate(string $scheduledOn, string $today, array $settings): array
    {
        if ($scheduledOn > $today && !SettingValue::enabled($settings, 'allow_early_results')) {
            return ['ok' => false, 'state' => 'fecha_futura', 'message' => 'No se puede ejecutar un duelo antes de su fecha programada. Activa "permitir adelantar duelos" si necesitas hacerlo.'];
        }
        if ($scheduledOn < $today && !SettingValue::enabled($settings, 'allow_overdue_results')) {
            return ['ok' => false, 'state' => 'vencido_bloqueado', 'message' => 'El duelo esta vencido y la configuracion no permite registrar resultados atrasados.'];
        }
        return [
            'ok' => true,
            'state' => $scheduledOn < $today ? 'vencido' : ($scheduledOn === $today ? 'hoy' : 'adelantado_permitido'),
            'message' => 'Fecha validada.',
        ];
    }

    public static function dayAndTime(
        DateTimeImmutable $parsed,
        string $date,
        ?string $startsAt,
        array $settings,
    ): ?array {
        $weekday = $parsed->format('w');
        $validWeekdays = array_filter(array_map('trim', explode(',', (string) ($settings['valid_weekdays'] ?? ''))), static fn(string $value): bool => $value !== '');
        $specialDays = array_filter(array_map('trim', preg_split('/[\s,]+/', (string) ($settings['special_days'] ?? '')) ?: []), static fn(string $value): bool => $value !== '');
        $allowedByWeekday = !$validWeekdays || in_array($weekday, $validWeekdays, true);
        $allowedBySpecial = SettingValue::enabled($settings, 'allow_special_days') && in_array($date, $specialDays, true);
        if (!$allowedByWeekday && !$allowedBySpecial) {
            return ['ok' => false, 'state' => 'dia_no_permitido', 'message' => 'El dia seleccionado no esta habilitado para jugar duelos.'];
        }
        $time = trim((string) $startsAt);
        if ($time !== '') {
            $time = substr($time, 0, 5);
            $start = substr((string) ($settings['allowed_start_time'] ?? '00:00'), 0, 5);
            $end = substr((string) ($settings['allowed_end_time'] ?? '23:59'), 0, 5);
            if ($start !== '' && $time < $start) {
                return ['ok' => false, 'state' => 'hora_temprana', 'message' => 'La hora esta antes del horario permitido.'];
            }
            if ($end !== '' && $time > $end) {
                return ['ok' => false, 'state' => 'hora_tardia', 'message' => 'La hora esta despues del horario permitido.'];
            }
        }
        return null;
    }

    public static function venue(?string $venue, array $settings): array
    {
        $venueValue = trim((string) $venue);
        $venues = array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) ($settings['venues'] ?? '')) ?: []), static fn(string $value): bool => $value !== '');
        if ($venueValue !== '' && $venues) {
            $normalizedVenues = array_map(static fn(string $value): string => strtolower($value), $venues);
            if (!in_array(strtolower($venueValue), $normalizedVenues, true)) {
                return ['ok' => false, 'state' => 'sede_no_permitida', 'message' => 'La sede seleccionada no esta en la lista de sedes disponibles.'];
            }
        }
        return ['ok' => true, 'state' => 'programable', 'message' => 'Fecha programable segun reglas.'];
    }

    public static function maxPerDay(array $settings): int
    {
        return max(1, (int) ($settings['max_matches_per_day'] ?? 3));
    }
}
