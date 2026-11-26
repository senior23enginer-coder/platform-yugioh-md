<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Calendar;

use StationX\Yugioh\Application\Shared\Clock;
use StationX\Yugioh\Domain\Calendar\MatchSchedulePolicy;
use StationX\Yugioh\Domain\Settings\SettingValue;

final readonly class ValidateResultDate
{
    public function __construct(private ResultDateReader $reader, private Clock $clock) {}

    public function execute(int $matchId): array
    {
        $settings = $this->reader->settings();
        if (!SettingValue::enabled($settings, 'result_date_guard')) {
            return ['ok' => true, 'state' => 'sin_guardia', 'message' => 'Guardia de fecha desactivada.'];
        }
        $match = $this->reader->match($matchId);
        if (!$match) {
            return ['ok' => false, 'state' => 'no_existe', 'message' => 'El duelo no existe en base de datos.'];
        }
        if (!empty($match['competition_id'])) {
            $gate = $this->reader->gate((int) $match['competition_id']);
            if (!$gate['can_execute']) {
                return [
                    'ok' => false,
                    'state' => 'competencia_bloqueada',
                    'message' => $gate['message'],
                    'dependencies' => $gate['dependencies'],
                ];
            }
        }
        $scheduledOn = (string) ($match['scheduled_on'] ?? '');
        if ($scheduledOn === '') {
            return ['ok' => false, 'state' => 'sin_fecha', 'message' => 'No se puede registrar resultado: el duelo no tiene fecha programada.'];
        }
        return MatchSchedulePolicy::resultDate(
            $scheduledOn,
            $this->clock->now()->format('Y-m-d'),
            $settings,
        );
    }
}
