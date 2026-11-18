<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\News;

use Closure;
use Throwable;
use InvalidArgumentException;
use StationX\Yugioh\Application\News\NewsServices;

/** Native endpoint semantics; session release happens before provider or database work on valid requests. */
final readonly class NewsController
{
    /** @param Closure(string):void $log Internal errors remain private. */
    public function __construct(private NewsServices $services, private Closure $log) {}
    public function handle(NewsRequest $request): NewsResponse
    {
        try {
            if ($request->method === 'GET' && $request->operation === 'image') {
                ($request->releaseSession)();
                return NewsResponse::image(
                    $this->services->image->execute((int) ($request->query['id'] ?? 0)),
                );
            }
            if ($request->method === 'POST') {
                $raw = ($request->readBody)();
                if (strlen($raw) > 10000) {
                    throw new InvalidArgumentException('Solicitud demasiado grande.');
                }
                $input = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
                if (
                    !is_array($input) ||
                    !hash_equals($request->token, (string) ($input['token'] ?? ''))
                ) {
                    throw new InvalidArgumentException('La sesión cambió. Recarga la página.');
                }
                ($request->releaseSession)();
                $answer =
                    $request->operation === 'sync'
                        ? $this->services->sync->execute(($input['retry_failed'] ?? false) === true)
                        : $this->services->reading->execute($request->operation, $input);
            } elseif ($request->method === 'GET' && $request->operation === 'list') {
                ($request->releaseSession)();
                $answer = $this->services->list->execute($request->query) + [
                    'token' => $request->token,
                ];
            } else {
                throw new InvalidArgumentException('Operación no válida.');
            }
            return new NewsResponse(200, ['ok' => true] + $answer);
        } catch (Throwable $error) {
            return $this->failure($error);
        }
    }

    public function failure(Throwable $error): NewsResponse
    {
        $validation = $error instanceof InvalidArgumentException;
        if (!$validation) {
            ($this->log)($error->getMessage());
        }
        return new NewsResponse($validation ? 422 : 503, [
            'ok' => false,
            'message' => $validation
                ? $error->getMessage()
                : 'No se pudo consultar el centro de noticias. El historial guardado se conserva.',
        ]);
    }
}
