<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\ReadModel;

interface SportingReadModelReader
{
    public function divisions(int $seasonId): array;

    public function matches(): array;

    public function competitions(): array;

    public function competitionDeckRows(): array;

    public function events(): array;

    public function seasons(): array;
}
