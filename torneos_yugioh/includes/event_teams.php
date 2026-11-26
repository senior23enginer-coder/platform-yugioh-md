<?php
declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/src/autoload.php';
require_once __DIR__ . '/event_catalog_rules.php';
require_once __DIR__ . '/participant_identity.php';
require_once __DIR__ . '/completion_times.php';

function ygo_team_kind(array $c): string
{
    $code = ygo_template_base_code((string) ($c['prompt_template'] ?? ($c['code'] ?? '')));
    if ($code === 'tag_duel') {
        return 'tag';
    }
    if ($code === 'all_star_event' && (int) ($c['participants_per_side'] ?? 1) === 1) {
        return '';
    }
    return in_array(
        $code,
        [
            'team_ycs',
            'ots_tournament_series',
            'team_3v3',
            'team_league',
            'team_cup',
            'team_championship',
            'all_star_event',
        ],
        true,
    )
        ? 'team'
        : '';
}

function ygo_team_decode_payload(string $json): array
{
    return \StationX\Yugioh\Domain\Competitions\TeamConfigurationPolicy::decodePayload($json);
}

function ygo_team_load(PDO $p, array $c): ?array
{
    return \StationX\Yugioh\Infrastructure\Composition\TeamEventModule::configuration($p)->load($c);
}

function ygo_team_store(PDO $p, array $c, array $s): void
{
    \StationX\Yugioh\Infrastructure\Composition\TeamEventModule::configuration($p)->store($c, $s);
}

function ygo_team_lineups(array $rows, string $kind): array
{
    return \StationX\Yugioh\Domain\Competitions\TeamLineupPolicy::lineups($rows, $kind);
}

function ygo_team_all_ids(array $s): array
{
    return \StationX\Yugioh\Domain\Competitions\TeamLineupPolicy::allIds($s);
}

function ygo_team_context(PDO $p, array $c): array
{
    return \StationX\Yugioh\Infrastructure\Composition\TeamEventModule::configuration($p)->context(
        $c,
    );
}

function ygo_team_save(PDO $p, int $id, array $rows): array
{
    return ygo_with_event_write_lock(
        $p,
        static fn(): array => \StationX\Yugioh\Infrastructure\Composition\TeamEventModule::configuration(
            $p,
        )->save($id, $rows),
    );
}

function ygo_team_require(PDO $p, array $c, array $ids): array
{
    return \StationX\Yugioh\Infrastructure\Composition\TeamEventModule::configuration($p)->require(
        $c,
        $ids,
    );
}

function ygo_team_lineup(array $s, int $captain): array
{
    return \StationX\Yugioh\Domain\Competitions\TeamLineupPolicy::lineup($s, $captain);
}

function ygo_team_bind_match(PDO $p, array $c, int $mid, int $a, int $b, array $s): void
{
    \StationX\Yugioh\Infrastructure\Composition\TeamEventModule::configuration($p)->bind(
        $c,
        $mid,
        $a,
        $b,
        $s,
    );
}

/** Three complete individual matches, or one genuinely shared Tag series. */
function ygo_team_aggregate(string $kind, array $evaluations, bool $drawAllowed): array
{
    return \StationX\Yugioh\Domain\Competitions\TeamLineupPolicy::aggregate(
        $kind,
        $evaluations,
        $drawAllowed,
    );
}

function ygo_team_result_load(PDO $p, int $season, int $mid): ?array
{
    return (new \StationX\Yugioh\Infrastructure\Persistence\Teams\PdoTeamResultStore($p))->load(
        $season,
        $mid,
    );
}

/** Collective rows never count as a captain's individual result.
 * Actual 3v3 seat results contribute once per member; Tag has no individual winner.
 */
function ygo_team_expand_individual_rows(PDO $p, array $rows): array
{
    return \StationX\Yugioh\Infrastructure\Composition\TeamEventModule::individual($p)->execute(
        $rows,
    );
}

function ygo_team_save_result(PDO $p, int $mid, array $sequences, int $revision): array
{
    return ygo_with_event_write_lock(
        $p,
        static fn(): array => \StationX\Yugioh\Infrastructure\Composition\TeamEventModule::result(
            $p,
        )->execute($mid, $sequences, $revision),
    );
}
