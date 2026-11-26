<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Matches;

use PDO;
use StationX\Yugioh\Application\Matches\LiveQueryReader;

final readonly class PdoLiveQueryReader implements LiveQueryReader
{
    public function __construct(private PDO $pdo) {}

    public function revision(): int
    {
        return (int) ($this->pdo
            ->query('SELECT COALESCE(MAX(id),0) FROM sx_ygo_audit_log')
            ->fetchColumn() ?:
        0);
    }

    public function ffaHistory(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT revision,payload,created_at FROM sx_ygo_ffa_actions WHERE match_id=? ORDER BY revision',
        );
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
