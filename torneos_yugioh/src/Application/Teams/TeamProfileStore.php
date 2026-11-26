<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Teams;

interface TeamProfileStore
{
    public function logo(int $id): string;

    public function save(int $id, array $profile): int;

    public function updateLogo(int $id, string $url): void;
}
