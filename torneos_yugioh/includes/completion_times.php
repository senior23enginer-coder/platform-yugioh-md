<?php
declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/src/autoload.php';

function ygo_completion_now(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('America/Bogota')))->format(
        'Y-m-d H:i:s',
    );
}

/** Server-owned close dates. Existing results without evidence stay undated. */
function ygo_completion_games(
    array $incoming,
    array $stored,
    array $before,
    array $after,
    string $now,
    bool $allowNew = true,
): array {
    return \StationX\Yugioh\Domain\Matches\CompletionPolicy::completionGames(
        $incoming,
        $stored,
        $before,
        $after,
        $now,
        $allowNew,
    );
}

/** Called inside the result transaction, before changing the match status. */
function ygo_completion_set_status(PDO $p, int $id, string $status, ?string $now = null): void
{
    $p->prepare(
        "UPDATE sx_ygo_matches SET completed_at=CASE
        WHEN ?='FINALIZADA' AND UPPER(status) NOT IN ('FINALIZADA','FINALIZADO','COMPLETED','FINISHED')
        THEN COALESCE(completed_at,?) ELSE completed_at END, status=? WHERE id=?",
    )->execute([$status, $now ?? ygo_completion_now(), $status, $id]);
}
