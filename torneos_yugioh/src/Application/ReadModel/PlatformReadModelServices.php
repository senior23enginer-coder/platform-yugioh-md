<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\ReadModel;

interface PlatformReadModelServices extends CompetitionReadModelServices
{
    public function currentSeasonId(): int;

    public function backfill(int $seasonId): void;

    public function deckMetadata(array $decks, array $cards, array $profiles): array;

    public function seasonEvents(): array;

    public function settings(): array;

    public function labels(): array;
}
