<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Presentation;

final class DashboardSummaryProjection
{
    public static function build(
        array $competitions,
        array $gameRules,
        array $allDecks,
        array $fixtures,
        array $activeDashboardEvents,
        callable $normalizeStatus,
    ): array {
        $pendingConfigEventCount = count(
            array_filter(
                $competitions,
                static fn(array $competition): bool => $normalizeStatus(
                    (string) ($competition['status'] ?? ''),
                ) === 'pending_configuration',
            ),
        );
        $pendingEventCount = count(
            array_filter(
                $competitions,
                static fn(array $competition): bool => $normalizeStatus(
                    (string) ($competition['status'] ?? ''),
                ) === 'pending_event',
            ),
        );
        $completedEventCount = count(
            array_filter(
                $competitions,
                static fn(array $competition): bool => in_array(
                    $normalizeStatus((string) ($competition['status'] ?? '')),
                    ['completed', 'finalized', 'finalizado', 'finalizada'],
                    true,
                ),
            ),
        );
        $cancelledEventCount = count(
            array_filter(
                $competitions,
                static fn(array $competition): bool => in_array(
                    $normalizeStatus((string) ($competition['status'] ?? '')),
                    ['cancelled', 'canceled', 'anulado', 'anulada'],
                    true,
                ),
            ),
        );
        $activeGameRuleCount = count(
            array_filter($gameRules, static fn(array $rule): bool => !empty($rule['active'])),
        );
        $deckWithTeamCount = count(
            array_filter($allDecks, static fn(array $deck): bool => !empty($deck['team_id'])),
        );
        $deckWithoutTeamCount = max(0, count($allDecks) - $deckWithTeamCount);
        $deckWithMediaCount = count(
            array_filter(
                $allDecks,
                static fn(array $deck): bool => !empty($deck['cover_url']) ||
                    !empty($deck['key_cards']),
            ),
        );
        $eventsWithoutDateCount = count(
            array_filter(
                $competitions,
                static fn(array $competition): bool => empty($competition['starts_on']),
            ),
        );
        $scheduledFixtureMatches = array_sum(
            array_map(
                static fn(array $rounds): int => array_sum(
                    array_map(
                        static fn(array $round): int => count(
                            array_filter(
                                $round['matches'] ?? [],
                                static fn(array $match): bool => !empty($match['scheduled_on']),
                            ),
                        ),
                        $rounds,
                    ),
                ),
                $fixtures,
            ),
        );
        $nextDashboardEvent = $activeDashboardEvents[0] ?? ($competitions[0] ?? null);
        return [
            $pendingConfigEventCount,
            $pendingEventCount,
            $completedEventCount,
            $cancelledEventCount,
            $activeGameRuleCount,
            $deckWithTeamCount,
            $deckWithoutTeamCount,
            $deckWithMediaCount,
            $eventsWithoutDateCount,
            $scheduledFixtureMatches,
            $nextDashboardEvent,
        ];
    }
}
