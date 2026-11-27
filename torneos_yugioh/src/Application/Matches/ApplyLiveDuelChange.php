<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Matches;

use StationX\Yugioh\Domain\Matches\LiveDuelState;

/** The caller owns commit and rollback, including the unlocked read on a conflict. */
final readonly class ApplyLiveDuelChange
{
    public function __construct(
        private LoadLiveDuel $loader,
        private LiveDuelWriteStore $writer,
    ) {}

    public function execute(
        int $matchId,
        int $expectedRevision,
        string $actor,
        string $operation,
        array $payload,
        callable $nowMs,
        callable $nowMilliseconds,
        callable $nowAtom,
    ): ?array {
        $this->writer->requireOpen($matchId, true);
        $live = $this->loader->execute($matchId, true, $nowMs);
        if ($expectedRevision > 0 && $expectedRevision !== (int) $live['revision']) {
            return null;
        }
        $state = LiveDuelState::transition(
            $live['state'],
            $live['match'],
            $actor,
            $operation,
            $payload,
            $nowMs,
            $nowMilliseconds,
            $nowAtom,
        );
        if ($state === null) {
            return null;
        }
        $revision = (int) $live['revision'] + 1;
        $this->writer->update($matchId, $state, $revision, $actor);
        if ($operation === 'surrender') {
            $this->writer->recordSurrender($matchId, $state, $actor);
        }
        return [
            'ok' => true,
            'match' => $live['match'],
            'state' => $state,
            'revision' => $revision,
            'updated_by' => $actor,
        ];
    }
}
