<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Seasons;

use InvalidArgumentException;
use StationX\Yugioh\Application\Shared\Clock;
use StationX\Yugioh\Application\Shared\TransactionBoundary;
use StationX\Yugioh\Domain\Seasons\SeasonDefinition;
use StationX\Yugioh\Domain\Seasons\SeasonSchedulePolicy;

final readonly class SeasonLifecycle
{
    public function __construct(
        private SeasonLifecycleStore $store,
        private TransactionBoundary $ownTransaction,
        private TransactionBoundary $joinTransaction,
        private Clock $clock,
    ) {}

    public function save(array $input): int
    {
        $id = (int) ($input['season_id'] ?? 0);
        $definition = SeasonDefinition::fromInput($input);
        return $this->ownTransaction->run(function () use ($id, $definition, $input): int {
            if ($id > 0) {
                SeasonSchedulePolicy::requireOpen(
                    $this->store->find($id, true),
                    false,
                    $this->clock->now()->format('Y-m-d'),
                );
                if ($this->store->hasOutsideDates($id, $definition['starts_on'], $definition['ends_on'])) {
                    throw new InvalidArgumentException(
                        'El período dejaría eventos o partidas fuera de la temporada. Ajusta primero sus fechas.',
                    );
                }
                $this->store->update($id, $definition);
            } else {
                $id = $this->store->insert($definition);
            }
            $this->store->audit($id, 'save_season', $definition);
            if (!empty($input['season_finalize'])) {
                $this->close($id);
            }
            return $id;
        });
    }

    public function close(int $id, string $reason = 'manual'): int
    {
        return $this->joinTransaction->run(function () use ($id, $reason): int {
            $season = $this->store->find($id, true);
            if (!$season) {
                throw new InvalidArgumentException('Temporada no encontrada.');
            }
            if (in_array($season['status'], ['completed', 'archived'], true)) {
                return 0;
            }
            $events = $this->store->openCompetitions($id);
            foreach ($events as $event) {
                $this->store->closeCompetition($id, $event, $reason);
            }
            $this->store->completeSeason($id);
            $this->store->audit($id, 'close_season', ['reason' => $reason, 'closed_events' => count($events)]);
            return count($events);
        });
    }

    public function closeExpired(): void
    {
        foreach ($this->store->expiredIds($this->clock->now()->format('Y-m-d')) as $id) {
            $this->close((int) $id, 'period_ended');
        }
    }

    public function delete(int $id): void
    {
        $this->ownTransaction->run(function () use ($id): void {
            if (!$this->store->find($id, true)) {
                throw new InvalidArgumentException('Temporada no encontrada.');
            }
            if ($this->store->hasDependencies($id)) {
                throw new InvalidArgumentException(
                    'La temporada tiene eventos o divisiones asociados. Puedes finalizarla y conservar su historial.',
                );
            }
            $this->store->deleteSeason($id);
            $this->store->audit($id, 'delete_season', []);
        });
    }
}
