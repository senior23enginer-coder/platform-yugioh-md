<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/autoload.php';
require_once __DIR__.'/event_qualification.php';
require_once __DIR__.'/event_catalog_rules.php';

function ygo_admission_required(array $c): bool
{
    return \StationX\Yugioh\Domain\Competitions\AdmissionConfigurationPolicy::required($c);
}

function ygo_admission_load(PDO $pdo,array $c,bool $lock=false): ?array
{
    return (new \StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoAdmissionConfigurationStore($pdo))->load($c, $lock);
}

function ygo_admission_store(PDO $pdo,array $c,array $a): void
{
    (new \StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoAdmissionConfigurationStore($pdo))->store($c, $a);
}

/** Resolve deck identity independently of memberships in the event being checked. */
function ygo_admission_deck_format(PDO $pdo,array $ids): string
{
    return (new \StationX\Yugioh\Application\Competitions\ResolveAdmissionDeckFormat(new \StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoAdmissionSourceReader($pdo)))->execute($ids);
}

/** Largest remainders, deterministic by configured source order. No fractional seats. */
function ygo_admission_quotas(array $rows,int $capacity): array
{
    return \StationX\Yugioh\Domain\Competitions\AdmissionQuotaPolicy::quotas($rows, $capacity);
}

/** A source must actually be finished; a status label alone is not sufficient. */
function ygo_admission_source(PDO $pdo,int $id,int $division=0,bool $lock=false): array
{
    return \StationX\Yugioh\Infrastructure\Composition\AdmissionReadModule::source($pdo)->execute($id, $division, $lock);
}

function ygo_admission_resolve(PDO $pdo,array $c,array $a,bool $lock=false): array
{
    return \StationX\Yugioh\Infrastructure\Composition\AdmissionReadModule::resolve($pdo)->execute($c, $a, $lock);
}

function ygo_admission_assert_no_cycle(PDO $pdo,int $target,array $sources): void
{
    \StationX\Yugioh\Domain\Competitions\AdmissionDependencyPolicy::assertNoCycle($target, $sources, (new \StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoAdmissionSourceReader($pdo))->edges());
}

/** Explicit action: save sources and load exactly their qualified decks, atomically. */
function ygo_admission_save(PDO $pdo,int $target,array $rows,int $capacity): array
{
    return ygo_with_event_write_lock($pdo, fn() => ygo_admission_save_serialized($pdo,$target,$rows,$capacity));
}

function ygo_admission_save_serialized(PDO $pdo,int $target,array $rows,int $capacity): array
{
    return (new \StationX\Yugioh\Application\Competitions\SaveEventAdmission(
        new \StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoAdmissionWriteStore($pdo),
        new \StationX\Yugioh\Infrastructure\Transactions\PdoTransactionBoundary($pdo)
    ))->execute($target, $rows, $capacity);
}

/** Called before any schedule mutation; also detects corrections to a frozen source. */
function ygo_admission_require(PDO $pdo,array $c,array $ids,bool $freeze=true): array
{
    return \StationX\Yugioh\Infrastructure\Composition\AdmissionConfigurationModule::configuration($pdo)->requireParticipants($c, $ids, $freeze);
}

function ygo_admission_view(PDO $pdo,array $c): array
{
    return \StationX\Yugioh\Infrastructure\Composition\AdmissionConfigurationModule::configuration($pdo)->view($c);
}

function ygo_admission_sources(PDO $pdo): array
{
    return \StationX\Yugioh\Infrastructure\Composition\AdmissionConfigurationModule::directory($pdo)->execute();
}

function ygo_admission_assert_result_editable(PDO $pdo,int $matchId): void
{
    \StationX\Yugioh\Infrastructure\Composition\AdmissionConfigurationModule::resultGuard($pdo)->execute($matchId);
}

/** Reschedule only the newly created stage, never any other event or earlier round. */
function ygo_admission_schedule_stage(PDO $pdo,array $c,string $start,string $pattern,int $perDay,?string $phase=null,?int $round=null): void
{
    \StationX\Yugioh\Infrastructure\Composition\AdmissionConfigurationModule::schedule($pdo)->execute($c, $start, $pattern, $perDay, $phase, $round);
}
