<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Combos;

use PDO;
use StationX\Yugioh\Application\Combos\ComboSourceFeedReader;

final readonly class PdoComboSourceFeedReader implements ComboSourceFeedReader
{
    public function __construct(private PDO $pdo) {}

    public function metadata(): array
    {
        return $this->pdo->query("SELECT id,deck_id,revision,updated_at FROM sx_ygo_created_combos WHERE id LIKE 'documented-%' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function records(): iterable
    {
        return $this->pdo->query("SELECT * FROM sx_ygo_created_combos WHERE id LIKE 'documented-%' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    }
}
