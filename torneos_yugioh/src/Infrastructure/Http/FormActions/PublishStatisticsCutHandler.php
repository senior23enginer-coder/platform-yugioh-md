<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native publish_statistics_cut adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class PublishStatisticsCutHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        ygo_stats_publish(
            $pdo,
            (string) ($post['statistics_kind'] ?? ''),
            (string) ($post['statistics_scope'] ?? ''),
            (string) ($post['statistics_cut'] ?? ''),
            trim((string) ($post['statistics_reason'] ?? '')),
        );
        ygo_stats_sync($pdo);
        return 'estadisticas_actualizadas';

        return null;
    }
}
