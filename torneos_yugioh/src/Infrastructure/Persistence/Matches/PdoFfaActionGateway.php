<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Matches;

use PDO;
use StationX\Yugioh\Application\Matches\FfaActionGateway;

final readonly class PdoFfaActionGateway implements FfaActionGateway
{
    public function __construct(private PDO $pdo) {}

    public function command(int $id, int $revision, string $request, array $command): void
    {
        \ygo_ffa_command($this->pdo, $id, $revision, $request, $command);
    }

    public function competition(int $id): int
    {
        $stmt = $this->pdo->prepare('SELECT competition_id FROM sx_ygo_ffa_matches WHERE id=?');
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn();
    }
}
