<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;
use StationX\Yugioh\Application\Commands\OfflineFormStore;

final readonly class PdoOfflineFormStore implements OfflineFormStore
{
    private const TABLES = [
        'save_deck' => 'sx_ygo_decks',
        'save_deck_analysis_profile' => 'sx_ygo_deck_analysis_profiles',
        'save_matchup_analysis' => 'sx_ygo_matchup_analyses',
        'save_team' => 'sx_ygo_teams',
        'save_competition' => 'sx_ygo_competitions',
        'configure_competition' => 'sx_ygo_competitions',
        'save_match_schedule' => 'sx_ygo_matches',
        'save_division' => 'sx_ygo_divisions',
    ];

    public function __construct(private PDO $pdo) {}

    public function result(string $requestId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT result_code FROM sx_ygo_sync_requests WHERE request_id=? LIMIT 1');
        $stmt->execute([$requestId]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : (string) $value;
    }

    public function updatedAt(string $action, int $id): string
    {
        $table = self::TABLES[$action] ?? null;
        if ($table === null || !\ygo_column_exists($this->pdo, $table, 'updated_at')) {
            return '';
        }
        $stmt = $this->pdo->prepare("SELECT updated_at FROM $table WHERE id=? LIMIT 1");
        $stmt->execute([$id]);
        return (string) ($stmt->fetchColumn() ?: '');
    }

    public function record(string $requestId, string $hash, string $result): void
    {
        $this->pdo->prepare('INSERT INTO sx_ygo_sync_requests (request_id,match_id,payload_hash,result_code) VALUES (?,?,?,?)')->execute([$requestId, 0, $hash, $result]);
    }
}
