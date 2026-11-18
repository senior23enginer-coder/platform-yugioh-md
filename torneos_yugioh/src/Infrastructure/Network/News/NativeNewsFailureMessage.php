<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Network\News;

use Throwable;
use RuntimeException;
use PDOException;
use StationX\Yugioh\Application\News\NewsFailureMessage;

/** Retains the native distinction between displayable provider errors and private storage failures. */
final class NativeNewsFailureMessage implements NewsFailureMessage
{
    public function for(Throwable $error): string
    {
        return $error instanceof RuntimeException && !($error instanceof PDOException)
            ? $error->getMessage()
            : 'No se pudo interpretar la respuesta de la fuente.';
    }
}
