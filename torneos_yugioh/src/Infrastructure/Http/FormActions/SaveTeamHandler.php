<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;
use RuntimeException;

/** Native save_team adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class SaveTeamHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $id = (int) ($post['team_id'] ?? 0);
        $existingLogo = '';
        if ($id > 0) {
            $logoStmt = $pdo->prepare('SELECT logo_url FROM sx_ygo_teams WHERE id = ? LIMIT 1');
            $logoStmt->execute([$id]);
            $existingLogo = (string) ($logoStmt->fetchColumn() ?: '');
        }
        $logoUrl = $existingLogo;
        $values = [
            trim((string) $post['team_name']),
            trim((string) $post['team_city']),
            $logoUrl,
            strtoupper(substr(trim((string) ($post['team_code'] ?? '')), 0, 12)),
            strtoupper(substr(trim((string) ($post['team_symbol'] ?? '')), 0, 24)),
        ];
        if ($values[0] !== '') {
            if ($id > 0) {
                $pdo->prepare(
                    'UPDATE sx_ygo_teams SET name=?, city=?, logo_url=?, short_code=?, symbol=? WHERE id=?',
                )->execute([...$values, $id]);
            } else {
                $pdo->prepare(
                    'INSERT INTO sx_ygo_teams (name, city, logo_url, short_code, symbol, active) VALUES (?, ?, ?, ?, ?, 1)',
                )->execute($values);
                $id = (int) $pdo->lastInsertId();
            }
            $uploadedLogo = ygo_store_team_logo_upload($id);
            if ($uploadedLogo !== '') {
                $pdo->prepare('UPDATE sx_ygo_teams SET logo_url=? WHERE id=?')->execute([
                    $uploadedLogo,
                    $id,
                ]);
                ygo_record_logo_history($pdo, 'team', $id, $uploadedLogo);
            }
        }
        if ($competitionType === 'qualifier' && strtolower($competitionRuleset) === 'goat') {
            throw new RuntimeException(
                'El formato GOAT se organiza por liga y divisiones; no admite clasificatorios.',
            );
        }
        return 'equipo_' . $id;

        return null;
    }
}
