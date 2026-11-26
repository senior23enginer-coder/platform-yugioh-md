<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Teams;

use StationX\Yugioh\Domain\Teams\TeamManagementPolicy;

final readonly class SaveTeam
{
    public function __construct(private TeamProfileStore $store, private TeamLogoService $logos) {}

    public function execute(array $input): string
    {
        $id = (int) ($input['team_id'] ?? 0);
        $profile = TeamManagementPolicy::profile($input, $id > 0 ? $this->store->logo($id) : '');
        if ($profile['name'] !== '') {
            $id = $this->store->save($id, $profile);
            $uploaded = $this->logos->upload($id);
            if ($uploaded !== '') {
                $this->store->updateLogo($id, $uploaded);
                $this->logos->record($id, $uploaded);
            }
        }
        return 'equipo_' . $id;
    }
}
