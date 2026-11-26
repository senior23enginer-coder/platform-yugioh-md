<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Seasons;

use InvalidArgumentException;

final readonly class HandleSeasonCommand
{
    public function __construct(
        private SeasonLifecycle $lifecycle,
        private SeasonScheduleReader $reader,
        private ResolveSeasonContext $context,
    ) {}

    public static function supports(string $action): bool
    {
        return in_array(
            $action,
            ['save_season', 'close_season', 'delete_season', 'set_season_status'],
            true,
        );
    }

    public function execute(string $action, array $input, mixed $requestSeasonId): void
    {
        if (!self::supports($action)) {
            return;
        }
        $id = (int) ($input['season_id'] ?? $this->context->current($requestSeasonId));
        if ($action === 'save_season') {
            $this->lifecycle->save($input);
        } elseif ($action === 'delete_season') {
            $this->lifecycle->delete($id);
        } elseif (
            $action === 'close_season' ||
            in_array($input['season_status'] ?? '', ['completed', 'archived'], true)
        ) {
            $this->lifecycle->close($id);
        } else {
            $season = $this->reader->find($id);
            if (!$season) {
                throw new InvalidArgumentException('Temporada no encontrada.');
            }
            $this->lifecycle->save([
                'season_id' => $id,
                'season_name' => $season['name'],
                'season_period' => $season['period_type'],
                'season_starts_on' => $season['starts_on'],
                'season_ends_on' => $season['ends_on'],
                'season_status' => $input['season_status'] ?? 'draft',
            ]);
        }
    }
}
