<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Matches;

use StationX\Yugioh\Domain\Matches\LiveDuelState;

final readonly class LoadLiveDuel
{
    public function __construct(private LiveDuelReadStore $store) {}

    public function execute(int $matchId, bool $forUpdate, callable $nowMs): array
    {
        $match = $this->store->match($matchId);
        if (!$match) {
            return [];
        }
        $row = $this->store->state($matchId, $forUpdate);
        if (!$row) {
            $state = LiveDuelState::initial($match, $nowMs);
            $this->store->insertInitial($matchId, $state);
            $row = $this->store->state($matchId, $forUpdate);
        }
        $state = json_decode((string) ($row['state_json'] ?? ''), true);
        $normalizedState = LiveDuelState::normalize(
            is_array($state) ? $state : LiveDuelState::initial($match, $nowMs),
            $nowMs,
        );
        $authoritativeState = LiveDuelState::initial($match, $nowMs);
        if (
            (int) $normalizedState['duel_number'] !== (int) $authoritativeState['duel_number'] &&
            strtoupper((string) $match['status']) !== 'FINALIZADA'
        ) {
            $normalizedState = LiveDuelState::normalize($authoritativeState, $nowMs);
        }
        $liveDuelNumber = (int) $normalizedState['duel_number'];
        $previousResult =
            $liveDuelNumber > 1 ? (string) ($match['d' . ($liveDuelNumber - 1)] ?? '') : '';
        $hasDrawReroll =
            (int) $normalizedState['dice_a'] >= 1 &&
            (int) $normalizedState['dice_b'] >= 1 &&
            in_array((string) $normalizedState['dice_winner'], ['A', 'B'], true);
        if ($previousResult === 'E' && !$hasDrawReroll) {
            $normalizedState['status'] = 'setup';
            $normalizedState['starts_player'] = '';
            $normalizedState['active_side'] = '';
            $normalizedState['timer_running'] = false;
            $normalizedState['timer_started_at'] = 0;
        }
        return [
            'match' => $match,
            'state' => $normalizedState,
            'revision' => max(1, (int) ($row['revision'] ?? 1)),
            'updated_by' => (string) ($row['updated_by'] ?? 'judge'),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }
}
