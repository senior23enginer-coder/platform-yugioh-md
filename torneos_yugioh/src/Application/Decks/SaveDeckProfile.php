<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decks;

use StationX\Yugioh\Domain\Decks\DeckProfileInput;

final readonly class SaveDeckProfile
{
    public function __construct(
        private DeckProfileStore $store,
        private DeckProfileServices $services,
    ) {}

    public function execute(array $input): string
    {
        $id = (int) ($input['deck_id'] ?? 0);
        $name = trim((string) $input['deck_name']);
        $archetype = trim((string) $input['archetype']);
        $team = $id > 0 ? $this->store->team($id) : null;
        $existingCover = $id > 0 ? $this->store->cover($id) : '';
        $cover = trim((string) ($input['cover_url'] ?? '')) ?: $existingCover;
        $teamId = DeckProfileInput::team($input, $team);
        if (DeckProfileInput::autoTeam($id, $teamId, $name, $input)) {
            $teamId = $this->services->team($name, $archetype);
        }
        $profile = DeckProfileInput::values($input, $name, $archetype, $teamId, $cover);
        if ($name !== '') {
            $id = $this->store->save($id, $profile);
            $uploaded = $this->services->uploadCover($id);
            if ($uploaded !== '') {
                $this->store->updateCover($id, $uploaded);
                $this->services->recordCover($id, $uploaded);
            }
        }
        return 'deck_' . $id;
    }
}
