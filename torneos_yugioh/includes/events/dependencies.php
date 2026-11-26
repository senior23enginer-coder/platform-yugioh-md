<?php
declare(strict_types=1);

/**
 * event-dependencies: compatibility services extracted from the web entry point.
 * Preserve public functions, transaction scopes and serialized write locks.
 * See context/source-architecture.md for the current migration boundaries.
 */

function ygo_validate_match_result_date(PDO $pdo, int $matchId): array
{
    $settings = ygo_settings($pdo);
    if (!ygo_setting_enabled($settings, 'result_date_guard')) {
        return ['ok' => true, 'state' => 'sin_guardia', 'message' => 'Guardia de fecha desactivada.'];
    }
    $stmt = $pdo->prepare('SELECT id, competition_id, scheduled_on, starts_at, status FROM sx_ygo_matches WHERE id = ? LIMIT 1');
    $stmt->execute([$matchId]);
    $match = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$match) {
        return ['ok' => false, 'state' => 'no_existe', 'message' => 'El duelo no existe en base de datos.'];
    }
    if (!empty($match['competition_id'])) {
        $gate = ygo_competition_gate($pdo, (int) $match['competition_id']);
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
    $today = (new DateTimeImmutable('today'))->format('Y-m-d');
    if ($scheduledOn > $today && !ygo_setting_enabled($settings, 'allow_early_results')) {
        return ['ok' => false, 'state' => 'fecha_futura', 'message' => 'No se puede ejecutar un duelo antes de su fecha programada. Activa "permitir adelantar duelos" si necesitas hacerlo.'];
    }
    if ($scheduledOn < $today && !ygo_setting_enabled($settings, 'allow_overdue_results')) {
        return ['ok' => false, 'state' => 'vencido_bloqueado', 'message' => 'El duelo esta vencido y la configuracion no permite registrar resultados atrasados.'];
    }
    return [
        'ok' => true,
        'state' => $scheduledOn < $today ? 'vencido' : ($scheduledOn === $today ? 'hoy' : 'adelantado_permitido'),
        'message' => 'Fecha validada.',
    ];
}

function ygo_validate_match_schedule(PDO $pdo, int $matchId, ?string $scheduledOn, ?string $startsAt, ?string $venue = null, array $participantIds = []): array
{
    $settings = ygo_settings($pdo);
    $date = trim((string) $scheduledOn);
    if ($date === '') {
        return ['ok' => true, 'state' => 'sin_fecha', 'message' => 'Sin fecha programada.'];
    }
    $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        return ['ok' => false, 'state' => 'fecha_invalida', 'message' => 'La fecha programada no es valida.'];
    }
    $deckQuery=$pdo->prepare('SELECT deck_a_id,deck_b_id,deck_c_id,deck_d_id,deck_e_id,deck_f_id FROM sx_ygo_matches WHERE id=?');$deckQuery->execute([$matchId]);
    $deckRow=$participantIds?:$deckQuery->fetch(PDO::FETCH_ASSOC);
    if(!$deckRow && $matchId===0) { $deckRow=[];foreach(['a','b','c','d','e','f'] as $side)$deckRow[]=(int)($_POST['manual_deck_'.$side.'_id'] ?? 0); }
    $ffaCount=0;
    if(ygo_table_exists($pdo,'sx_ygo_ffa_matches')){
        $fq=$pdo->prepare('SELECT state_json,status FROM sx_ygo_ffa_matches WHERE scheduled_on=?');$fq->execute([$date]);
        foreach($fq->fetchAll(PDO::FETCH_ASSOC) as $f){if($f['status']!=='FINALIZADA')$ffaCount++;if(array_intersect(array_filter(array_map('intval',array_values($deckRow?:[]))),array_column(json_decode($f['state_json'],true)['players'],'deck_id')))return ['ok'=>false,'state'=>'deck_ocupado','message'=>'Un deck ya participa en una mesa multijugador en esta fecha.'];}
    }
    if($deckRow){$notBefore=ygo_deck_available_from($pdo,array_map('intval',array_values($deckRow)));if($notBefore!=='' && $date<$notBefore)return ['ok'=>false,'state'=>'deck_no_disponible','message'=>'Uno de los decks solo puede jugar desde '.$notBefore.'.'];}
    if($deckRow){
        $ids=array_values(array_filter(array_map('intval',array_values($deckRow))));
        if($ids){
            $other=$pdo->prepare('SELECT deck_a_id,deck_b_id,deck_c_id,deck_d_id,deck_e_id,deck_f_id FROM sx_ygo_matches WHERE scheduled_on=? AND id<>?');$other->execute([$date,$matchId]);
            foreach($other->fetchAll(PDO::FETCH_ASSOC) as $busy) if(array_intersect($ids,array_map('intval',array_values($busy))))return ['ok'=>false,'state'=>'deck_ocupado','message'=>'Un deck ya tiene otro combate en esta fecha.'];
        }
    }
    $weekday = $parsed->format('w');
    $validWeekdays = array_filter(array_map('trim', explode(',', (string) ($settings['valid_weekdays'] ?? ''))), static fn(string $value): bool => $value !== '');
    $specialDays = array_filter(array_map('trim', preg_split('/[\s,]+/', (string) ($settings['special_days'] ?? '')) ?: []), static fn(string $value): bool => $value !== '');
    $allowedByWeekday = !$validWeekdays || in_array($weekday, $validWeekdays, true);
    $allowedBySpecial = ygo_setting_enabled($settings, 'allow_special_days') && in_array($date, $specialDays, true);
    if (!$allowedByWeekday && !$allowedBySpecial) {
        return ['ok' => false, 'state' => 'dia_no_permitido', 'message' => 'El dia seleccionado no esta habilitado para jugar duelos.'];
    }
    $time = trim((string) $startsAt);
    if ($time !== '') {
        $time = substr($time, 0, 5);
        $start = substr((string) ($settings['allowed_start_time'] ?? '00:00'), 0, 5);
        $end = substr((string) ($settings['allowed_end_time'] ?? '23:59'), 0, 5);
        if ($start !== '' && $time < $start) {
            return ['ok' => false, 'state' => 'hora_temprana', 'message' => 'La hora esta antes del horario permitido.'];
        }
        if ($end !== '' && $time > $end) {
            return ['ok' => false, 'state' => 'hora_tardia', 'message' => 'La hora esta despues del horario permitido.'];
        }
    }
    $maxPerDay = max(1, (int) ($settings['max_matches_per_day'] ?? 3));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sx_ygo_matches WHERE scheduled_on = ? AND id <> ? AND status <> 'FINALIZADA'");
    $stmt->execute([$date, $matchId]);
    if ((int) $stmt->fetchColumn()+$ffaCount >= $maxPerDay && !ygo_schedule_can_displace_qualifier($pdo, $matchId, $date)) {
        return ['ok' => false, 'state' => 'capacidad_dia', 'message' => 'Ese dia ya alcanzo el maximo de combates configurado.'];
    }
    $venueValue = trim((string) $venue);
    $venues = array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) ($settings['venues'] ?? '')) ?: []), static fn(string $value): bool => $value !== '');
    if ($venueValue !== '' && $venues) {
        $normalizedVenues = array_map(static fn(string $value): string => strtolower($value), $venues);
        if (!in_array(strtolower($venueValue), $normalizedVenues, true)) {
            return ['ok' => false, 'state' => 'sede_no_permitida', 'message' => 'La sede seleccionada no esta en la lista de sedes disponibles.'];
        }
    }
    return ['ok' => true, 'state' => 'programable', 'message' => 'Fecha programable segun reglas.'];
}

function ygo_competition_dependencies(PDO $pdo): array
{
    return \StationX\Yugioh\Infrastructure\Composition\CompetitionGateModule::reader($pdo)->dependencies();
}

function ygo_competition_result_count(PDO $pdo, int $competitionId): int
{
    return \StationX\Yugioh\Infrastructure\Composition\CompetitionGateModule::reader($pdo)->resultCount($competitionId);
}

function ygo_competition_honor_count(PDO $pdo, int $competitionId): int
{
    return \StationX\Yugioh\Infrastructure\Composition\CompetitionGateModule::reader($pdo)->honorCount($competitionId);
}

function ygo_competition_division_count(PDO $pdo, int $competitionId): int
{
    return \StationX\Yugioh\Infrastructure\Composition\CompetitionGateModule::reader($pdo)->divisionCount($competitionId);
}

function ygo_competition_dependency_satisfied(PDO $pdo, array $dependency, bool $completed, bool $hasResults, int $resultCount): bool
{
    return \StationX\Yugioh\Infrastructure\Composition\CompetitionGateModule::dependency($pdo)->execute(
        $dependency,
        $completed,
        $hasResults,
    );
}

function ygo_competition_gate(PDO $pdo, int $competitionId, bool $refresh=false): array
{
    static $cache = [];
    if ($refresh) {
        unset($cache[$competitionId]);
    }
    if (isset($cache[$competitionId])) {
        return $cache[$competitionId];
    }
    return $cache[$competitionId] = \StationX\Yugioh\Infrastructure\Composition\CompetitionGateModule::gate($pdo)->execute($competitionId);
}
