<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;
use Throwable;

/** Native import_deck_cards adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class ImportDeckCardsHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $deckId = (int) ($post['import_card_deck_id'] ?? 0);
        $raw = trim((string) ($post['deck_card_rows'] ?? ''));
        $replace = !empty($post['replace_deck_cards']);
        if ($deckId > 0 && $raw !== '') {
            $rows = preg_split('/\R/', $raw) ?: [];
            $valid = [];
            foreach ($rows as $line) {
                $columns = str_getcsv(trim($line), ';');
                if (count($columns) < 3) {
                    $columns = str_getcsv(trim($line), ',');
                }
                $section = strtolower(trim((string) ($columns[0] ?? 'main')));
                $quantity = max(1, min(3, (int) ($columns[1] ?? 1)));
                $name = mb_substr(trim((string) ($columns[2] ?? '')), 0, 180);
                if (
                    !in_array($section, ['main', 'extra', 'side', 'tech'], true) ||
                    $name === '' ||
                    strtolower($name) === 'nombre'
                ) {
                    continue;
                }
                $valid[] = [
                    $deckId,
                    $name,
                    mb_substr(trim((string) ($columns[3] ?? '')), 0, 120),
                    $quantity,
                    $section,
                    mb_substr(trim((string) ($columns[4] ?? '')), 0, 255),
                ];
            }
            if ($valid) {
                $pdo->beginTransaction();
                try {
                    if ($replace) {
                        $pdo->prepare('DELETE FROM sx_ygo_deck_cards WHERE deck_id=?')->execute([
                            $deckId,
                        ]);
                    }
                    $insert = $pdo->prepare(
                        'INSERT INTO sx_ygo_deck_cards (deck_id,card_name,role,quantity,section,notes) VALUES (?,?,?,?,?,?)',
                    );
                    foreach ($valid as $row) {
                        $insert->execute($row);
                    }
                    $pdo->commit();
                } catch (Throwable $error) {
                    $pdo->rollBack();
                    throw $error;
                }
            }
        }
        return 'cartas_importadas';

        return null;
    }
}
