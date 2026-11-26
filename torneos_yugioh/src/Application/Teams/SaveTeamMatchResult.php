<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Teams;

use DateTimeZone;
use InvalidArgumentException;
use StationX\Yugioh\Application\Shared\Clock;
use StationX\Yugioh\Application\Shared\TransactionBoundary;
use StationX\Yugioh\Domain\Competitions\CompetitionLifecyclePolicy;
use StationX\Yugioh\Domain\Competitions\TeamLineupPolicy;
use StationX\Yugioh\Domain\Competitions\TeamResultPolicy;

final readonly class SaveTeamMatchResult
{
    public function __construct(
        private TeamResultStore $store,
        private TeamResultServices $services,
        private TeamEventConfiguration $configuration,
        private TransactionBoundary $transaction,
        private Clock $clock,
    ) {}

    public function execute(int $matchId, array $sequences, int $revision): array
    {
        return $this->transaction->run(function () use ($matchId, $sequences, $revision): array {
            $competition = $this->services->competitionForMatch($matchId);
            if (!$competition || $this->services->kind($competition) === '') {
                throw new InvalidArgumentException('El combate no es de equipos.');
            }
            $competition = $this->store->lockCompetition((int) $competition['id']);
            if (!$competition) {
                throw new InvalidArgumentException('El combate no es de equipos.');
            }
            $this->services->requireOpen((int) $competition['season_id']);
            if (in_array(CompetitionLifecyclePolicy::normalizeStatus($competition['status']), ['completed', 'closed', 'cancelled', 'archived'], true)) {
                throw new InvalidArgumentException('El evento esta cerrado.');
            }
            $this->services->assertEditable($matchId);
            $date = $this->services->validateDate($matchId);
            if (!$date['ok']) {
                throw new InvalidArgumentException('El combate no puede registrarse antes de su fecha programada.');
            }
            $state = $this->configuration->load($competition);
            $workflow = $this->services->workflow($competition);
            $match = TeamResultPolicy::currentMatch($state, $workflow, $matchId);
            $db = $this->store->lockMatch($matchId) ?? [];
            $left = TeamLineupPolicy::lineup($state, $match['a']);
            $right = TeamLineupPolicy::lineup($state, $match['b']);
            TeamResultPolicy::assertSeats($db, $left, $right);
            $old = $this->store->load((int) $competition['season_id'], $matchId);
            TeamResultPolicy::assertRevision($old, $revision);
            if (!array_is_list($sequences)) {
                throw new InvalidArgumentException('Los puestos deben estar ordenados sin saltos.');
            }
            $evaluations = [];
            foreach ($sequences as $sequence) {
                if (!is_array($sequence) || !array_is_list($sequence)) {
                    throw new InvalidArgumentException('Secuencia invalida.');
                }
                $evaluations[] = $this->services->evaluate($matchId, $sequence);
            }
            $aggregate = TeamLineupPolicy::aggregate($state['kind'], $evaluations, in_array($match['phase'], ['swiss', 'groups', 'league'], true));
            $now = $this->clock->now()->setTimezone(new DateTimeZone('America/Bogota'))->format('Y-m-d H:i:s');
            $record = TeamResultPolicy::record($state, $left, $right, $sequences, $evaluations, $aggregate, $old, $revision, (string) $db['status'], $now);
            $this->store->save((int) $competition['season_id'], $matchId, $record);
            $this->store->saveCollective($matchId, TeamResultPolicy::collectiveDuels($state['kind'], $sequences), $aggregate);
            $this->services->finalize($matchId, $now);
            $this->services->standings((int) $competition['season_id'], (int) $competition['id']);
            $this->store->audit($matchId, $record);
            $this->services->sync($matchId);
            return $record;
        });
    }
}
