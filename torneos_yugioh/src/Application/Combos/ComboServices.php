<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Combos;

/** Composed application entry points; no PDO or HTTP context escapes through this container. */
final readonly class ComboServices
{
    public function __construct(
        public SaveCreatedCombo $save,
        public ComboQueries $queries,
        public PrepareComboDocument $documents,
        public ComboInventory $inventory,
    ) {}
}
