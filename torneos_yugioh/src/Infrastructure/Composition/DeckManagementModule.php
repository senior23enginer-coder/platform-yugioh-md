<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\Decks\AddDeckCard;
use StationX\Yugioh\Application\Decks\ImportDeckCards;
use StationX\Yugioh\Application\Decks\RemoveDeck;
use StationX\Yugioh\Application\Decks\SaveDeckMedia;
use StationX\Yugioh\Application\Decks\SaveDeckProfile;
use StationX\Yugioh\Infrastructure\Persistence\Decks\NativeDeckProfileServices;
use StationX\Yugioh\Infrastructure\Persistence\Decks\PdoDeckCardWriter;
use StationX\Yugioh\Infrastructure\Persistence\Decks\PdoDeckMediaWriter;
use StationX\Yugioh\Infrastructure\Persistence\Decks\PdoDeckProfileStore;
use StationX\Yugioh\Infrastructure\Persistence\Decks\PdoDeckRemovalStore;
use StationX\Yugioh\Infrastructure\Transactions\PdoTransactionBoundary;

final class DeckManagementModule
{
    public static function card(PDO $pdo): AddDeckCard
    {
        return new AddDeckCard(new PdoDeckCardWriter($pdo));
    }

    public static function import(PDO $pdo): ImportDeckCards
    {
        return new ImportDeckCards(new PdoDeckCardWriter($pdo), new PdoTransactionBoundary($pdo, false));
    }

    public static function save(PDO $pdo): SaveDeckProfile
    {
        return new SaveDeckProfile(new PdoDeckProfileStore($pdo), new NativeDeckProfileServices($pdo));
    }

    public static function remove(PDO $pdo): RemoveDeck
    {
        return new RemoveDeck(DeckEligibilityModule::deletion($pdo), new PdoDeckRemovalStore($pdo));
    }

    public static function media(PDO $pdo): SaveDeckMedia
    {
        return new SaveDeckMedia(new PdoDeckMediaWriter($pdo));
    }
}
