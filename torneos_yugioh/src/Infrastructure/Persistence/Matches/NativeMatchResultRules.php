<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Matches;

use PDO;
use StationX\Yugioh\Application\Matches\MatchResultRules;

final readonly class NativeMatchResultRules implements MatchResultRules
{
    public function __construct(private PDO $pdo) {}

    public function teamKindForMatch(int $matchId): string
    {
        return \ygo_team_kind(\ygo_competition_for_match($this->pdo, $matchId));
    }

    public function verifiedStart(int $matchId, int $duel, string $starts): bool
    {
        return \ygo_match_editor_verified_start($this->pdo, $matchId, $duel, $starts);
    }

    public function validateDate(int $matchId): array
    {
        return \ygo_validate_match_result_date($this->pdo, $matchId);
    }

    public function assertEditable(int $matchId): void
    {
        \ygo_admission_assert_result_editable($this->pdo, $matchId);
        \ygo_workflow_assert_result_editable($this->pdo, $matchId);
        \ygo_season_require_open($this->pdo, \ygo_current_season_id($this->pdo), true, true);
    }

    public function evaluate(int $matchId, array $values): array
    {
        return \ygo_apply_event_model_to_result($this->pdo, $matchId, \ygo_evaluate_duel_with_rule($this->pdo, $matchId, $values), $values);
    }
}
