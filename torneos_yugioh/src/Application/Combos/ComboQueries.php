<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Combos;

use InvalidArgumentException;

/** Uses loaded inventory identity to scope saved guides and their revisions to one deck. */
final readonly class ComboQueries
{
    public function __construct(
        private ComboInventory $inventory,
        private ComboRepository $records,
    ) {}
    public function overview(int $deckId, ?string $historyId = null): array
    {
        $inventory = $this->inventory->load($deckId, false, false);
        $hash = $inventory['inventory_sha256'];
        $rows = array_map(
            static fn(array $row): array => ComboRecordView::unpack($row, $hash),
            $this->records->forDeck($deckId),
        );
        $answer = $inventory + ['records' => $rows];
        if ($historyId !== null) {
            if (
                !preg_match('/^[a-zA-Z0-9-]{16,64}$/D', $historyId) ||
                !array_filter($rows, static fn($row) => $row['id'] === $historyId)
            ) {
                throw new InvalidArgumentException('El combo no pertenece a este deck.');
            }
            $answer['history'] = array_map(
                static fn(array $row): array => [
                    'revision' => (int) $row['revision'],
                    'created_at' => $row['created_at'],
                    'record' => ComboRecordView::unpack(
                        json_decode($row['snapshot_json'], true, 128, JSON_THROW_ON_ERROR),
                        $hash,
                    ),
                ],
                $this->records->history($historyId),
            );
        }
        return $answer;
    }
}
