<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Matches;

interface FfaActionGateway
{
    public function command(int $id, int $revision, string $request, array $command): void;
    public function competition(int $id): int;
}
