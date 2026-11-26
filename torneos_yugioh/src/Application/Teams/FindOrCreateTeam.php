<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Teams;

final readonly class FindOrCreateTeam
{
    public function __construct(private TeamStore $teams) {}

    public function execute(string $teamName, ?array $profile = null): int
    {
        $teamId = $this->teams->findId($teamName);
        if ($teamId > 0) {
            $this->teams->activate($teamId);
            if ($profile) {
                $this->teams->updateProfile($teamId, $profile);
            }
            return $teamId;
        }
        return $this->teams->create($teamName, $profile);
    }
}
