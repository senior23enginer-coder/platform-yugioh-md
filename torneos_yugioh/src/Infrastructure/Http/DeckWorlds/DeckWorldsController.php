<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\DeckWorlds;

use Closure;
use InvalidArgumentException;
use Throwable;
use StationX\Yugioh\Application\DeckWorlds\AssignWorldsCommand;
use StationX\Yugioh\Application\DeckWorlds\DeckWorldsServices;
use StationX\Yugioh\Domain\DeckWorlds\WorldCatalog;

/** Incoming adapter: authorizes and decodes HTTP, then delegates rules and transactions. */
final readonly class DeckWorldsController
{
    /**
     * @param Closure():DeckWorldsServices $services Lazy composition after authorization.
     * @param Closure(string):void $log Internal errors, never exposed to the player.
     */
    public function __construct(private Closure $services, private Closure $log) {}

    public function handle(DeckWorldsRequest $request): DeckWorldsResponse
    {
        try {
            if (!in_array($request->method, ['GET', 'POST'], true)) {
                return new DeckWorldsResponse(
                    405,
                    ['ok' => false, 'message' => 'Método no permitido.'],
                    ['Allow' => 'GET, POST'],
                );
            }
            if (
                $request->method === 'POST' &&
                (!$request->canEdit ||
                    empty($request->providedToken) ||
                    !hash_equals($request->expectedToken, $request->providedToken))
            ) {
                return new DeckWorldsResponse(403, [
                    'ok' => false,
                    'message' => 'No se pudo autorizar el guardado. Recarga la plataforma.',
                ]);
            }
            if ($request->contentLength > 2000) {
                throw new InvalidArgumentException('Datos demasiado grandes.');
            }
            // Lazy composition preserves the old guarantee: rejected requests never open a database.
            $services = ($this->services)();
            if ($request->method === 'POST') {
                $raw = ($request->readBody)();
                if (strlen($raw) > 2000) {
                    throw new InvalidArgumentException('Datos demasiado grandes.');
                }
                $input = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
                if (!is_array($input)) {
                    throw new InvalidArgumentException('Datos no válidos.');
                }
                $record = $services->assign->execute(AssignWorldsCommand::fromArray($input));
                $answer = [
                    'record' => WorldAssignmentPresenter::one($record),
                    'history' => WorldAssignmentPresenter::many(
                        $services->query->history($record->deckId),
                    ),
                ];
            } else {
                $overview = $services->query->overview((int) ($request->query['deck_id'] ?? 0));
                $answer = [
                    'catalog' => WorldCatalog::LABELS,
                    'records' => WorldAssignmentPresenter::many($overview->records),
                    'eligible_deck_ids' => $overview->eligibleIds,
                    'history' => WorldAssignmentPresenter::many($overview->history),
                    'can_edit' => $request->canEdit,
                    'token' => $request->canEdit ? $request->expectedToken : null,
                ];
            }
            return new DeckWorldsResponse(200, ['ok' => true] + $answer);
        } catch (Throwable $error) {
            return DeckWorldsResponse::failure($error, $this->log);
        }
    }
}
