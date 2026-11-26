<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Bootstrap;

final class ConnectionStatus
{
    public static function initial(): array
    {
        return ['connected' => false, 'message' => 'Sin conexion comprobada', 'counts' => []];
    }

    public static function connected(array $seedCounts, array $maintenanceCounts): array
    {
        return [
            'connected' => true,
            'message' => 'Conectado a la misma base de datos de la tienda',
            'counts' => array_merge($seedCounts, $maintenanceCounts),
        ];
    }

    public static function failed(): array
    {
        return [
            'connected' => false,
            'message' => 'No se pudo conectar a la base de datos en este entorno',
            'counts' => [],
        ];
    }
}
