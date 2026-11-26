<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decks;

use StationX\Yugioh\Application\Shared\TransactionBoundary;
use StationX\Yugioh\Domain\Decks\DeckCardInput;

final readonly class ImportDeckCards
{
    public function __construct(
        private DeckCardWriter $writer,
        private TransactionBoundary $transaction,
    ) {}

    public function execute(array $input): string
    {
        $id = (int) ($input['import_card_deck_id'] ?? 0);
        $raw = trim((string) ($input['deck_card_rows'] ?? ''));
        $replace = !empty($input['replace_deck_cards']);
        if ($id > 0 && $raw !== '') {
            $rows = DeckCardInput::parse($id, $raw);
            if ($rows) {
                $this->transaction->run(function () use ($id, $replace, $rows): void {
                    if ($replace) {
                        $this->writer->clear($id);
                    }
                    $this->writer->appendBatch($rows);
                });
            }
        }
        return 'cartas_importadas';
    }
}
