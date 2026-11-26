<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Seasons;

final readonly class ResolveSeasonContext
{
    public function __construct(private SeasonContextReader $reader) {}

    public function resolve(array $query, array $input): ?int
    {
        $id = (int) ($query['season_context'] ?? $query['public_season'] ?? 0);
        $eventId = (int) ($input['competition_id'] ??
            $input['manual_competition_id'] ??
            $input['checkin_competition_id'] ??
            $input['dependency_competition_id'] ??
            $input['honor_competition_id'] ??
            0);
        $dependencyId = (int) ($input['dependency_id'] ?? 0);
        if ($dependencyId > 0) {
            $eventId = $this->reader->competitionForDependency($dependencyId) ?: $eventId;
        }
        $matchId = (int) ($input['match_id'] ??
            $input['schedule_match_id'] ??
            $input['friendly_match_id'] ??
            $input['checkin_match_id'] ??
            0);
        if ($matchId > 0) {
            $eventId = $this->reader->competitionForMatch($matchId) ?: $eventId;
        }
        if ($eventId > 0) {
            $id = $this->reader->seasonForCompetition($eventId) ?: $id;
        } elseif (isset($input['competition_season_id'])) {
            $id = (int) $input['competition_season_id'];
        }
        if (($input['action'] ?? '') === 'save_division' && (int) ($input['division_id'] ?? 0) > 0) {
            $id = $this->reader->seasonForDivision((int) $input['division_id']) ?: $id;
        }
        return $id > 0 && $this->reader->exists($id) ? $id : null;
    }

    public function current(mixed $requestSeasonId): int
    {
        return !empty($requestSeasonId) ? (int) $requestSeasonId : $this->reader->defaultSeasonId();
    }
}
