<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Bootstrap;

final readonly class EnrichPlatformData
{
    public function __construct(private BootstrapDataPort $port) {}

    public function execute(
        array $data,
        array $catalogGroups,
        array $competitionTypeOptions,
    ): array {
        $data['statisticsV8'] = $this->port->statistics();
        $data['executionCapabilities'] = $this->port->executionCapabilities();
        $data['executionModelsByType'] = $this->port->executionModels(
            array_keys($competitionTypeOptions),
        );
        $data['ffaMatches'] = $this->port->freeForAllMatches();
        $data['canEditEvents'] = $this->port->canEdit();
        $data['teamResults'] = $this->port->teamResults();

        foreach ($data['competitions'] as &$competition) {
            $competition['teamConfig'] = $this->port->teamConfiguration($competition);
            $competition['workflow'] = $this->port->workflow($competition);
            $competition['recoveryToken'] =
                $competition['workflow'] !== null
                    ? $this->port->recoveryToken($competition['workflow'])
                    : null;
        }
        unset($competition);
        $data['admissionSources'] = $this->port->admissionSources();
        foreach ($data['competitions'] as &$competition) {
            $competition['admission'] = $this->port->admission($competition);
        }
        unset($competition);

        $data['eventExtendedCatalog'] = $this->port->eventCatalog();
        $data['businessEventDescriptors'] = $this->port->eventDescriptors();
        $data['catalogs'] = $catalogGroups;
        $data['eventPromptTemplates'] = $this->port->eventTemplates();
        $data['competitionTypeOptions'] = $competitionTypeOptions;
        return $data;
    }
}
