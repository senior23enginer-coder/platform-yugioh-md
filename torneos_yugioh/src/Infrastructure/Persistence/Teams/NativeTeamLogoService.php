<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Teams;

use PDO;
use StationX\Yugioh\Application\Teams\TeamLogoService;

final readonly class NativeTeamLogoService implements TeamLogoService
{
    public function __construct(private PDO $pdo) {}

    public function upload(int $id): string
    {
        return \ygo_store_team_logo_upload($id);
    }

    public function record(int $id, string $url): void
    {
        \ygo_record_logo_history($this->pdo, 'team', $id, $url);
    }
}
