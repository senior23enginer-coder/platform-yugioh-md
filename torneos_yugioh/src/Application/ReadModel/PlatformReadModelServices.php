<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\ReadModel;

interface PlatformReadModelServices
{
    public function currentSeasonId(): int;

    public function dependencies(): array;

    public function progressGroups(): array;

    public function progress(array $rows, bool $knockout): array;

    public function gate(int $id): array;

    public function allowedDecks(int $id, int $divisionId): array;

    public function standings(int $id): array;

    public function backfill(int $seasonId): void;

    public function deckMetadata(array $decks, array $cards, array $profiles): array;

    public function seasonEvents(): array;

    public function settings(): array;

    public function labels(): array;
}
