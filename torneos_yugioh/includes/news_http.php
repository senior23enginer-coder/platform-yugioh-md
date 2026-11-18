<?php
declare(strict_types=1);

require_once __DIR__ . '/news.php';

use StationX\Yugioh\Infrastructure\Composition\NewsModule;
use StationX\Yugioh\Infrastructure\Http\News\NewsController;
use StationX\Yugioh\Infrastructure\Http\News\NewsRequest;
use StationX\Yugioh\Infrastructure\Http\News\NewsResponse;

/** Stable compatibility facade for the native read endpoint. */
function ygo_news_items(PDO $pdo, array $input): array
{
    return NewsModule::create($pdo)->list->execute($input);
}

function ygo_news_image(PDO $pdo, int $id): never
{
    NewsResponse::image(NewsModule::create($pdo)->image->execute($id))->emit();
    exit();
}

/** Native session/database composition only; request policy and responses are handled separately. */
function ygo_news_http(): never
{
    header('Cache-Control: no-store');
    try {
        sx_start();
        $prefix = '';
        $entity = 1;
        $pdo = connect_store_common($prefix, $entity);
        ygo_news_schema($pdo);
        $_SESSION['news_token'] ??= bin2hex(random_bytes(24));
        $controller = new NewsController(
            NewsModule::create($pdo),
            static fn(string $message) => error_log('StationX news: ' . $message),
        );
        $request = new NewsRequest(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            (string) ($_GET['op'] ?? 'list'),
            $_GET,
            $_SESSION['news_token'],
            static fn(): string => (string) file_get_contents('php://input'),
            static function (): void {
                session_write_close();
            },
        );
        $controller->handle($request)->emit();
    } catch (Throwable $error) {
        $validation = $error instanceof InvalidArgumentException;
        if (!$validation) {
            error_log('StationX news: ' . $error->getMessage());
        }
        (new NewsResponse($validation ? 422 : 503, [
            'ok' => false,
            'message' => $validation
                ? $error->getMessage()
                : 'No se pudo consultar el centro de noticias. El historial guardado se conserva.',
        ]))->emit();
    }
    exit();
}
