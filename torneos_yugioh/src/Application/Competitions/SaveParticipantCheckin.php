<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

use StationX\Yugioh\Domain\Competitions\ParticipantCheckinInput;

final readonly class SaveParticipantCheckin
{
    public function __construct(private ParticipantCheckinStore $store) {}

    public function execute(array $post): string
    {
        $input = ParticipantCheckinInput::normalize($post);
        if ($input !== null) {
            $this->store->save($input);
            $this->store->audit($input);
        }
        return 'estadisticas';
    }
}
