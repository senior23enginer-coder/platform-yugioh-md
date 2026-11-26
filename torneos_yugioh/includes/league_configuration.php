<?php
declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/src/autoload.php';

function ygo_format_deck_ids(PDO $pdo, string $ruleset): array
{
    if ($ruleset === '') {
        return [];
    }
    if ($ruleset === 'custom') {
        return array_map(
            'intval',
            $pdo
                ->query(
                    'SELECT id FROM sx_ygo_decks WHERE active=1 AND team_id IS NOT NULL ORDER BY seed_rank,name,id',
                )
                ->fetchAll(PDO::FETCH_COLUMN),
        );
    }
    $stmt = $pdo->prepare("SELECT DISTINCT d.id,d.seed_rank,d.name
        FROM sx_ygo_decks d
        WHERE d.active=1 AND d.team_id IS NOT NULL AND (
            EXISTS (SELECT 1 FROM sx_ygo_divisions dv JOIN sx_ygo_competitions c ON c.id=dv.league_competition_id AND c.active=1 WHERE dv.id=d.division_id AND dv.active=1 AND c.event_ruleset=?)
            OR EXISTS (SELECT 1 FROM sx_ygo_competition_decks cd JOIN sx_ygo_competitions c ON c.id=cd.competition_id AND c.active=1 WHERE cd.deck_id=d.id AND cd.active=1 AND c.event_ruleset=?)
            OR EXISTS (SELECT 1 FROM sx_ygo_matches m JOIN sx_ygo_competitions c ON c.id=m.competition_id AND c.active=1 WHERE c.event_ruleset=? AND d.id IN (m.deck_a_id,m.deck_b_id,m.deck_c_id,m.deck_d_id,m.deck_e_id,m.deck_f_id))
            OR LOWER(COALESCE(d.style,'')) IN (?,?)
        ) ORDER BY d.seed_rank,d.name,d.id");
    $styles = [
        'tcg_advanced' => ['tcg avanzado', 'tcg_advanced'],
        'tcg_traditional' => ['tcg tradicional', 'tcg_traditional'],
        'goat' => ['goat', 'goat'],
        'rush_duel' => ['rush duel', 'rush_duel'],
    ][$ruleset] ?? [$ruleset, $ruleset];
    $stmt->execute([$ruleset, $ruleset, $ruleset, ...$styles]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function ygo_validate_format_roster(PDO $pdo, string $ruleset, array $ids): array
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if ($ruleset === 'custom' && $ids) {
        $formats = $pdo
            ->query("SELECT DISTINCT code FROM sx_ygo_rulesets WHERE active=1 AND code<>'custom'")
            ->fetchAll(PDO::FETCH_COLUMN);
        $commonFormat = false;
        foreach ($formats as $format) {
            if (!array_diff($ids, ygo_format_deck_ids($pdo, (string) $format))) {
                $commonFormat = true;
                break;
            }
        }
        if (!$commonFormat) {
            throw new InvalidArgumentException(
                'No se pueden mezclar decks de distintos formatos en un mismo evento.',
            );
        }
    }
    if (array_diff($ids, ygo_format_deck_ids($pdo, $ruleset))) {
        throw new InvalidArgumentException(
            'Hay decks que no pertenecen al formato del evento. Revisa los participantes antes de guardar.',
        );
    }
    return $ids;
}

function ygo_league_divisions(PDO $pdo, int $competitionId): array
{
    return (new \StationX\Yugioh\Infrastructure\Persistence\Leagues\PdoDivisionMovementReader($pdo))->divisions($competitionId);
}

function ygo_validate_division_movements(array $divisions, array $input): array
{
    return \StationX\Yugioh\Domain\Competitions\DivisionMovementPolicy::validateDivisionMovements(
        $divisions,
        $input,
    );
}

function ygo_save_division_movements(PDO $pdo, int $competitionId, array $input): void
{
    \StationX\Yugioh\Infrastructure\Composition\DivisionMovementModule::configure($pdo)->execute($competitionId, $input);
}

function ygo_apply_configured_league_movements(PDO $pdo, int $competitionId): int
{
    return \StationX\Yugioh\Infrastructure\Composition\DivisionMovementModule::apply($pdo)->execute($competitionId);
}

function ygo_league_deck_assignments(PDO $pdo): array
{
    return $pdo
        ->query(
            "SELECT DISTINCT c.id competition_id,c.season_id,c.name competition_name,d.id deck_id
        FROM sx_ygo_competitions c JOIN sx_ygo_decks d ON d.active=1 AND (
            EXISTS(SELECT 1 FROM sx_ygo_divisions dv WHERE dv.id=d.division_id AND dv.active=1 AND dv.season_id=c.season_id AND dv.league_competition_id=c.id)
            OR EXISTS(SELECT 1 FROM sx_ygo_competition_decks cd WHERE cd.competition_id=c.id AND cd.deck_id=d.id AND cd.active=1))
        WHERE c.active=1 AND c.type='league' AND LOWER(c.status) NOT IN ('completed','closed','cancelled','canceled','archived','finalized','finalizado','finalizada','anulado','anulada')
        ORDER BY d.id,c.id",
        )
        ->fetchAll(PDO::FETCH_ASSOC);
}

function ygo_available_league_deck_ids(PDO $pdo, int $competitionId, array $ids): array
{
    $stmt = $pdo->prepare('SELECT id,season_id,type FROM sx_ygo_competitions WHERE id=?');
    $stmt->execute([$competitionId]);
    $competition = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$competition || $competition['type'] !== 'league') {
        return array_values($ids);
    }
    $blocked = [];
    foreach (ygo_league_deck_assignments($pdo) as $row) {
        if (
            (int) $row['season_id'] === (int) $competition['season_id'] &&
            (int) $row['competition_id'] !== $competitionId
        ) {
            $blocked[(int) $row['deck_id']] = true;
        }
    }
    return array_values(array_filter($ids, static fn($id) => !isset($blocked[(int) $id])));
}

function ygo_validate_league_roster(PDO $pdo, int $competitionId, array $ids): void
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) {
        return;
    }
    // Serialize concurrent roster saves for the same decks before checking other leagues.
    sort($ids);
    $lock = $pdo->prepare(
        'SELECT id FROM sx_ygo_decks WHERE id IN (' .
            implode(',', array_fill(0, count($ids), '?')) .
            ') ORDER BY id FOR UPDATE',
    );
    $lock->execute($ids);
    $lock->fetchAll(PDO::FETCH_COLUMN);
    if (array_diff($ids, ygo_available_league_deck_ids($pdo, $competitionId, $ids))) {
        throw new InvalidArgumentException(
            'No se puede inscribir el deck: ya esta asignado o inscrito en otra liga de esta temporada. Puede participar en copas y otros eventos.',
        );
    }
}

function ygo_league_round_robin_plan(
    PDO $pdo,
    int $competitionId,
    array $selectedDeckIds,
    int $participantsPerSide,
    bool $doubleRound,
): array {
    $divisions = ygo_league_divisions($pdo, $competitionId);
    $owned = array_column($divisions, null, 'id');
    $ids = array_values(array_unique(array_map('intval', $selectedDeckIds)));
    if (!$ids) {
        return ['groups' => [], 'rounds' => []];
    }
    $stmt = $pdo->prepare(
        'SELECT id,division_id FROM sx_ygo_decks WHERE active=1 AND id IN (' .
            implode(',', array_fill(0, count($ids), '?')) .
            ')',
    );
    $stmt->execute($ids);
    $deckDivisions = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($ids as $id) {
        if (!isset($deckDivisions[$id]) || !isset($owned[(int) $deckDivisions[$id]])) {
            throw new InvalidArgumentException(
                'Asigna cada participante a una division de esta liga antes de generar sus combates.',
            );
        }
    }
    $groups = [];
    $rounds = [];
    foreach ($divisions as $division) {
        $localIds = array_values(
            array_filter(
                $ids,
                static fn($id) => (int) $deckDivisions[$id] === (int) $division['id'],
            ),
        );
        if (!$localIds) {
            continue;
        }
        $localGroups = ygo_roster_groups_for_match($pdo, $localIds, $participantsPerSide);
        if (count($localGroups) < 2) {
            throw new InvalidArgumentException(
                'Cada division con participantes necesita al menos dos lados completos para generar combates.',
            );
        }
        $offset = count($groups);
        $groups = array_merge($groups, $localGroups);
        foreach (ygo_round_robin_pairs($localGroups, $doubleRound) as $roundIndex => $pairs) {
            foreach ($pairs as $tableIndex => $pair) {
                $rounds[$roundIndex][] = [
                    $offset + $pair[0],
                    $offset + $pair[1],
                    (int) $division['id'],
                    $tableIndex + 1,
                ];
            }
        }
    }
    ksort($rounds);
    return ['groups' => $groups, 'rounds' => array_values($rounds)];
}
