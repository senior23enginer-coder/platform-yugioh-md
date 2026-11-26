<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Calendar;

final class CompetitionCalendarProjection
{
    public static function journeys(
        int $competitionId,
        string $eventType,
        array $competition,
        array $days,
    ): iterable {
        foreach ($days as $index => $row) {
            $journey = $index + 1;
            yield [
                $competition['division_id'] !== null ? (int) $competition['division_id'] : null,
                'competition-' . $competitionId . '-' . $eventType . '-jornada-' . $journey,
                (string) ($competition['name'] ?? 'Evento') . ' - Jornada ' . $journey,
                $eventType,
                (string) $row['scheduled_on'],
                $row['starts_at'] ?: null,
                (string) ($row['venue'] ?: 'Station X Tulua'),
                (int) $row['match_count'] . ' combates programados desde duelos persistidos.',
            ];
        }
    }

    public static function startCode(int $competitionId): string
    {
        return 'competition-' . $competitionId . '-inicio';
    }
}
