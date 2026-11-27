<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/autoload.php';

/** Capture sporting identity when the roster is fixed, never infer it on a later read. */
function ygo_participant_identities(PDO $p, array $ids, string $format): array
{
    return (new \StationX\Yugioh\Application\Competitions\FreezeParticipantIdentities(
        new \StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoParticipantIdentityReader($p),
    ))->execute($ids, $format);
}
