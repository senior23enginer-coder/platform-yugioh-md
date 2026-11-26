<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Statistics;

use StationX\Yugioh\Domain\Statistics\AchievementPolicy;
use StationX\Yugioh\Domain\Statistics\Analytics;
use StationX\Yugioh\Domain\Statistics\MetricFactory;
use StationX\Yugioh\Domain\Statistics\StatisticsPolicy;
use StationX\Yugioh\Domain\Statistics\StatisticsRevisionPolicy;

final readonly class ReadStatisticsView
{
    public function __construct(
        private StatisticsViewReader $reader,
        private ReadStatisticsSource $source,
    ) {}

    public function execute(): array
    {
        if (!$this->reader->installed()) {
            return ['status' => 'PENDIENTE', 'reason' => 'Esquema estadistico no instalado'];
        }
        $payload = $this->reader->previousPayload();
        $data = $payload ? json_decode($payload, true) : ['status' => 'PENDIENTE'];
        $data['publications'] = $this->reader->publications(false);
        $data['analytics'] = Analytics::analytics($data);
        $data['revision_history'] = $this->reader->history();
        $data['achievement_catalog'] = AchievementPolicy::achievements(
            MetricFactory::metric(0, 0, 'formato'),
        );
        $source = $this->source->execute();
        $data['available_cuts'] = [];
        $data['can_publish'] = false;
        $data['policy']['global_cadence'] = 'per_result';
        $data['policy']['minimum_matches'] = 2;
        $data['policy']['minimum_opponents'] = 1;
        $data['source_summary'] = [
            'matches' => count($source['matches']),
            'confirmed' => count(array_filter($source['matches'], fn($m) => $m['confirmed'])),
            'events' => count($source['events']),
            'version' => StatisticsPolicy::VERSION,
        ];
        return StatisticsRevisionPolicy::publicValues($data);
    }
}
