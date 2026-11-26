<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Statistics;

use PDO;
use RuntimeException;
use StationX\Yugioh\Application\Statistics\StatisticsRevisionStore;
use StationX\Yugioh\Application\Statistics\StatisticsViewReader;
use StationX\Yugioh\Domain\Statistics\StatisticsPolicy;
use StationX\Yugioh\Domain\Statistics\StatisticsRevisionPolicy;

final readonly class PdoStatisticsRevisionStore implements StatisticsRevisionStore, StatisticsViewReader
{
    public function __construct(private PDO $pdo) {}

    public function installed(): bool
    {
        return \ygo_table_exists($this->pdo, 'sx_ygo_statistics_revisions');
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    public function underLock(callable $operation): mixed
    {
        if ((int) $this->pdo->query("SELECT GET_LOCK('station_x_statistics_v8',10)")->fetchColumn() !== 1) {
            throw new RuntimeException('Calculo estadistico ocupado; reintenta.');
        }
        try {
            return $operation();
        } finally {
            $this->pdo->query("SELECT RELEASE_LOCK('station_x_statistics_v8')");
        }
    }

    public function policy(): ?string
    {
        $value = $this->pdo->query("SELECT payload FROM sx_ygo_statistics_policy WHERE scope_key='global'")->fetchColumn();
        return $value === false ? null : (string) $value;
    }

    public function publications(bool $full): array
    {
        if (!\ygo_table_exists($this->pdo, 'sx_ygo_statistics_publications')) {
            return [];
        }
        $query = $this->pdo->query('SELECT * FROM sx_ygo_statistics_publications ORDER BY cut_at,id');
        $cuts = [];
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $cuts[] = StatisticsRevisionPolicy::publication($row, $full);
        }
        return $cuts;
    }

    public function revision(string $hash): int
    {
        $query = $this->pdo->prepare('SELECT id FROM sx_ygo_statistics_revisions WHERE source_hash=? AND algorithm_version=?');
        $query->execute([$hash, StatisticsPolicy::VERSION]);
        return (int) $query->fetchColumn();
    }

    public function activate(int $revision): void
    {
        $this->pdo->prepare('INSERT INTO sx_ygo_statistics_current(id,revision_id) VALUES(1,?) ON DUPLICATE KEY UPDATE revision_id=VALUES(revision_id)')->execute([$revision]);
    }

    public function previousPayload(): ?string
    {
        $value = $this->pdo->query('SELECT r.payload FROM sx_ygo_statistics_revisions r JOIN sx_ygo_statistics_current c ON c.revision_id=r.id WHERE c.id=1')->fetchColumn();
        return $value === false ? null : (string) $value;
    }

    public function persistMedals(array $medals): void
    {
        $query = $this->pdo->prepare('INSERT IGNORE INTO sx_ygo_statistics_medals(event_id,bonus_points,bonus_version,definition_json) VALUES(?,?,?,?)');
        foreach ($medals as $medal) {
            $query->execute([$medal['event_id'], $medal['bonus_points'], $medal['bonus_version'], json_encode($medal)]);
        }
    }

    public function previousRevision(): ?int
    {
        $value = $this->pdo->query('SELECT revision_id FROM sx_ygo_statistics_current WHERE id=1')->fetchColumn();
        return $value ? (int) $value : null;
    }

    public function insertRevision(string $hash, array $data, ?int $previous): array
    {
        $query = $this->pdo->prepare('INSERT IGNORE INTO sx_ygo_statistics_revisions(source_hash,algorithm_version,payload,previous_revision_id,change_reason) VALUES(?,?,?,?,?)');
        $query->execute([$hash, StatisticsPolicy::VERSION, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION), $previous, 'Relectura del origen confirmado: comparar versiones de match y hash de fuente.']);
        $count = $query->rowCount();
        return ['count' => $count, 'id' => (int) $this->pdo->lastInsertId()];
    }

    public function history(): array
    {
        return $this->pdo->query('SELECT id,source_hash,algorithm_version,created_at,previous_revision_id,change_reason FROM sx_ygo_statistics_revisions ORDER BY id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
    }
}
