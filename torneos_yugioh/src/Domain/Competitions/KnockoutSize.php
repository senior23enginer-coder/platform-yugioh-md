<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

/** Pure competition policy. No PDO, HTTP, clock or session dependencies. */
final class KnockoutSize
{
    /** Preserve the established round order, bye handling and participant identities. */
    public static function forParticipants(int $participants): int
    {
        if ($participants >= 16) {
            return 16;
        }
        if ($participants >= 8) {
            return 8;
        }
        if ($participants >= 4) {
            return 4;
        }
        return min(2, $participants);
    }
}
