<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/src/autoload.php';

/**
 * post-actions: compatibility services extracted from the web entry point.
 * Preserve public functions, transaction scopes and serialized write locks.
 * See context/source-architecture.md for the current migration boundaries.
 */

function ygo_handle_post(PDO $pdo): ?string
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !ygo_can_edit()) return ygo_handle_post_serialized($pdo);
    return ygo_with_event_write_lock($pdo, fn() => ygo_handle_post_serialized($pdo));
}

function ygo_handle_post_serialized(PDO $pdo): ?string
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return null;
    }
    $action = (string) ($_POST['action'] ?? '');
    if(ygo_fixed_program($pdo) && in_array($action,['generate_leagues','generate_cups','generate_world','auto_schedule_qualifier','rebuild_qualifier_calendar'],true)) throw new InvalidArgumentException('Generador historico bloqueado: conserva el calendario fijo. Usa la ficha de un evento nuevo y un motor soportado.');
    ygo_season_initialize_context($pdo);
    if (!ygo_can_edit()) {
        return 'gestion_no_disponible';
    }
    $commands = \StationX\Yugioh\Infrastructure\Composition\NativeFormCommands::early($pdo);
    if ($commands->supports($action)) {
        return $commands->execute(new \StationX\Yugioh\Application\Commands\FormCommand($action, $_POST, $_FILES ?? []));
    }

    $seasonResult = ygo_season_handle_post($pdo, $action);
    if ($seasonResult !== null) return $seasonResult;
    $seasonError = ygo_season_guard_post($pdo, $action);
    if ($seasonError !== null) return $seasonError;
    $commands = \StationX\Yugioh\Infrastructure\Composition\NativeFormCommands::regular($pdo);
    if ($commands->supports($action)) {
        return $commands->execute(new \StationX\Yugioh\Application\Commands\FormCommand($action, $_POST, $_FILES ?? []));
    }

    return null;
}
