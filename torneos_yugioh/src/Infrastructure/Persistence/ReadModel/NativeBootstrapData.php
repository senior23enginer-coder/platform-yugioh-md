<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\ReadModel;

use PDO;
use StationX\Yugioh\Application\Bootstrap\BootstrapDataPort;

final readonly class NativeBootstrapData implements BootstrapDataPort
{
    public function __construct(private PDO $pdo) {}

    public function statistics(): array
    {
        return \ygo_stats_view($this->pdo);
    }

    public function executionCapabilities(): array
    {
        return \ygo_execution_capabilities();
    }

    public function executionModels(array $types): array
    {
        return array_combine($types, array_map('ygo_allowed_execution_models_for_type', $types));
    }

    public function freeForAllMatches(): array
    {
        return \ygo_ffa_rows($this->pdo);
    }

    public function canEdit(): bool
    {
        return \ygo_can_edit();
    }

    public function teamResults(): array
    {
        $results = [];
        $query = $this->pdo->prepare(
            "SELECT setting_key,setting_value FROM sx_ygo_settings WHERE season_id=? AND setting_key LIKE 'event_team_result_%'",
        );
        $query->execute([\ygo_current_season_id($this->pdo)]);
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $results[(int) substr($row['setting_key'], 18)] = json_decode(
                $row['setting_value'],
                true,
            );
        }
        return $results;
    }

    public function teamConfiguration(array $competition): mixed
    {
        return \ygo_team_load($this->pdo, $competition);
    }

    public function workflow(array $competition): mixed
    {
        return \ygo_workflow_load($this->pdo, $competition);
    }

    public function recoveryToken(mixed $workflow): mixed
    {
        return \ygo_workflow_recovery_token($workflow);
    }

    public function admissionSources(): array
    {
        return \ygo_admission_sources($this->pdo);
    }

    public function admission(array $competition): mixed
    {
        return \ygo_admission_view($this->pdo, $competition);
    }

    public function eventCatalog(): array
    {
        return \ygo_extended_event_catalog();
    }

    public function eventDescriptors(): mixed
    {
        return json_decode(
            (string) file_get_contents(YGO_APP_ROOT . '/includes/event_business_descriptors.json'),
            true,
        );
    }

    public function eventTemplates(): array
    {
        return \ygo_event_available_templates();
    }
}
