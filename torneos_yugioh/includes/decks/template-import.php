<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/autoload.php';

/** Strict, UTF-8 deck template. No prompt prose or executable rules accepted. */
function ygo_parse_deck_template(string $text): array
{
    return \StationX\Yugioh\Domain\Decks\DeckTemplate::parse($text);
}

/** Creates a native deck and its cards together; failures leave neither behind. */
function ygo_import_deck_template(PDO $pdo, array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK ||
        (int) ($file['size'] ?? 0) < 1 || (int) ($file['size'] ?? 0) > 131072 ||
        !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        throw new InvalidArgumentException('Selecciona un archivo TXT valido de hasta 128 KiB.');
    }
    $filename = (string) ($file['name'] ?? '');
    if (!preg_match('/\.txt$/i', $filename)) throw new InvalidArgumentException('Solo se aceptan archivos .txt.');
    $source = file_get_contents((string) $file['tmp_name']);
    if ($source === false) throw new InvalidArgumentException('No se pudo leer el TXT.');
    return (new \StationX\Yugioh\Application\Decks\ImportDeckTemplate(
        new \StationX\Yugioh\Infrastructure\Persistence\Decks\PdoDeckTemplateWriter($pdo),
        new \StationX\Yugioh\Infrastructure\Transactions\PdoTransactionBoundary($pdo, false)
    ))->execute($source);
}
