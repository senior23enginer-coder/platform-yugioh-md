<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Time;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;
use StationX\Yugioh\Application\News\PublicationDates;

/** Preserves existing PHP date parsing and stores known instants in UTC. */
final class NativePublicationDates implements PublicationDates
{
    public function parse(string $value): array
    {
        if (!$value) {
            return [null, 'unknown'];
        }
        try {
            if (!preg_match('/(?:Z|[+-]\d{2}:?\d{2}|GMT|UTC)$/i', $value)) {
                return [(new DateTimeImmutable($value))->format('Y-m-d') . ' 00:00:00', 'date'];
            }
            return [
                (new DateTimeImmutable($value))
                    ->setTimezone(new DateTimeZone('UTC'))
                    ->format('Y-m-d H:i:s'),
                'instant',
            ];
        } catch (Throwable) {
            return [null, 'unknown'];
        }
    }
}
