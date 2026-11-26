<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\DeckPackages;

use StationX\Yugioh\Application\Decks\Packages\DeckPackageTicketStore;

final class SessionDeckPackageTicketStore implements DeckPackageTicketStore
{
    public function pending(): array
    {
        return $_SESSION['ygo_deck_data_pending'] ?? [];
    }

    public function save(array $pending): void
    {
        $_SESSION['ygo_deck_data_pending'] = $pending;
    }

    public function clear(): void
    {
        unset($_SESSION['ygo_deck_data_pending']);
    }
}
