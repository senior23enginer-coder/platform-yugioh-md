<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Leagues;

use StationX\Yugioh\Domain\Competitions\DivisionMovementPolicy;

final readonly class ConfigureDivisionMovements
{
    public function __construct(
        private DivisionMovementReader $reader,
        private DivisionMovementWriter $writer,
    ) {}

    public function execute(int $competitionId, array $input): void
    {
        $rows = DivisionMovementPolicy::validateDivisionMovements(
            $this->reader->divisions($competitionId),
            $input,
        );
        $this->writer->saveConfiguration($competitionId, $rows);
    }
}
