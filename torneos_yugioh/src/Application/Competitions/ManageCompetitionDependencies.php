<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

use StationX\Yugioh\Domain\Competitions\DependencyInput;

final readonly class ManageCompetitionDependencies
{
    public function __construct(private DependencyWriteStore $store) {}

    public function save(array $post): string
    {
        $seasonId = $this->store->currentSeasonId();
        $input = DependencyInput::normalize($post);
        if ($input !== null) {
            $this->store->save($seasonId, $input);
            $this->store->audit('competition', $input['competitionId'], 'save_dependency', [
                'depends_on' => $input['dependsOnId'],
                'condition' => $input['condition'],
                'take_count' => $input['takeCount'],
            ]);
        }
        return 'dependencia_evento';
    }

    public function remove(array $post): string
    {
        $id = (int) ($post['dependency_id'] ?? 0);
        if ($id > 0) {
            $this->store->remove($id);
            $this->store->audit('competition_dependency', $id, 'delete_dependency', []);
        }
        return 'dependencia_quitada';
    }
}
