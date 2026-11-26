<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Calendar;

use PDO;
use StationX\Yugioh\Application\Calendar\LegacyQualifierCalendarReader;

final readonly class PdoLegacyQualifierCalendarReader implements LegacyQualifierCalendarReader
{
    public function __construct(private PDO $pdo) {}

    public function currentSeasonId(): int
    {
        return \ygo_current_season_id($this->pdo);
    }

    public function firstDivisionId(): int
    {
        return \ygo_first_division_id($this->pdo);
    }

    public function selectedQualifierId(): ?int
    {
        return \ygo_selected_qualifier_competition_id($this->pdo);
    }

    public function activeDeckIds(): array
    {
        return array_map(
            'intval',
            $this->pdo
                ->query('SELECT id FROM sx_ygo_decks WHERE active = 1 ORDER BY seed_rank, id')
                ->fetchAll(PDO::FETCH_COLUMN),
        );
    }
}
