<?php
declare(strict_types=1);

/**
 * deck-eligibility: compatibility services extracted from the web entry point.
 * Preserve public functions, transaction scopes and serialized write locks.
 * See context/source-architecture.md for the current migration boundaries.
 */

function ygo_deck_delete_blocker(PDO $pdo, int $deckId): ?array
{
    return \StationX\Yugioh\Infrastructure\Composition\DeckEligibilityModule::deletion($pdo)->execute($deckId);
}

function ygo_allowed_deck_ids_for_competition(PDO $pdo, ?int $competitionId, int $manualDivisionId): array
{
    return \StationX\Yugioh\Infrastructure\Composition\DeckEligibilityModule::eligible($pdo)->execute($competitionId, $manualDivisionId);
}

function ygo_match_deck_validation(PDO $pdo, ?int $competitionId, int $manualDivisionId, array $deckIds, int $participantsPerSide = 1): array
{
    return \StationX\Yugioh\Infrastructure\Composition\DeckEligibilityModule::validate($pdo)->execute(
        $competitionId,
        $manualDivisionId,
        $deckIds,
        $participantsPerSide,
    );
}

function ygo_match_decks_allowed(PDO $pdo, ?int $competitionId, int $manualDivisionId, array $deckIds, int $participantsPerSide = 1): bool
{
    return ygo_match_deck_validation($pdo, $competitionId, $manualDivisionId, $deckIds, $participantsPerSide)['ok'];
}

function ygo_roster_groups_for_match(PDO $pdo, array $selectedDeckIds, int $participantsPerSide): array
{
    return \StationX\Yugioh\Infrastructure\Composition\DeckEligibilityModule::group($pdo)->execute($selectedDeckIds, $participantsPerSide);
}
