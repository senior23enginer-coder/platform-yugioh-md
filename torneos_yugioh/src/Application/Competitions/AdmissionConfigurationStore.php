<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface AdmissionConfigurationStore
{
    public function load(array $competition, bool $lock = false): ?array;

    public function store(array $competition, array $admission): void;
}
