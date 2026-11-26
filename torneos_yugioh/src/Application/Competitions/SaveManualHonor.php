<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

use InvalidArgumentException;
use StationX\Yugioh\Application\Shared\TransactionBoundary;
use StationX\Yugioh\Domain\Competitions\ManualHonorPolicy;

final readonly class SaveManualHonor
{
    public function __construct(
        private ManualHonorStore $store,
        private TransactionBoundary $transaction,
    ) {}

    public function execute(array $post): string
    {
        $seasonId = $this->store->currentSeasonId();
        $eventId = max(0, (int) ($post['honor_competition_id'] ?? 0));
        $champion = max(0, (int) ($post['honor_champion_deck_id'] ?? 0));
        $runner = max(0, (int) ($post['honor_runner_deck_id'] ?? 0));
        $notes = trim((string) ($post['honor_notes'] ?? ''));
        return $this->transaction->run(function () use (
            $seasonId,
            $eventId,
            $champion,
            $runner,
            $notes,
            $post,
        ): string {
            ManualHonorPolicy::assertParticipants($champion, $runner);
            if ($eventId > 0) {
                $event = $this->store->lockEvent($eventId, $seasonId);
                ManualHonorPolicy::assertEvent($event, $notes);
                if ($this->store->hasHonor($eventId)) {
                    throw new InvalidArgumentException(
                        'Esta edicion ya tiene un titulo. Su correccion requiere una revision controlada.',
                    );
                }
                ManualHonorPolicy::assertRoster(
                    $champion,
                    $runner,
                    $this->store->participants($eventId),
                );
                $this->store->validateRoster(
                    (string) $event['event_ruleset'],
                    array_values(array_filter([$champion, $runner])),
                );
            }
            $championTeam = $this->store->team($champion);
            if (!$championTeam) {
                throw new InvalidArgumentException(
                    'El campeon requiere una identidad equipo/deck confirmada.',
                );
            }
            $runnerTeam = $runner ? $this->store->team($runner) : null;
            if ($runner && !$runnerTeam) {
                throw new InvalidArgumentException('Subcampeon no identificado.');
            }
            $this->store->record([
                $seasonId,
                $eventId ?: null,
                $champion,
                $championTeam,
                $runner ?: null,
                $runnerTeam ?: null,
                trim((string) ($post['honor_title'] ?? 'Campeon')),
                $notes,
            ]);
            $this->store->audit($eventId, $champion, $championTeam, $notes);
            return 'palmares';
        });
    }
}
