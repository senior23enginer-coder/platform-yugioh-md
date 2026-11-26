<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Decks;

use PDO;
use StationX\Yugioh\Application\Decks\DeckProfileServices;

final readonly class NativeDeckProfileServices implements DeckProfileServices
{
    public function __construct(private PDO $pdo) {}

    public function team(string $name, string $archetype): int
    {
        $profile = \ygo_team_profile_for_deck($name, $archetype);
        return \ygo_find_or_create_team($this->pdo, $profile['name'], $profile);
    }

    public function uploadCover(int $id): string
    {
        return \ygo_store_deck_logo_upload($id);
    }

    public function recordCover(int $id, string $url): void
    {
        \ygo_record_logo_history($this->pdo, 'deck', $id, $url);
    }
}
