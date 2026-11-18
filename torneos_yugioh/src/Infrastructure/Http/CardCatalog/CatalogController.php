<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\CardCatalog;

use Throwable;
use InvalidArgumentException;
use StationX\Yugioh\Application\CardCatalog\CatalogServices;
use StationX\Yugioh\Domain\CardCatalog\CardIdentity;

/** Request mapping only: exact local/provider choice and existing status/message behavior. */
final readonly class CatalogController
{
    public function __construct(private CatalogServices $services) {}
    public function handle(string $method, array $query): CatalogResponse
    {
        try {
            if ($method !== 'GET') {
                throw new InvalidArgumentException('Operación no permitida.');
            }
            $op = $query['op'] ?? '';
            if (in_array($op, ['detail', 'image'], true)) {
                $id = filter_var($query['id'] ?? '', FILTER_VALIDATE_INT);
                CardIdentity::requireValid((int) $id, 'Código inválido.');
                if ($op === 'image') {
                    return new CatalogResponse(
                        200,
                        null,
                        $this->services->image->execute(
                            $id,
                            (string) ($query['size'] ?? 'small'),
                            ($query['local'] ?? '') === '1',
                        ),
                    );
                }
                if (($query['local'] ?? '1') === '1') {
                    $card = $this->services->inspect->execute($id);
                } else {
                    $card = $this->services->lookup->execute($id);
                    $card['image'] = './?api=deck_builder&op=image&size=full&id=' . $id;
                    $card['text_source'] = 'YGOPRODeck';
                    $card['text_language'] = 'en';
                }
                return new CatalogResponse(200, ['ok' => true, 'card' => $card]);
            }
            return new CatalogResponse(
                200,
                $this->services->search->execute(
                    (string) ($query['q'] ?? ''),
                    (string) ($query['format'] ?? 'all'),
                    (int) ($query['page'] ?? 0),
                ),
            );
        } catch (Throwable $error) {
            return self::failure($error);
        }
    }
    /** Preserve the catalog endpoint's existing message/status contract, including emission failures. */
    public static function failure(Throwable $error): CatalogResponse
    {
        return new CatalogResponse($error instanceof InvalidArgumentException ? 422 : 503, [
            'ok' => false,
            'message' => $error->getMessage(),
        ]);
    }
}
