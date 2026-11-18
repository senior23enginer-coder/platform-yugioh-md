<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Calendar;

use PDO;
use StationX\Yugioh\Application\Calendar\ScheduleCapacity;

/** Adapts existing settings; construction performs no query. */
final readonly class NativeScheduleCapacity implements ScheduleCapacity
{
    public function __construct(private PDO $pdo) {}

    public function limit(): int
    {
        $settings = \ygo_settings($this->pdo);
        return max(1, min(3, (int) ($settings['max_matches_per_day'] ?? 3)));
    }
}
