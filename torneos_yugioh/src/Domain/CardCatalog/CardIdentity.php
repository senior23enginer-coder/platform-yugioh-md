<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\CardCatalog;

use InvalidArgumentException;

/** Technical catalog identity bounds; does not impose competitive restrictions. */
final class CardIdentity
{
    public static function requireValid(
        int $id,
        string $message = 'Código de carta inválido.',
    ): void {
        if ($id < 1 || $id > 999999999) {
            throw new InvalidArgumentException($message);
        }
    }
}
