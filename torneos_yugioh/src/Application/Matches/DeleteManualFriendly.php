<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Matches;

use StationX\Yugioh\Application\Shared\TransactionBoundary;

final readonly class DeleteManualFriendly
{
    public function __construct(private ManualFriendlyStore $store, private TransactionBoundary $transaction) {}

    public function execute(array $post): string
    {
        $id = max(0, (int) ($post['friendly_match_id'] ?? 0));
        $match = $this->store->find($id);
        if (!$match) {
            return 'amistoso_no_eliminado';
        }
        $this->transaction->run(function () use ($id, $match): void {
            $this->store->delete($id);
            $this->store->audit($id, $match);
        });
        return 'amistoso_eliminado';
    }
}
