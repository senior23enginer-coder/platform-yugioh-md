<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\Decks\FindDeckDeletionBlocker;
use StationX\Yugioh\Application\Decks\GroupMatchRoster;
use StationX\Yugioh\Application\Decks\ResolveEligibleDecks;
use StationX\Yugioh\Application\Decks\ValidateMatchDecks;
use StationX\Yugioh\Infrastructure\Persistence\Decks\PdoCompetitionEligibilityReader;
use StationX\Yugioh\Infrastructure\Persistence\Decks\PdoDeckDeletionReader;
use StationX\Yugioh\Infrastructure\Persistence\Decks\PdoMatchRosterReader;

final class DeckEligibilityModule
{
    public static function eligible(PDO $pdo): ResolveEligibleDecks
    {
        return new ResolveEligibleDecks(new PdoCompetitionEligibilityReader($pdo));
    }

    public static function validate(PDO $pdo): ValidateMatchDecks
    {
        return new ValidateMatchDecks(self::eligible($pdo), new PdoMatchRosterReader($pdo));
    }

    public static function group(PDO $pdo): GroupMatchRoster
    {
        return new GroupMatchRoster(new PdoMatchRosterReader($pdo));
    }

    public static function deletion(PDO $pdo): FindDeckDeletionBlocker
    {
        return new FindDeckDeletionBlocker(new PdoDeckDeletionReader($pdo));
    }
}
