<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

use InvalidArgumentException;

final class ManualHonorPolicy
{
    public static function assertParticipants(int $champion, int $runner): void
    {
        if ($champion < 1 || $champion === $runner) {
            throw new InvalidArgumentException(
                'Selecciona un campeon identificado y distinto del subcampeon.',
            );
        }
    }

    public static function assertEvent(array $event, string $notes): void
    {
        if (
            !$event ||
            !in_array(
                CompetitionLifecyclePolicy::normalizeStatus((string) $event['status']),
                ['completed', 'closed'],
                true,
            )
        ) {
            throw new InvalidArgumentException(
                'El titulo requiere un evento finalizado confirmado.',
            );
        }
        if (($event['execution_model'] ?? '') !== 'manual') {
            throw new InvalidArgumentException(
                'El campeon de este sistema se determina desde su flujo competitivo; no se sustituye desde Palmares.',
            );
        }
        if ($notes === '') {
            throw new InvalidArgumentException(
                'La adjudicacion manual requiere justificar la evidencia del titulo.',
            );
        }
    }

    public static function assertRoster(int $champion, int $runner, array $allowed): void
    {
        if (
            !in_array($champion, $allowed, true) ||
            ($runner && !in_array($runner, $allowed, true))
        ) {
            throw new InvalidArgumentException(
                'El titulo solo puede atribuirse a participantes de esta edicion.',
            );
        }
    }
}
