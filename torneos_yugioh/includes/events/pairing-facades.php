<?php
declare(strict_types=1);

/**
 * pairing-facades: compatibility services extracted from the web entry point.
 * Preserve public functions, transaction scopes and serialized write locks.
 * See context/source-architecture.md for the current migration boundaries.
 */

function buildFixtures(array $decks): array
{
    return \StationX\Yugioh\Domain\Competitions\FixtureBuilder::build($decks);
}

function ygo_knockout_target_size(int $participants): int
{
    return \StationX\Yugioh\Domain\Competitions\KnockoutSize::forParticipants($participants);
}

function ygo_round_robin_pairs(array $groups, bool $doubleRound = false): array
{
    return \StationX\Yugioh\Domain\Competitions\RoundRobinPairings::rounds($groups, $doubleRound);
}
