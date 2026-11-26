<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Leagues;

use InvalidArgumentException;
use StationX\Yugioh\Application\Shared\TransactionBoundary;
use StationX\Yugioh\Domain\Competitions\DivisionMovementPlan;
use StationX\Yugioh\Domain\Competitions\DivisionMovementPolicy;

final readonly class ApplyDivisionMovements
{
    public function __construct(
        private DivisionMovementReader $reader,
        private DivisionMovementWriter $writer,
        private TransactionBoundary $transaction,
    ) {}

    public function execute(int $competitionId): int
    {
        return $this->transaction->run(function () use ($competitionId): int {
            $competition = $this->reader->lockCompetition($competitionId);
            $divisions = $this->reader->divisions($competitionId);
            if (
                !$competition ||
                $competition['type'] !== 'league' ||
                count($divisions) < 2 ||
                !in_array($competition['status'], ['completed', 'finalizado', 'finalizada'], true) ||
                (int) $competition['lock_division_moves'] === 1
            ) {
                return 0;
            }
            if ($this->reader->alreadyApplied($competitionId)) {
                return 0;
            }
            if ($this->reader->hasPendingMatches($competitionId)) {
                throw new InvalidArgumentException(
                    'Completa los encuentros antes de aplicar ascensos y descensos.',
                );
            }
            $input = [];
            foreach ($divisions as $division) {
                $input[$division['id']] = $division;
            }
            $plans = DivisionMovementPolicy::validateDivisionMovements($divisions, $input);
            $ranking = $this->reader->standings($competitionId);
            $original = $this->reader->originalDivisions();
            $moves = DivisionMovementPlan::build($plans, $ranking, $original);
            if (!$moves) {
                return 0;
            }
            $this->writer->apply($competitionId, $moves);
            return count($moves);
        });
    }
}
