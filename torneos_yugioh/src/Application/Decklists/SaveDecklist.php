<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decklists;

use StationX\Yugioh\Domain\Decks\DecklistRules;
use StationX\Yugioh\Application\Shared\{EventWriteLock, TransactionBoundary};

/** Native lock -> transaction -> locked snapshot -> validation -> replacement -> audit -> commit. */
final readonly class SaveDecklist
{
    public function __construct(
        private DecklistReader $reader,
        private DecklistWriter $writer,
        private DeckCardCatalog $catalog,
        private EventWriteLock $lock,
        private TransactionBoundary $transaction,
    ) {}
    public function execute(int $id, array $input): array
    {
        return $this->lock->run(
            fn() => $this->transaction->run(function () use ($id, $input): array {
                $before = $this->reader->snapshot($id, true);
                $rows = DecklistRules::rows($input, $before['version']);
                $old = array_column($before['cards'], null, 'id');
                $normalized = [];
                $seen = [];
                foreach ($rows as $row) {
                    $plan = DecklistRules::prepare(
                        $old,
                        $row,
                        ($input['documented_import'] ?? false) === true,
                    );
                    $catalog = $plan['cardId'] ? $this->catalog->lookup($plan['cardId']) : null;
                    $finished = DecklistRules::finish($plan, $catalog, $seen);
                    $seen[$finished['key']] = true;
                    $normalized[] = $finished['card'];
                }
                $this->writer->replace($id, $normalized);
                $after = $this->reader->snapshot($id);
                $this->writer->audit($id, $before['cards'], $after['cards']);
                return ['ok' => true] + $after;
            }),
        );
    }
}
