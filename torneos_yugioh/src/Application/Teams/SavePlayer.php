<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Teams;

use StationX\Yugioh\Application\Shared\TransactionBoundary;
use StationX\Yugioh\Domain\Teams\PlayerProfile;

final readonly class SavePlayer
{
    public function __construct(private PlayerStore $store, private TransactionBoundary $transaction) {}

    public function execute(array $input): string
    {
        $profile = PlayerProfile::fromInput($input);
        return $this->transaction->run(function () use ($profile): string {
            $id = $this->store->save($profile);
            $this->store->replaceDecks($id, $profile['deckIds']);
            $this->store->audit($id, $profile['teamId'], $profile['deckIds']);
            return 'jugador_' . $id;
        });
    }
}
