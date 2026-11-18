<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Combos;

use PDO;
use DomainException;
use StationX\Yugioh\Application\Combos\ComboRepository;

/** SQL adapter. It never commits current records independently of revision/source history. */
final readonly class PdoComboRepository implements ComboRepository
{
    public function __construct(private PDO $pdo) {}
    public function find(string $id): ?array
    {
        $query = $this->pdo->prepare('SELECT * FROM sx_ygo_created_combos WHERE id=?');
        $query->execute([$id]);
        return $query->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function forDeck(int $deckId): array
    {
        $query = $this->pdo->prepare(
            'SELECT * FROM sx_ygo_created_combos WHERE deck_id=? ORDER BY updated_at DESC,id',
        );
        $query->execute([$deckId]);
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function history(string $id): array
    {
        $query = $this->pdo->prepare(
            'SELECT revision,snapshot_json,created_at FROM sx_ygo_created_combo_revisions WHERE combo_id=? ORDER BY revision DESC',
        );
        $query->execute([$id]);
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function persist(array $record, bool $exists, int $expectedRevision): void
    {
        if ($exists) {
            $query = $this->pdo->prepare(
                'UPDATE sx_ygo_created_combos SET title=?,revision=?,inventory_sha256=?,document_json=?,inventory_json=?,updated_at=? WHERE id=? AND revision=?',
            );
            $query->execute([
                $record['title'],
                $record['revision'],
                $record['inventory_sha256'],
                $record['document_json'],
                $record['inventory_json'],
                $record['updated_at'],
                $record['id'],
                $expectedRevision,
            ]);
            if ($query->rowCount() !== 1) {
                throw new DomainException(
                    'El combo cambió durante el guardado. Tu edición se conserva.',
                );
            }
        } else {
            $this->pdo
                ->prepare(
                    'INSERT INTO sx_ygo_created_combos(id,deck_id,title,revision,inventory_sha256,document_json,inventory_json,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)',
                )
                ->execute([
                    $record['id'],
                    $record['deck_id'],
                    $record['title'],
                    $record['revision'],
                    $record['inventory_sha256'],
                    $record['document_json'],
                    $record['inventory_json'],
                    $record['updated_at'],
                    $record['updated_at'],
                ]);
        }
    }

    public function appendRevision(array $record, string $createdAt): void
    {
        $this->pdo
            ->prepare(
                'INSERT INTO sx_ygo_created_combo_revisions(combo_id,revision,snapshot_json,created_at) VALUES(?,?,?,?)',
            )
            ->execute([
                $record['id'],
                $record['revision'],
                json_encode($record, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                $createdAt,
            ]);
    }
}
