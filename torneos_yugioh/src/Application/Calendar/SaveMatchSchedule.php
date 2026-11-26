<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Calendar;

use StationX\Yugioh\Domain\Calendar\MatchScheduleUpdate;

final readonly class SaveMatchSchedule
{
    public function __construct(
        private MatchScheduleStore $store,
        private MatchScheduleServices $services,
    ) {}

    public function execute(array $input): string
    {
        $matchId = (int) ($input['schedule_match_id'] ?? 0);
        if ($matchId <= 0) {
            return 'calendario_reprogramado';
        }
        $match = $this->store->find($matchId);
        if (!$match) {
            return 'calendario_bloqueado_regla';
        }
        if (MatchScheduleUpdate::closed($match)) {
            return 'calendario_bloqueado_finalizado';
        }
        $values = MatchScheduleUpdate::fromInput($input);
        $guard = $this->services->validate(
            $matchId,
            $values['scheduled_on'],
            $values['starts_at'],
            $values['venue'],
        );
        if (!$guard['ok']) {
            $this->store->audit($matchId, 'blocked_schedule_by_rules', $guard);
            return 'calendario_bloqueado_regla';
        }
        $this->store->update($matchId, $values);
        $this->store->audit($matchId, 'reschedule_match', [
            'before' => MatchScheduleUpdate::before($match),
            'after' => $values,
        ]);
        $competitionId = (int) ($match['competition_id'] ?? 0);
        if ($competitionId > 0) {
            $this->services->rebuildCalendar($competitionId, (string) ($match['phase'] ?? 'match'));
            if ($this->store->competitionType($competitionId) !== 'qualifier') {
                $this->services->reflowQualifiers();
            }
        }
        return 'calendario_reprogramado';
    }
}
