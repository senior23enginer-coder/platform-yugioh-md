<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decks;

final readonly class RemoveDeck
{
    public function __construct(
        private FindDeckDeletionBlocker $blocker,
        private DeckRemovalStore $store,
    ) {}

    public function execute(array $input): string
    {
        $id = (int) ($input['deck_id'] ?? 0);
        if ($id > 0) {
            $blocker = $this->blocker->execute($id);
            if ($blocker) {
                $this->store->auditBlocked($id, $blocker);
                return 'deck_bloqueado_evento';
            }
            $this->store->archive($id);
        }
        return 'deck_quitado';
    }
}
