<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Seasons;

use InvalidArgumentException;
use StationX\Yugioh\Domain\Seasons\SeasonSchedulePolicy;

final readonly class GuardSeasonAction
{
    public function __construct(
        private ValidateSeasonSchedule $schedule,
        private SeasonContextReader $reader,
    ) {}

    public static function supports(string $action): bool
    {
        return in_array(
            $action,
            [
                'save_competition',
                'configure_competition',
                'save_match_result',
                'save_match_schedule',
                'create_match',
                'delete_manual_friendly',
                'advance_competition',
                'finalize_competition',
                'advance_phase',
                'apply_qualifier_divisions',
                'apply_league_movements',
                'recalculate_competition_standings',
                'archive_competition',
                'delete_competition',
                'set_qualifier_status',
                'auto_schedule_qualifier',
                'rebuild_qualifier_calendar',
                'generate_friendlies',
                'generate_leagues',
                'generate_cups',
                'generate_world',
                'save_division',
                'save_checkin',
                'save_honor',
                'save_competition_dependency',
                'delete_competition_dependency',
            ],
            true,
        );
    }

    public function execute(string $action, int $seasonId, array $input): void
    {
        if (!self::supports($action)) {
            return;
        }
        $season = $this->schedule->requireOpen($seasonId, $action === 'save_match_result');
        if (
            $action === 'save_competition' &&
            (!isset($input['competition_season_id']) || (int) $input['competition_season_id'] !== $seasonId)
        ) {
            throw new InvalidArgumentException(
                'Selecciona la temporada del evento. La temporada de un evento existente se conserva.',
            );
        }
        $start = $input['starts_on'] ??
            $input['competition_start_on'] ??
            $input['scheduled_on'] ??
            $input['manual_scheduled_on'] ??
            $input['qualifier_schedule_start'] ??
            null;
        $end = $input['ends_on'] ?? null;
        SeasonSchedulePolicy::validateEventDates($season, $start, $end);
        if ($action === 'create_match' && (int) ($input['manual_competition_id'] ?? 0) <= 0) {
            throw new InvalidArgumentException('Selecciona un evento con temporada para crear la partida.');
        }
        $divisionIds = array_unique(
            array_filter(
                array_merge(
                    [(int) ($input['competition_division_id'] ?? 0)],
                    array_map('intval', (array) ($input['competition_division_ids'] ?? [])),
                ),
            ),
        );
        foreach ($divisionIds as $divisionId) {
            if ($this->reader->seasonForDivision($divisionId) !== $seasonId) {
                throw new InvalidArgumentException('Las divisiones del evento deben pertenecer a su temporada.');
            }
        }
    }
}
