<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\Decklists;

use Closure;
use Throwable;
use JsonException;
use PDOException;
use InvalidArgumentException;
use StationX\Yugioh\Application\Decklists\{SaveDecklist, DecklistReader};

/** HTTP authorization/body/token boundary; no session globals, SQL or transaction coordination. */
final readonly class DecklistController
{
    public function __construct(private DecklistReader $reader, private SaveDecklist $save) {}
    public function handle(
        string $method,
        int $id,
        string $token,
        Closure $canEdit,
        Closure $readBody,
    ): DecklistResponse {
        try {
            if ($method === 'POST') {
                if (!$canEdit()) {
                    throw new InvalidArgumentException('No tienes permiso para editar el deck.');
                }
                $raw = $readBody();
                if (strlen($raw) > 100000) {
                    throw new InvalidArgumentException('La lista supera el tamaño permitido.');
                }
                $input = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
                if (!is_array($input) || !hash_equals($token, (string) ($input['token'] ?? ''))) {
                    throw new InvalidArgumentException(
                        'La sesión cambió. Vuelve a abrir el editor.',
                    );
                }
                $answer = $this->save->execute($id, $input);
            } elseif ($method === 'GET') {
                $answer =
                    ['ok' => true, 'token' => $token, 'can_edit' => $canEdit()] +
                    $this->reader->snapshot($id);
            } else {
                throw new InvalidArgumentException('Operación no permitida.');
            }
            return new DecklistResponse(200, $answer);
        } catch (Throwable $error) {
            return self::failure($error);
        }
    }

    public static function failure(Throwable $error): DecklistResponse
    {
        return new DecklistResponse($error instanceof InvalidArgumentException ? 422 : 503, [
            'ok' => false,
            'message' =>
                $error instanceof JsonException
                    ? 'Los datos de la lista no son válidos.'
                    : ($error instanceof PDOException
                        ? 'No se pudo guardar la lista. Inténtalo de nuevo.'
                        : $error->getMessage()),
        ]);
    }
}
