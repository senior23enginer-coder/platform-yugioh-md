<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Leagues;

use InvalidArgumentException;
use StationX\Yugioh\Domain\Competitions\LeagueRosterPolicy;

final readonly class LeagueRoster
{
    public function __construct(private LeagueRosterReader $reader) {}

    public function available(int $competitionId, array $ids): array
    {
        $competition = $this->reader->competition($competitionId);
        if (!$competition || $competition['type'] !== 'league') {
            return array_values($ids);
        }
        return LeagueRosterPolicy::available(
            $ids,
            $competitionId,
            (int) $competition['season_id'],
            $this->reader->assignments(),
        );
    }

    public function validate(int $competitionId, array $ids): void
    {
        $ids = LeagueRosterPolicy::normalize($ids);
        if (!$ids) {
            return;
        }
        sort($ids);
        $this->reader->lockDecks($ids);
        if (array_diff($ids, $this->available($competitionId, $ids))) {
            throw new InvalidArgumentException(
                'No se puede inscribir el deck: ya esta asignado o inscrito en otra liga de esta temporada. Puede participar en copas y otros eventos.',
            );
        }
    }
}
