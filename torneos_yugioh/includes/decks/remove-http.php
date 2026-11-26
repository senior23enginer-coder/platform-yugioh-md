<?php
declare(strict_types=1);

use StationX\Yugioh\Application\Commands\FormCommand;
use StationX\Yugioh\Infrastructure\Http\FormActions\RemoveDeckHandler;

/** The API uses the same event lock and removal handler as the native form. */
function ygo_deck_remove_response(int $status, array $body): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function ygo_deck_remove_http(): never
{
    try {
        sx_start();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, ['GET', 'DELETE'], true)) {
            header('Allow: GET, DELETE');
            ygo_deck_remove_response(405, ['ok' => false, 'message' => 'Método no permitido.']);
        }
        if (!ygo_can_edit() || isset($_GET['public_season']) || isset($_GET['public_live'])) {
            ygo_deck_remove_response(403, ['ok' => false, 'message' => 'Permisos de edición requeridos.']);
        }
        $id = filter_var($_GET['deck_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false || $id === null) {
            ygo_deck_remove_response(422, ['ok' => false, 'message' => 'deck_id inválido.']);
        }
        if ($method === 'DELETE') {
            $token = (string) ($_SESSION['sx_csrf'] ?? '');
            $provided = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
            if ($token === '' || !hash_equals($token, $provided)) {
                ygo_deck_remove_response(403, ['ok' => false, 'message' => 'Token CSRF inválido.']);
            }
            $expectedName = $_GET['expected_name'] ?? null;
            if (!is_string($expectedName) || trim($expectedName) === '' || strlen($expectedName) > 640) {
                ygo_deck_remove_response(422, ['ok' => false, 'message' => 'expected_name es obligatorio.']);
            }
        }
        $prefix = '';
        $entity = 1;
        $pdo = connect_store_common($prefix, $entity);
        $read = static function () use ($pdo, $id): ?array {
            $stmt = $pdo->prepare('SELECT id, name, active FROM sx_ygo_decks WHERE id = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'active' => (bool) $row['active']] : null;
        };
        if ($method === 'GET') {
            $deck = $read();
            if ($deck === null) ygo_deck_remove_response(404, ['ok' => false, 'message' => 'Deck inexistente.']);
            if (empty($_SESSION['sx_csrf'])) $_SESSION['sx_csrf'] = bin2hex(random_bytes(32));
            ygo_deck_remove_response(200, ['ok' => true, 'deck' => $deck, 'token' => $_SESSION['sx_csrf']]);
        }
        $result = ygo_with_event_write_lock($pdo, static function () use ($pdo, $id, $expectedName, $read): array {
            $deck = $read();
            if ($deck === null) return [404, ['ok' => false, 'message' => 'Deck inexistente.']];
            if ($deck['name'] !== $expectedName) return [409, ['ok' => false, 'message' => 'El nombre del deck no coincide.']];
            if (!$deck['active']) return [200, ['ok' => true, 'deck' => $deck, 'changed' => false]];
            $status = (new RemoveDeckHandler($pdo))->execute(new FormCommand('remove_deck', ['deck_id' => $id], []));
            if ($status === 'deck_bloqueado_evento') {
                return [409, ['ok' => false, 'message' => 'El deck está asociado a un evento o combate protegido.']];
            }
            $after = $read();
            if ($status !== 'deck_quitado' || $after === null || $after['active']) {
                throw new RuntimeException('No se pudo verificar la desactivación.');
            }
            return [200, ['ok' => true, 'deck' => $after, 'changed' => true]];
        });
        ygo_deck_remove_response($result[0], $result[1]);
    } catch (Throwable $error) {
        error_log('Station X deck removal: ' . $error->getMessage());
        ygo_deck_remove_response(503, ['ok' => false, 'message' => 'Operación no disponible.']);
    }
}
