<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\DeckWorlds;

use DomainException;
use InvalidArgumentException;
use StationX\Yugioh\Application\DeckWorlds\Ports\EligibleDecks;
use StationX\Yugioh\Application\DeckWorlds\Ports\WorldAssignmentWriter;
use StationX\Yugioh\Application\Shared\Clock;
use StationX\Yugioh\Application\Shared\TransactionBoundary;
use StationX\Yugioh\Domain\DeckWorlds\WorldAssignment;

/** Coordinates eligibility, optimistic concurrency and atomic revision persistence. */
final readonly class AssignDeckWorlds
{
    public function __construct(
        private WorldAssignmentWriter $assignments,
        private EligibleDecks $eligibleDecks,
        private TransactionBoundary $transaction,
        private Clock $clock,
    ) {}

    public function execute(AssignWorldsCommand $command): WorldAssignment
    {
        return $this->transaction->run(function () use ($command): WorldAssignment {
            // Lock the deck even when its first assignment does not exist yet.
            if (!$this->assignments->lockExistingDeck($command->deckId)) {
                throw new InvalidArgumentException('El deck no existe.');
            }
            if (!in_array($command->deckId, $this->eligibleDecks->ids(), true)) {
                throw new InvalidArgumentException('Mundos solo está disponible para decks TCG.');
            }
            $current = $this->assignments->findForUpdate($command->deckId);
            if ($command->expectedRevision !== ($current?->revision ?? 0)) {
                throw new DomainException(
                    'Otra edición cambió los mundos. Actualiza la lista antes de volver a guardar; tu selección sigue en pantalla.',
                );
            }
            if ($current && $current->selection->equals($command->selection)) {
                return $current;
            }
            $next = new WorldAssignment(
                $command->deckId,
                $command->selection,
                $command->expectedRevision + 1,
                $this->clock->now()->format('Y-m-d H:i:s'),
            );
            $this->assignments->persistWithHistory($next, $current !== null);
            return $next;
        });
    }
}
