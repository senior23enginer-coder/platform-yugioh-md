<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\ReadModel;

use PDO;
use StationX\Yugioh\Application\ReadModel\PlatformReadModelServices;

final readonly class NativePlatformReadModelServices implements PlatformReadModelServices
{
    public function __construct(private PDO $pdo) {}

    public function currentSeasonId(): int
    {
        return \ygo_current_season_id($this->pdo);
    }

    public function dependencies(): array
    {
        return \ygo_competition_dependencies($this->pdo);
    }

    public function progressGroups(): array
    {
        return \ygo_event_progress_groups($this->pdo);
    }

    public function progress(array $rows, bool $knockout): array
    {
        return \ygo_event_progress($rows, $knockout);
    }

    public function gate(int $id): array
    {
        return \ygo_competition_gate($this->pdo, $id);
    }

    public function allowedDecks(int $id, int $divisionId): array
    {
        return \ygo_allowed_deck_ids_for_competition($this->pdo, $id, $divisionId);
    }

    public function standings(int $id): array
    {
        return \ygo_competition_standings($this->pdo, $id);
    }

    public function backfill(int $seasonId): void
    {
        \ygo_backfill_missing_standing_snapshots($this->pdo, $seasonId);
    }

    public function deckMetadata(array $decks, array $cards, array $profiles): array
    {
        return \ygo_deck_list_metadata($decks, $cards, $profiles);
    }

    public function seasonEvents(): array
    {
        return \ygo_season_event_rows($this->pdo);
    }

    public function settings(): array
    {
        return \ygo_settings($this->pdo);
    }

    public function labels(): array
    {
        return \ygo_ui_labels($this->pdo);
    }
}
