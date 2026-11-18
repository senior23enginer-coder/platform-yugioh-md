<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Combos;

use DomainException;
use InvalidArgumentException;
use StationX\Yugioh\Application\Shared\Clock;
use StationX\Yugioh\Application\Shared\TransactionBoundary;

/** Coordinates optimistic revisions, inventory integrity and atomic source/history snapshots. */
final readonly class SaveCreatedCombo
{
    public function __construct(
        private ComboInventory $inventory,
        private ComboRepository $records,
        private PrepareComboDocument $documents,
        private ComboSources $sources,
        private TransactionBoundary $transaction,
        private Clock $clock,
    ) {}
    public function execute(array $input): array
    {
        if (
            !isset($input['deck_id'], $input['revision'], $input['document']) ||
            !is_int($input['deck_id']) ||
            $input['deck_id'] < 1 ||
            !is_int($input['revision']) ||
            $input['revision'] < 0 ||
            !is_array($input['document'])
        ) {
            throw new InvalidArgumentException('Deck, documento o revisión no válidos.');
        }
        return $this->transaction->run(function () use ($input): array {
            $inventory = $this->inventory->load($input['deck_id'], true);
            if (
                !hash_equals(
                    $inventory['inventory_sha256'],
                    (string) ($input['inventory_sha256'] ?? ''),
                )
            ) {
                throw new DomainException(
                    'La lista de cartas cambió. Recarga las cartas antes de guardar; tu trabajo sigue en el editor.',
                );
            }
            $document = $this->documents->execute($input['document'], $inventory);
            $json = json_encode($document, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $old = $this->records->find($document['id']);
            $revision = $input['revision'];
            if ($old && (int) $old['deck_id'] !== (int) $document['deck_id']) {
                throw new InvalidArgumentException('No puedes trasladar un combo a otro deck.');
            }
            if ($old && (int) $old['revision'] !== $revision) {
                if (
                    $old['document_json'] === $json &&
                    $old['inventory_sha256'] === $inventory['inventory_sha256']
                ) {
                    return ComboRecordView::unpack($old, $inventory['inventory_sha256']);
                }
                throw new DomainException(
                    'Otra edición guardó cambios. Abre esa versión en otra pestaña antes de reemplazar tu trabajo.',
                );
            }
            if (!$old && $revision !== 0) {
                throw new DomainException('La versión del combo ya no está disponible.');
            }
            $now = $this->clock->now()->format('Y-m-d H:i:s');
            $row = [
                'id' => $document['id'],
                'deck_id' => $document['deck_id'],
                'title' => $document['title'],
                'revision' => $revision + 1,
                'inventory_sha256' => $inventory['inventory_sha256'],
                'document_json' => $json,
                'inventory_json' => json_encode(
                    $inventory['cards'],
                    JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                ),
                'updated_at' => $now,
            ];
            $this->records->persist($row, $old !== null, $revision);
            if (isset($document['source_route_id'])) {
                $row['source_prompt'] = $this->sources->markdown(
                    ComboRecordView::unpack($row, $inventory['inventory_sha256']),
                );
            }
            $this->records->appendRevision($row, $now);
            return ComboRecordView::unpack($row, $inventory['inventory_sha256']);
        });
    }
}
