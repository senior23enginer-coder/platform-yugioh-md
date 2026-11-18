<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;
use InvalidArgumentException;

/** Native configure_statistics_global adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class ConfigureStatisticsGlobalHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $mapping = [];
        foreach (['FULL', 'REDUCED', 'BONUS'] as $code) {
            if (isset($post['statistics_k_' . $code])) {
                $value = filter_var($post['statistics_k_' . $code], FILTER_VALIDATE_INT);
                if ($value === false) {
                    throw new InvalidArgumentException('Importancia estadistica invalida.');
                }
                $mapping[$code] = $value;
            }
        }
        ygo_stats_configure_global(
            $pdo,
            trim((string) ($post['statistics_anchor'] ?? '')),
            $mapping,
        );
        ygo_stats_sync($pdo);
        return 'estadisticas_actualizadas';

        return null;
    }
}
