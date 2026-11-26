<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decks;

use RuntimeException;
use StationX\Yugioh\Application\Shared\TransactionBoundary;
use StationX\Yugioh\Domain\Decks\DeckAnalysisInput;

final readonly class SaveDeckAnalysisProfile
{
    public function __construct(
        private DeckAnalysisStore $store,
        private TransactionBoundary $transaction,
    ) {}

    public function execute(array $post): string
    {
        $deckId = max(0, (int) ($post['deck_analysis_deck_id'] ?? 0));
        if (!$this->store->activeDeck($deckId)) {
            throw new RuntimeException('El deck seleccionado ya no esta activo.');
        }
        $input = DeckAnalysisInput::profile($post);
        $this->transaction->run(function () use ($deckId, $input): void {
            $this->store->saveProfile($deckId, $input['values']);
            $saved = $this->store->profile($deckId);
            $revision = (int) ($saved['revision'] ?? 1);
            $this->store->profileHistory($deckId, $revision, $saved, $input['reason']);
            $this->store->audit('deck_analysis', $deckId, 'save_deck_analysis_profile', [
                'revision' => $revision,
                'source_type' => $input['sourceType'],
            ]);
        });
        return 'deck_analysis_' . $deckId;
    }
}
