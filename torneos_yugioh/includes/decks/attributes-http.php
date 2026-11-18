<?php
declare(strict_types=1);

require_once __DIR__ . '/attributes.php';

use StationX\Yugioh\Application\DeckWorlds\DeckWorldsServices;
use StationX\Yugioh\Infrastructure\Composition\DeckWorldsModule;
use StationX\Yugioh\Infrastructure\Http\DeckWorlds\DeckWorldsController;
use StationX\Yugioh\Infrastructure\Http\DeckWorlds\DeckWorldsRequest;
use StationX\Yugioh\Infrastructure\Http\DeckWorlds\DeckWorldsResponse;
use StationX\Yugioh\Infrastructure\Persistence\DeckWorlds\DeckWorldsSchema;

/** Native endpoint composition. Only this boundary reads request/session globals and opens PDO. */
function ygo_deck_attributes_http(): never
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $log = static function (string $message): void {
        error_log('Station X deck worlds: ' . $message);
    };
    try {
        sx_start();
        $request = new DeckWorldsRequest(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            $_GET,
            ygo_can_edit() && !isset($_GET['public_season']) && !isset($_GET['public_live']),
            (string) ($_SESSION['sx_csrf'] ?? ''),
            (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''),
            (int) ($_SERVER['CONTENT_LENGTH'] ?? 0),
            static fn(): string => file_get_contents('php://input', false, null, 0, 2001),
        );
        $factory = static function (): DeckWorldsServices {
            $prefix = '';
            $entity = 1;
            $pdo = connect_store_common($prefix, $entity);
            DeckWorldsSchema::ensure($pdo);
            return DeckWorldsModule::create($pdo);
        };
        $response = (new DeckWorldsController($factory, $log))->handle($request);
    } catch (Throwable $error) {
        $response = DeckWorldsResponse::failure($error, $log);
    }
    $response->emit();
    exit();
}
