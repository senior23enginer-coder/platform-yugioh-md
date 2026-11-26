<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Calendar;

use DateTimeImmutable;
use StationX\Yugioh\Domain\Calendar\MatchSchedulePolicy;

final readonly class ValidateMatchSchedule
{
    public function __construct(private MatchScheduleValidationReader $reader) {}

    public function execute(
        int $matchId,
        ?string $scheduledOn,
        ?string $startsAt,
        ?string $venue,
        array $participantIds,
        array $manualDeckIds,
    ): array {
        $settings = $this->reader->settings();
        $date = trim((string) $scheduledOn);
        if ($date === '') {
            return ['ok' => true, 'state' => 'sin_fecha', 'message' => 'Sin fecha programada.'];
        }
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            return [
                'ok' => false,
                'state' => 'fecha_invalida',
                'message' => 'La fecha programada no es valida.',
            ];
        }
        $deckRow = $this->reader->participants($matchId, $participantIds);
        if (!$deckRow && $matchId === 0) {
            $deckRow = $manualDeckIds;
        }
        $ids = array_values(array_filter(array_map('intval', array_values($deckRow))));
        $ffaCount = 0;
        foreach ($this->reader->freeForAll($date) as $f) {
            if ($f['status'] !== 'FINALIZADA') {
                $ffaCount++;
            }
            if (
                array_intersect(
                    $ids,
                    array_column(json_decode($f['state_json'], true)['players'], 'deck_id'),
                )
            ) {
                return [
                    'ok' => false,
                    'state' => 'deck_ocupado',
                    'message' => 'Un deck ya participa en una mesa multijugador en esta fecha.',
                ];
            }
        }
        if ($deckRow) {
            $notBefore = $this->reader->availableFrom(array_map('intval', array_values($deckRow)));
            if ($notBefore !== '' && $date < $notBefore) {
                return [
                    'ok' => false,
                    'state' => 'deck_no_disponible',
                    'message' => 'Uno de los decks solo puede jugar desde ' . $notBefore . '.',
                ];
            }
        }
        if ($ids) {
            foreach ($this->reader->busyMatches($date, $matchId) as $busy) {
                if (array_intersect($ids, array_map('intval', array_values($busy)))) {
                    return [
                        'ok' => false,
                        'state' => 'deck_ocupado',
                        'message' => 'Un deck ya tiene otro combate en esta fecha.',
                    ];
                }
            }
        }
        $dayError = MatchSchedulePolicy::dayAndTime($parsed, $date, $startsAt, $settings);
        if ($dayError !== null) {
            return $dayError;
        }
        if (
            $this->reader->pendingCount($date, $matchId) + $ffaCount >=
                MatchSchedulePolicy::maxPerDay($settings) &&
            !$this->reader->canDisplaceQualifier($matchId, $date)
        ) {
            return [
                'ok' => false,
                'state' => 'capacidad_dia',
                'message' => 'Ese dia ya alcanzo el maximo de combates configurado.',
            ];
        }
        return MatchSchedulePolicy::venue($venue, $settings);
    }
}
