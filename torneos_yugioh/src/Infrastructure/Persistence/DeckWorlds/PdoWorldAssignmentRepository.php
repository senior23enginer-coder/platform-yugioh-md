<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\DeckWorlds;

use PDO;
use StationX\Yugioh\Application\DeckWorlds\Ports\WorldAssignmentReader;
use StationX\Yugioh\Application\DeckWorlds\Ports\WorldAssignmentWriter;
use StationX\Yugioh\Domain\DeckWorlds\WorldAssignment;

/** SQL adapter. Writes never commit independently of assignment history or the deck lock. */
final readonly class PdoWorldAssignmentRepository implements
    WorldAssignmentReader,
    WorldAssignmentWriter
{
    public function __construct(private PDO $pdo) {}

    public function all(): array
    {
        $rows = $this->pdo
            ->query(
                'SELECT a.* FROM sx_ygo_deck_attributes a JOIN sx_ygo_decks d ON d.id=a.deck_id ORDER BY a.deck_id',
            )
            ->fetchAll(PDO::FETCH_ASSOC);
        return array_map(WorldAssignmentCodec::fromRow(...), $rows);
    }

    public function history(int $deckId): array
    {
        $query = $this->pdo->prepare(
            'SELECT * FROM sx_ygo_deck_attribute_revisions WHERE deck_id=? ORDER BY revision DESC LIMIT 25',
        );
        $query->execute([$deckId]);
        return array_map(WorldAssignmentCodec::fromRow(...), $query->fetchAll(PDO::FETCH_ASSOC));
    }

    public function lockExistingDeck(int $deckId): bool
    {
        $query = $this->pdo->prepare(
            'SELECT id FROM sx_ygo_decks WHERE id=?' . $this->lockSuffix(),
        );
        $query->execute([$deckId]);
        return (bool) $query->fetchColumn();
    }

    public function findForUpdate(int $deckId): ?WorldAssignment
    {
        $query = $this->pdo->prepare(
            'SELECT * FROM sx_ygo_deck_attributes WHERE deck_id=?' . $this->lockSuffix(),
        );
        $query->execute([$deckId]);
        $row = $query->fetch(PDO::FETCH_ASSOC);
        return $row ? WorldAssignmentCodec::fromRow($row) : null;
    }

    public function persistWithHistory(WorldAssignment $assignment, bool $alreadyExists): void
    {
        $json = json_encode($assignment->selection->codes, JSON_THROW_ON_ERROR);
        if ($alreadyExists) {
            $this->pdo
                ->prepare(
                    'UPDATE sx_ygo_deck_attributes SET attributes_json=?,revision=?,updated_at=? WHERE deck_id=?',
                )
                ->execute([
                    $json,
                    $assignment->revision,
                    $assignment->updatedAt,
                    $assignment->deckId,
                ]);
        } else {
            $this->pdo
                ->prepare(
                    'INSERT INTO sx_ygo_deck_attributes(deck_id,attributes_json,revision,updated_at) VALUES(?,?,?,?)',
                )
                ->execute([
                    $assignment->deckId,
                    $json,
                    $assignment->revision,
                    $assignment->updatedAt,
                ]);
        }
        $this->pdo
            ->prepare(
                'INSERT INTO sx_ygo_deck_attribute_revisions(deck_id,revision,attributes_json,changed_at) VALUES(?,?,?,?)',
            )
            ->execute([$assignment->deckId, $assignment->revision, $json, $assignment->updatedAt]);
    }

    private function lockSuffix(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    }
}
