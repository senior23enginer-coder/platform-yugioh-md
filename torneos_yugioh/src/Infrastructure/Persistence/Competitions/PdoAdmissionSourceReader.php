<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\AdmissionSourceReader;

final readonly class PdoAdmissionSourceReader implements AdmissionSourceReader
{
    public function __construct(private PDO $pdo) {}

    public function competition(int $id, bool $lock): array
    {
        $q = $this->pdo->prepare(
            'SELECT * FROM sx_ygo_competitions WHERE id=? AND active=1' .
                ($lock ? ' FOR UPDATE' : ''),
        );
        $q->execute([$id]);
        return $q->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function matches(int $id): array
    {
        $q = $this->pdo->prepare(
            'SELECT m.id,m.division_id,m.deck_a_id,m.deck_b_id,m.phase,m.round_number,m.status,r.result,r.score FROM sx_ygo_matches m LEFT JOIN sx_ygo_match_results r ON r.match_id=m.id WHERE m.competition_id=? ORDER BY m.id',
        );
        $q->execute([$id]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function normalizedStatus(string $status): string
    {
        return \ygo_normalize_status_key($status);
    }

    public function workflow(array $competition): ?array
    {
        return \ygo_workflow_load($this->pdo, $competition);
    }

    public function divisions(int $id): array
    {
        return \ygo_league_divisions($this->pdo, $id);
    }

    public function workflowResult(int $matchId): array
    {
        return \ygo_workflow_db_result($this->pdo, $matchId);
    }

    public function standings(int $id): array
    {
        return \ygo_competition_standings($this->pdo, $id);
    }

    public function divisionBelongs(int $division, int $id, int $seasonId): bool
    {
        $q = $this->pdo->prepare(
            'SELECT id FROM sx_ygo_divisions WHERE id=? AND league_competition_id=? AND season_id=?',
        );
        $q->execute([$division, $id, $seasonId]);
        return (bool) $q->fetchColumn();
    }

    public function honors(int $id): array
    {
        $q = $this->pdo->prepare(
            'SELECT champion_deck_id,runner_up_deck_id FROM sx_ygo_honors WHERE competition_id=? ORDER BY id',
        );
        $q->execute([$id]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function finals(int $id): array
    {
        $last = $this->pdo->prepare(
            'SELECT m.deck_a_id,m.deck_b_id,r.result FROM sx_ygo_matches m JOIN sx_ygo_match_results r ON r.match_id=m.id WHERE m.competition_id=? AND m.round_number=(SELECT MAX(round_number) FROM sx_ygo_matches WHERE competition_id=?)',
        );
        $last->execute([$id, $id]);
        return $last->fetchAll(PDO::FETCH_ASSOC);
    }

    public function roster(int $id): array
    {
        $q = $this->pdo->prepare(
            'SELECT deck_id,sort_order FROM sx_ygo_competition_decks WHERE competition_id=? AND active=1 ORDER BY sort_order,id',
        );
        $q->execute([$id]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function rulesets(): array
    {
        return $this->pdo
            ->query("SELECT code,label FROM sx_ygo_rulesets WHERE active=1 AND code<>'custom'")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deckFormats(array $ids): array
    {
        $q = $this->pdo->prepare(
            'SELECT d.id,d.style,c.event_ruleset FROM sx_ygo_decks d LEFT JOIN sx_ygo_divisions v ON v.id=d.division_id LEFT JOIN sx_ygo_competitions c ON c.id=v.league_competition_id WHERE d.id IN (' .
                implode(',', array_fill(0, count($ids), '?')) .
                ')',
        );
        $q->execute($ids);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function edges(): array
    {
        return $this->pdo
            ->query(
                'SELECT competition_id,depends_on_competition_id FROM sx_ygo_competition_dependencies WHERE active=1',
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function baseTemplate(string $code): string
    {
        return \ygo_template_base_code($code);
    }

    public function validateFormatRoster(string $format, array $ids): void
    {
        \ygo_validate_format_roster($this->pdo, $format, $ids);
    }
}
