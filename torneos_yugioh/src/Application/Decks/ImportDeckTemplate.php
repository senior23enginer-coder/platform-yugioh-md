<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decks;

use InvalidArgumentException;
use StationX\Yugioh\Application\Shared\TransactionBoundary;
use StationX\Yugioh\Domain\Decks\DeckTemplate;

final readonly class ImportDeckTemplate
{
    public function __construct(
        private DeckTemplateWriter $writer,
        private TransactionBoundary $transaction,
    ) {}

    public function execute(string $source): string
    {
        $data = DeckTemplate::parse($source);
        if ($this->writer->activeNameExists($data['name'])) {
            throw new InvalidArgumentException('Ya existe un deck activo con ese nombre.');
        }
        return $this->transaction->run(function () use ($data): string {
            $id = $this->writer->create($data);
            $this->writer->appendCards($id, $data['cards']);
            return 'deck_' . $id;
        });
    }
}
