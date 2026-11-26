<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

use StationX\Yugioh\Domain\Competitions\LegacyEventFixturePlan;

final readonly class GenerateLegacyEventFixtures
{
    public function __construct(
        private LegacyEventFixtureReader $reader,
        private LegacyEventFixtureWriter $writer,
    ) {}

    public function regenerateLeagues(int $seasonId): void
    {
        foreach ($this->reader->divisions($seasonId) as $division) {
            $divisionId = (int) $division['id'];
            $this->writer->clearPhase($divisionId, 'league');
            $decks = $this->reader->leagueDecks($divisionId);
            $this->writer->insert($divisionId, 'league', LegacyEventFixturePlan::league($decks));
        }
    }

    public function knockout(int $divisionId, string $phase, array $decks): int
    {
        $this->writer->clearPhase($divisionId, $phase);
        return $this->writer->insertKnockout($divisionId, $phase, LegacyEventFixturePlan::knockout($decks));
    }

    public function friendlies(): int
    {
        $divisionId = $this->reader->firstDivisionId();
        $decks = $this->reader->decks();
        if (!$divisionId || count($decks) < 2) {
            return 0;
        }
        $this->writer->clearPhase($divisionId, 'friendly');
        return $this->writer->insert($divisionId, 'friendly', LegacyEventFixturePlan::friendlies($decks));
    }

    public function cups(): int
    {
        $seasonId = $this->reader->currentSeasonId();
        $created = 0;
        foreach ($this->reader->divisions($seasonId) as $division) {
            $divisionId = (int) $division['id'];
            $created += $this->knockout($divisionId, 'cup', $this->reader->decks($divisionId));
        }
        return $created;
    }

    public function world(): int
    {
        $divisionId = $this->reader->firstDivisionId();
        $decks = array_slice($this->reader->decks(), 0, 24);
        if (!$divisionId || count($decks) < 2) {
            return 0;
        }
        $this->writer->clearPhase($divisionId, 'world');
        return $this->writer->insert($divisionId, 'world', LegacyEventFixturePlan::world($decks));
    }

    public function leagues(): int
    {
        $this->regenerateLeagues($this->reader->currentSeasonId());
        return $this->reader->leagueMatchCount();
    }
}
