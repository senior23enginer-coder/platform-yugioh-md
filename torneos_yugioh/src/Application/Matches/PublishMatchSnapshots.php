<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Matches;

final readonly class PublishMatchSnapshots
{
    public function __construct(private MatchSnapshotStore $store) {}

    public function execute(int $matchId, array $decodedDetails): void
    {
        $matchSnapshot = $this->store->match($matchId);
        foreach ($decodedDetails as $detailRow) {
            $duelNumber = max(1, min(5, (int) ($detailRow['duel'] ?? 0)));
            foreach (['A', 'B'] as $side) {
                $suffix = strtolower($side);
                $defaultDeckKey = $side === 'A' ? 'deck_a_id' : 'deck_b_id';
                $deckId = max(
                    0,
                    (int) ($detailRow['deck_' . $suffix . '_id'] ??
                        ($matchSnapshot[$defaultDeckKey] ?? 0)),
                );
                if ($deckId <= 0) {
                    continue;
                }
                $deckSnapshot = $this->store->deck($deckId);
                $decklist = $this->store->decklist($deckId);
                $originValue = strtolower(
                    trim((string) ($detailRow['data_origin'] ?? ($detailRow['source'] ?? ''))),
                );
                $hasTurnHistory =
                    !empty($detailRow['turn_history']) && is_array($detailRow['turn_history']);
                $origin =
                    str_contains($originValue, 'infer') || str_contains($originValue, 'backfill')
                        ? 'inferred'
                        : (str_contains($originValue, 'add')
                            ? 'added'
                            : ($originValue === 'captured' || $hasTurnHistory
                                ? 'captured'
                                : 'unknown'));
                $decklistJson = json_encode($decklist, JSON_UNESCAPED_UNICODE);
                $this->store->insert([
                    'matchId' => $matchId,
                    'duel' => $duelNumber,
                    'side' => $side,
                    'competitionId' => (int) ($matchSnapshot['competition_id'] ?? 0) ?: null,
                    'deckId' => $deckId,
                    'teamId' => (int) ($deckSnapshot['team_id'] ?? 0) ?: null,
                    'playerId' =>
                        max(0, (int) ($detailRow['player_' . $suffix . '_id'] ?? 0)) ?: null,
                    'deckName' => (string) ($deckSnapshot['name'] ?? ''),
                    'archetype' => (string) ($deckSnapshot['archetype'] ?? ''),
                    'variation' => (string) ($deckSnapshot['variations'] ?? ''),
                    'teamName' => (string) ($deckSnapshot['team_name'] ?? ''),
                    'rulesetCode' => (string) ($matchSnapshot['event_ruleset'] ?? ''),
                    'rulesetVersion' => (string) ($matchSnapshot['ruleset_version'] ?? ''),
                    'divisionName' => (string) ($matchSnapshot['division_name'] ?? ''),
                    'decklistJson' => $decklistJson,
                    'decklistHash' => hash('sha256', $decklistJson ?: '[]'),
                    'dataOrigin' => $origin,
                    'snapshotStatus' => $origin === 'captured' ? 'exact' : $origin,
                ]);
            }
        }
    }
}
