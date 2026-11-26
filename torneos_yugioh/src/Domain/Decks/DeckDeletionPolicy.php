<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Decks;

final class DeckDeletionPolicy
{
    public static function blockedStatuses(): array
    {
        return [
            'pending_configuration',
            'pendiente_configuracion',
            'pending_event',
            'pendiente_evento',
            'active',
            'activo',
            'completed',
            'finalizado',
            'finalizada',
            'cancelled',
            'canceled',
            'anulado',
            'anulada',
        ];
    }
}
