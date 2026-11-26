<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Decks;

use PDO;
use StationX\Yugioh\Application\Decks\Packages\DeckPackageInventory;

final readonly class PdoDeckPackageInventory implements DeckPackageInventory
{
    public function __construct(private PDO $pdo) {}

    public function inventory(int $id): array
    {
        return \ygo_combo_inventory($this->pdo, $id, false, false);
    }

    public function saved(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id,revision,document_json,inventory_json,updated_at FROM sx_ygo_created_combos WHERE deck_id=? ORDER BY id',
        );
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
