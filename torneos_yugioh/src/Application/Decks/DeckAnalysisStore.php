<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decks;

interface DeckAnalysisStore
{
    public function activeDeck(int $id): bool;

    public function activePair(int $deckId, int $opponentId): bool;

    public function saveProfile(int $deckId, array $values): void;

    public function profile(int $deckId): array;

    public function profileHistory(int $deckId, int $revision, array $saved, string $reason): void;

    public function saveMatchup(int $deckId, int $opponentId, array $scope, array $values): void;

    public function matchup(int $deckId, int $opponentId, array $scope): array;

    public function matchupHistory(
        int $analysisId,
        int $deckId,
        int $opponentId,
        int $revision,
        array $analysis,
        string $reason,
    ): void;

    public function audit(string $entity, int $id, string $action, array $payload): void;
}
