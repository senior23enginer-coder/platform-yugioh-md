<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Teams;

use InvalidArgumentException;
use StationX\Yugioh\Application\Shared\TransactionBoundary;
use StationX\Yugioh\Domain\Competitions\TeamConfigurationPolicy;
use StationX\Yugioh\Domain\Competitions\TeamLineupPolicy;

final readonly class TeamEventConfiguration
{
    public function __construct(
        private TeamEventConfigurationStore $store,
        private TeamEventRules $rules,
        private TransactionBoundary $transaction,
    ) {}

    public function load(array $competition): ?array
    {
        if (empty($competition['id'])) {
            return null;
        }
        $json = $this->store->loadJson($competition);
        return $json === null ? null : TeamConfigurationPolicy::decodeState($json);
    }

    public function store(array $competition, array $state): void
    {
        $this->store->storeJson($competition, TeamConfigurationPolicy::encodeState($state));
    }

    public function context(array $competition): array
    {
        if ($this->rules->kind($competition) !== '') {
            $competition['_teams_configured'] = $this->load($competition) !== null;
        }
        return $competition;
    }

    public function save(int $id, array $rows): array
    {
        return $this->transaction->run(function () use ($id, $rows): array {
            $competition = $this->store->lockCompetition($id);
            if (!$competition) {
                throw new InvalidArgumentException('Evento inexistente.');
            }
            $this->rules->requireOpen((int) $competition['season_id']);
            $this->rules->assertFixtureMutable($id);
            TeamConfigurationPolicy::assertMutable($competition);
            $kind = $this->rules->kind($competition);
            $state = TeamLineupPolicy::lineups($rows, $kind);
            TeamConfigurationPolicy::assertMode($competition, $kind);
            $ids = TeamLineupPolicy::allIds($state);
            $this->rules->validateFormat((string) $competition['event_ruleset'], $ids);
            $actualFormat = $this->rules->actualFormat($ids);
            TeamConfigurationPolicy::assertSameFormat($competition, $actualFormat, false);
            $this->rules->validateLeague($id, $ids);
            if ($this->rules->hasAdmission($competition)) {
                throw new InvalidArgumentException('Los equipos se inscriben directamente; la clasificacion entre eventos de equipos requiere otro contrato de admision.');
            }
            $this->store->replaceRoster($id, $ids);
            $state['identities'] = $this->rules->identities($ids, $actualFormat);
            $this->store($competition, $state);
            $this->store->audit($id, $state);
            $this->rules->refreshGate($id);
            return $state;
        });
    }

    public function require(array $competition, array $ids): array
    {
        $state = $this->load($competition);
        if (!$state) {
            throw new InvalidArgumentException('Configura Equipos y alineaciones en la ficha antes de programar.');
        }
        $expected = TeamConfigurationPolicy::assertRoster($state, $ids);
        $this->rules->validateFormat($competition['event_ruleset'], $expected);
        $format = $this->rules->actualFormat($expected);
        TeamConfigurationPolicy::assertSameFormat($competition, $format, true);
        $this->rules->validateLeague((int) $competition['id'], $expected);
        if (empty($state['frozen'])) {
            $state['identities'] = $this->rules->identities($expected, $format);
        }
        $state['frozen'] = true;
        $this->store($competition, $state);
        return $state;
    }

    public function bind(array $competition, int $matchId, int $a, int $b, array $state): void
    {
        $binding = TeamConfigurationPolicy::binding($state, $a, $b);
        $this->store->bindMatch((int) $competition['id'], $matchId, $binding);
    }
}
