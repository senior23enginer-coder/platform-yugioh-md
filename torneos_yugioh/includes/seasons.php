<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/autoload.php';

function ygo_season_periods(): array
{
    return \StationX\Yugioh\Domain\Seasons\SeasonDefinition::periods();
}

function ygo_season_status_label(string $status): string
{
    return ['draft' => 'Borrador', 'active' => 'Activa', 'completed' => 'Finalizada', 'archived' => 'Archivada'][$status] ?? $status;
}

function ygo_season_end(string $start, string $period): string
{
    return \StationX\Yugioh\Domain\Seasons\SeasonDefinition::end($start, $period);
}

function ygo_ensure_seasons_schema(PDO $pdo): void
{
    ygo_add_column($pdo, 'sx_ygo_seasons', 'period_type', "VARCHAR(20) NOT NULL DEFAULT 'annual' AFTER status");
    ygo_add_column($pdo, 'sx_ygo_seasons', 'starts_on', 'DATE NULL AFTER period_type');
    ygo_add_column($pdo, 'sx_ygo_seasons', 'ends_on', 'DATE NULL AFTER starts_on');
    ygo_add_column($pdo, 'sx_ygo_seasons', 'closed_at', 'DATETIME NULL AFTER ends_on');
    ygo_add_column($pdo, 'sx_ygo_seasons', 'updated_at', 'TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at');
    $pdo->exec("UPDATE sx_ygo_seasons SET starts_on=COALESCE(starts_on,'2026-01-01'),ends_on=COALESCE(ends_on,'2026-12-31') WHERE slug='temporada-2026'");
    $fk = $pdo->query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='sx_ygo_competitions' AND CONSTRAINT_NAME='fk_ygo_competition_season'");
    if ((int) $fk->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE sx_ygo_competitions ADD CONSTRAINT fk_ygo_competition_season FOREIGN KEY (season_id) REFERENCES sx_ygo_seasons(id) ON DELETE RESTRICT');
    }
}

function ygo_season_find(PDO $pdo, int $id, bool $lock = false): ?array
{
    return (new \StationX\Yugioh\Infrastructure\Persistence\Seasons\PdoSeasonScheduleReader($pdo))->find($id, $lock);
}

function ygo_season_require_open(PDO $pdo, int $id, bool $playing = false, bool $lock = false): array
{
    return \StationX\Yugioh\Infrastructure\Composition\SeasonScheduleModule::create($pdo)->requireOpen($id, $playing, $lock);
}

function ygo_season_validate_event_dates(array $season, ?string $start, ?string $end): void
{
    \StationX\Yugioh\Domain\Seasons\SeasonSchedulePolicy::validateEventDates($season, $start, $end);
}

function ygo_season_initialize_context(PDO $pdo): void
{
    $id = \StationX\Yugioh\Infrastructure\Composition\SeasonContextModule::context($pdo)->resolve($_GET, $_POST);
    if ($id !== null) {
        $GLOBALS['ygo_request_season_id'] = $id;
    }
}

function ygo_season_validate_competition_schedule(PDO $pdo, int $competitionId): void
{
    \StationX\Yugioh\Infrastructure\Composition\SeasonScheduleModule::create($pdo)->validateCompetition($competitionId);
}

function ygo_season_save(PDO $pdo, array $input): int
{
    return \StationX\Yugioh\Infrastructure\Composition\SeasonLifecycleModule::create($pdo)->save($input);
}

function ygo_season_close(PDO $pdo, int $id, string $reason = 'manual'): int
{
    return \StationX\Yugioh\Infrastructure\Composition\SeasonLifecycleModule::create($pdo)->close($id, $reason);
}

function ygo_season_close_expired(PDO $pdo): void
{
    \StationX\Yugioh\Infrastructure\Composition\SeasonLifecycleModule::create($pdo)->closeExpired();
}

function ygo_season_delete(PDO $pdo, int $id): void
{
    \StationX\Yugioh\Infrastructure\Composition\SeasonLifecycleModule::create($pdo)->delete($id);
}

function ygo_season_handle_post(PDO $pdo, string $action): ?string
{
    if (!\StationX\Yugioh\Application\Seasons\HandleSeasonCommand::supports($action)) return null;
    try {
        \StationX\Yugioh\Infrastructure\Composition\SeasonContextModule::commands($pdo)->execute(
            $action,
            $_POST,
            $GLOBALS['ygo_request_season_id'] ?? null,
        );
        return 'temporada_guardada';
    } catch (InvalidArgumentException $error) {
        $_SESSION['ygo_season_error'] = $error->getMessage();
        return 'temporada_error';
    }
}

function ygo_season_guard_post(PDO $pdo, string $action): ?string
{
    if (!\StationX\Yugioh\Application\Seasons\GuardSeasonAction::supports($action)) return null;
    try {
        \StationX\Yugioh\Infrastructure\Composition\SeasonContextModule::guard($pdo)->execute(
            $action,
            ygo_current_season_id($pdo),
            $_POST,
        );
        return null;
    } catch (InvalidArgumentException $error) {
        $_SESSION['ygo_season_error'] = $error->getMessage();
        return 'evento_temporada_bloqueada';
    }
}

function ygo_current_season_id(PDO $pdo): int
{
    return \StationX\Yugioh\Infrastructure\Composition\SeasonContextModule::context($pdo)->current($GLOBALS['ygo_request_season_id'] ?? null);
}


function ygo_season_event_rows(PDO $pdo): array
{
    return (new \StationX\Yugioh\Infrastructure\Persistence\Seasons\PdoSeasonContextReader($pdo))->eventRows();
}
