<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Calendar;

final class MatchScheduleUpdate
{
    public static function closed(array $match): bool
    {
        return in_array(
            strtoupper((string) ($match['status'] ?? '')),
            ['FINALIZADA', 'FINALIZADO', 'COMPLETED'],
            true,
        );
    }

    public static function fromInput(array $input): array
    {
        return [
            'scheduled_on' => $input['scheduled_on'] ?? '' ?: null,
            'starts_at' => $input['starts_at'] ?? '' ?: null,
            'venue' => trim((string) ($input['venue'] ?? '')),
            'judge' => trim((string) ($input['judge'] ?? '')),
            'notes' => trim((string) ($input['match_notes'] ?? '')),
        ];
    }

    public static function before(array $match): array
    {
        return [
            'scheduled_on' => $match['scheduled_on'] ?? null,
            'starts_at' => $match['starts_at'] ?? null,
            'venue' => $match['venue'] ?? '',
            'judge' => $match['judge'] ?? '',
            'notes' => $match['notes'] ?? '',
        ];
    }
}
