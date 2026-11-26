<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

use StationX\Yugioh\Domain\Competitions\DivisionInput;

final readonly class SaveDivision
{
    public function __construct(private DivisionWriteStore $store) {}

    public function execute(array $post): string
    {
        $id = (int) ($post['division_id'] ?? 0);
        $seasonId = $this->store->currentSeasonId();
        $decks = DivisionInput::selectedDecks($post);
        if (count($decks) > 0 && count($decks) < 4) {
            return 'division_minimo_4_decks';
        }
        $definition = DivisionInput::definition($id, $post, $decks);
        if ($definition !== null) {
            $id = $this->store->save($id, $seasonId, $definition['code'], $definition['values']);
            if ($id > 0) {
                $logo = $this->store->uploadLogo($id);
                if ($logo !== '') {
                    $this->store->setLogo($id, $seasonId, $logo);
                }
                if ($decks) {
                    $this->store->assignDecks($id, $decks);
                } elseif ($definition['sourceQualifierId'] && $definition['rankFrom'] && $definition['rankTo']) {
                    $this->store->applyQualifier($id);
                }
            }
        }
        return 'division';
    }
}
