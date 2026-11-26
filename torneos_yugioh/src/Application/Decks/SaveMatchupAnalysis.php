<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decks;

use RuntimeException;
use StationX\Yugioh\Application\Shared\TransactionBoundary;
use StationX\Yugioh\Domain\Decks\DeckAnalysisInput;

final readonly class SaveMatchupAnalysis
{
    public function __construct(private DeckAnalysisStore $store, private TransactionBoundary $transaction) {}

    public function execute(array $post): string
    {
        $deckId = max(0, (int) ($post['matchup_deck_id'] ?? 0));
        $opponentId = max(0, (int) ($post['matchup_opponent_deck_id'] ?? 0));
        if ($deckId <= 0 || $opponentId <= 0 || $deckId === $opponentId) {
            throw new RuntimeException('Selecciona dos decks diferentes para guardar el analisis.');
        }
        if (!$this->store->activePair($deckId, $opponentId)) {
            throw new RuntimeException('Uno de los decks del matchup ya no esta activo.');
        }
        $input = DeckAnalysisInput::matchup($post);
        $this->transaction->run(function () use ($deckId, $opponentId, $input): void {
            $this->store->saveMatchup($deckId, $opponentId, $input['scope'], $input['values']);
            $analysis = $this->store->matchup($deckId, $opponentId, $input['scope']);
            $analysisId = (int) ($analysis['id'] ?? 0);
            $revision = (int) ($analysis['revision'] ?? 1);
            $this->store->matchupHistory($analysisId, $deckId, $opponentId, $revision, $analysis, $input['reason']);
            $this->store->audit('matchup_analysis', $analysisId, 'save_matchup_analysis', [
                'deck_id' => $deckId,
                'opponent_deck_id' => $opponentId,
                'revision' => $revision,
                'source_type' => $input['sourceType'],
            ]);
        });
        return 'matchup_deck_' . $deckId;
    }
}
