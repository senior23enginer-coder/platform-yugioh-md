<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decks;

final readonly class SaveDeckMedia
{
    public function __construct(private DeckMediaWriter $writer) {}

    public function execute(array $input): string
    {
        $id = (int) ($input['media_deck_id'] ?? 0);
        $url = trim((string) ($input['media_url'] ?? ''));
        if ($id > 0 && $url !== '') {
            $this->writer->add($id, trim((string) ($input['media_type'] ?? 'image')), $url, trim((string) ($input['media_title'] ?? '')));
        }
        return 'media_deck';
    }
}
