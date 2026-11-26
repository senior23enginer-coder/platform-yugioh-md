<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Bootstrap;

final class InitialPlatformData
{
    public static function build(
        array $metadata,
        array $divisions,
        array $fixtures,
        array $allDecks,
        bool $connectionFailed = false,
    ): array {
        $data = [
            'eventExtendedCatalog' => $metadata['eventExtendedCatalog'],
            'executionCapabilities' => $metadata['executionCapabilities'],
            'executionModelsByType' => $metadata['executionModelsByType'],
            'divisions' => $divisions,
            'fixtures' => $fixtures,
            'allDecks' => $allDecks,
            'teams' => [],
            'competitions' => [],
            'competitionDependencies' => [],
            'statusHistory' => [],
            'standingSnapshots' => [],
        ];
        if (!$connectionFailed) {
            $data += [
                'matchSnapshots' => [],
                'matchupAnalyses' => [],
                'matchupAnalysisHistory' => [],
                'deckAnalysisProfiles' => [],
                'deckAnalysisProfileHistory' => [],
                'medalUnlocks' => [],
                'medalProgress' => [],
                'rankingHistory' => [],
                'eventSummaries' => [],
                'checkins' => [],
                'metaHistory' => [],
                'eloHistory' => [],
                'seasons' => [],
                'players' => [],
                'playerDecks' => [],
            ];
        }

        return $data + [
            'cards' => [],
            'media' => [],
            'logoHistory' => [],
            'honors' => [],
            'audit' => [],
            'events' => [],
            'rulesets' => [],
            'rulesetVersions' => [],
            'matchTypes' => [],
            'gameRules' => [],
            'gameRuleConditions' => [],
            'eventPromptTemplates' => $metadata['eventPromptTemplates'],
            'competitionTypeOptions' => $metadata['competitionTypeOptions'],
            'settings' => $metadata['settings'],
            'uiLabels' => $metadata['uiLabels'],
            'serverToday' => $metadata['serverToday'],
        ];
    }
}
