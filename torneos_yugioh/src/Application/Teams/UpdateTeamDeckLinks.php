<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Teams;

use StationX\Yugioh\Domain\Teams\TeamManagementPolicy;

final readonly class UpdateTeamDeckLinks
{
    public function __construct(private TeamDeckLinkStore $store) {}

    public function execute(array $input): string
    {
        $teamId = (int) ($input['team_id'] ?? 0);
        if ($teamId > 0 && $this->store->active($teamId)) {
            $add = TeamManagementPolicy::ids($input['team_add_deck_ids'] ?? []);
            $remove = TeamManagementPolicy::ids($input['team_remove_deck_ids'] ?? []);
            if ($add) {
                $this->store->add($teamId, $add);
            }
            if ($remove) {
                $this->store->remove($teamId, $remove);
            }
            $this->store->audit($teamId, $add, $remove);
        }
        return 'equipo_ficha';
    }
}
