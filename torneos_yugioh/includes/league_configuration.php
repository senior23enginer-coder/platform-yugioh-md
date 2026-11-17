<?php
declare(strict_types=1);

function ygo_format_deck_ids(PDO $pdo, string $ruleset): array
{
    if ($ruleset === '') return [];
    if ($ruleset === 'custom') {
        return array_map('intval', $pdo->query('SELECT id FROM sx_ygo_decks WHERE active=1 AND team_id IS NOT NULL ORDER BY seed_rank,name,id')->fetchAll(PDO::FETCH_COLUMN));
    }
    $stmt = $pdo->prepare("SELECT DISTINCT d.id,d.seed_rank,d.name
        FROM sx_ygo_decks d
        WHERE d.active=1 AND d.team_id IS NOT NULL AND (
            EXISTS (SELECT 1 FROM sx_ygo_divisions dv JOIN sx_ygo_competitions c ON c.id=dv.league_competition_id AND c.active=1 WHERE dv.id=d.division_id AND dv.active=1 AND c.event_ruleset=?)
            OR EXISTS (SELECT 1 FROM sx_ygo_competition_decks cd JOIN sx_ygo_competitions c ON c.id=cd.competition_id AND c.active=1 WHERE cd.deck_id=d.id AND cd.active=1 AND c.event_ruleset=?)
            OR EXISTS (SELECT 1 FROM sx_ygo_matches m JOIN sx_ygo_competitions c ON c.id=m.competition_id AND c.active=1 WHERE c.event_ruleset=? AND d.id IN (m.deck_a_id,m.deck_b_id,m.deck_c_id,m.deck_d_id,m.deck_e_id,m.deck_f_id))
            OR LOWER(COALESCE(d.style,'')) IN (?,?)
        ) ORDER BY d.seed_rank,d.name,d.id");
    $styles = ['tcg_advanced'=>['tcg avanzado','tcg_advanced'],'tcg_traditional'=>['tcg tradicional','tcg_traditional'],'goat'=>['goat','goat'],'rush_duel'=>['rush duel','rush_duel']][$ruleset] ?? [$ruleset,$ruleset];
    $stmt->execute([$ruleset,$ruleset,$ruleset,...$styles]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function ygo_validate_format_roster(PDO $pdo, string $ruleset, array $ids): array
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if ($ruleset === 'custom' && $ids) {
        $formats = $pdo->query("SELECT DISTINCT code FROM sx_ygo_rulesets WHERE active=1 AND code<>'custom'")->fetchAll(PDO::FETCH_COLUMN);
        $commonFormat = false;
        foreach ($formats as $format) {
            if (!array_diff($ids, ygo_format_deck_ids($pdo, (string)$format))) { $commonFormat = true; break; }
        }
        if (!$commonFormat) throw new InvalidArgumentException('No se pueden mezclar decks de distintos formatos en un mismo evento.');
    }
    if (array_diff($ids, ygo_format_deck_ids($pdo, $ruleset))) {
        throw new InvalidArgumentException('Hay decks que no pertenecen al formato del evento. Revisa los participantes antes de guardar.');
    }
    return $ids;
}

function ygo_league_divisions(PDO $pdo, int $competitionId): array
{
    $stmt = $pdo->prepare("SELECT d.*, (SELECT COUNT(*) FROM sx_ygo_decks dk WHERE dk.division_id=d.id AND dk.active=1) actual_decks
        FROM sx_ygo_divisions d JOIN sx_ygo_competitions c ON c.id=d.league_competition_id AND c.season_id=d.season_id
        WHERE c.id=? AND c.active=1 AND c.type='league' AND d.active=1 ORDER BY d.tier_order,d.id");
    $stmt->execute([$competitionId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function ygo_validate_division_movements(array $divisions, array $input): array
{
    if (count($divisions) < 2) {
        if ($input) throw new InvalidArgumentException('Ascenso y descenso solo se configura en ligas con más de una división.');
        return [];
    }
    usort($divisions, static fn($a,$b)=>[(int)($a['tier_order'] ?? 0),(int)$a['id']] <=> [(int)($b['tier_order'] ?? 0),(int)$b['id']]);
    $byId = [];
    foreach ($divisions as $index=>$division) $byId[(int)$division['id']] = $index;
    foreach ($input as $id=>$values) {
        if (!isset($byId[(int)$id]) || !is_array($values)) throw new InvalidArgumentException('La division de origen debe pertenecer a esta liga.');
    }
    $validated = [];
    foreach ($divisions as $index=>$division) {
        $id=(int)$division['id'];$values=$input[$id] ?? [];$row=['id'=>$id];
        foreach (['promotion'=>-1,'relegation'=>1] as $kind=>$direction) {
            $raw=$values[$kind.'_slots'] ?? '0';
            if (filter_var($raw,FILTER_VALIDATE_INT)===false || (int)$raw<0) throw new InvalidArgumentException('Los cupos deben ser numeros enteros mayores o iguales a cero.');
            $slots=(int)$raw;
            $neighbor=$divisions[$index+$direction] ?? null;
            $target=(int)($values[$kind.'_to_division_id'] ?? 0);
            if ($slots && !$neighbor) throw new InvalidArgumentException($kind==='promotion' ? 'La primera division no puede ascender: no existe una division superior.' : 'La ultima division no puede descender: no existe una division inferior.');
            if ($slots && $target!==(int)$neighbor['id']) throw new InvalidArgumentException('Los movimientos solo se permiten entre divisiones consecutivas de la misma liga, respetando ascenso y descenso.');
            $row[$kind.'_slots']=$slots;
            $row[$kind.'_to_division_id']=$slots ? $target : null;
        }
        if ($row['promotion_slots']+$row['relegation_slots']>(int)$division['actual_decks']) throw new InvalidArgumentException('Los cupos de ascenso y descenso no pueden superar los decks de la division.');
        $validated[]=$row;
    }
    for ($index=0;$index<count($validated)-1;$index++) {
        if ($validated[$index]['relegation_slots']!==$validated[$index+1]['promotion_slots']) throw new InvalidArgumentException('El numero de descensos de una division debe ser igual al numero de ascensos de la division inferior.');
    }
    return $validated;
}

function ygo_save_division_movements(PDO $pdo, int $competitionId, array $input): void
{
    $rows = ygo_validate_division_movements(ygo_league_divisions($pdo,$competitionId), $input);
    $update = $pdo->prepare('UPDATE sx_ygo_divisions SET promotion_slots=?,promotion_to_division_id=?,relegation_slots=?,relegation_to_division_id=? WHERE id=? AND league_competition_id=?');
    foreach ($rows as $row) {
        $update->execute([$row['promotion_slots'],$row['promotion_to_division_id'],$row['relegation_slots'],$row['relegation_to_division_id'],$row['id'],$competitionId]);
    }
}

function ygo_apply_configured_league_movements(PDO $pdo, int $competitionId): int
{
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id=? AND active=1 FOR UPDATE');
        $stmt->execute([$competitionId]);
        $competition = $stmt->fetch(PDO::FETCH_ASSOC);
        $divisions = ygo_league_divisions($pdo,$competitionId);
        if (!$competition || $competition['type']!=='league' || count($divisions)<2 || !in_array($competition['status'],['completed','finalizado','finalizada'],true) || (int)$competition['lock_division_moves']===1) {
            if ($ownsTransaction) $pdo->commit();
            return 0;
        }
        $done = $pdo->prepare("SELECT COUNT(*) FROM sx_ygo_audit_log WHERE entity_type='competition' AND entity_id=? AND action='division_movements_applied'");
        $done->execute([$competitionId]);
        if ((int)$done->fetchColumn() > 0) { if ($ownsTransaction) $pdo->commit(); return 0; }
        $pending = $pdo->prepare("SELECT COUNT(*) FROM sx_ygo_matches WHERE competition_id=? AND status<>'FINALIZADA'");
        $pending->execute([$competitionId]);
        if ((int)$pending->fetchColumn() > 0) throw new InvalidArgumentException('Completa los encuentros antes de aplicar ascensos y descensos.');
        $input=[];
        foreach ($divisions as $division) $input[$division['id']]=$division;
        $plans=ygo_validate_division_movements($divisions,$input);
        $ranking=ygo_competition_standings($pdo,$competitionId);
        $original=[];
        $stmt=$pdo->query('SELECT id,division_id FROM sx_ygo_decks WHERE active=1');
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $deck) $original[(int)$deck['id']]=(int)$deck['division_id'];
        $moves=[];
        foreach($plans as $plan) {
            $divisionRanking=array_values(array_filter($ranking,static fn($row)=>(int)($original[(int)$row['id']] ?? 0)===$plan['id'] && (int)($row['pj'] ?? $row['played'] ?? 0)>0));
            if ($plan['promotion_slots']+$plan['relegation_slots']>count($divisionRanking)) throw new InvalidArgumentException('La división no tiene suficientes participantes con resultados para los cupos configurados.');
            foreach(['promotion','relegation'] as $kind) {
                $slots=$plan[$kind.'_slots'];
                if (!$slots) continue;
                $selected=$kind==='promotion'?array_slice($divisionRanking,0,$slots):array_slice($divisionRanking,-$slots);
                foreach($selected as $row) $moves[]=['deck_id'=>(int)$row['id'],'from_division_id'=>$plan['id'],'to_division_id'=>$plan[$kind.'_to_division_id'],'kind'=>$kind];
            }
        }
        if (!$moves) { if ($ownsTransaction) $pdo->commit(); return 0; }
        $update=$pdo->prepare('UPDATE sx_ygo_decks SET division_id=? WHERE id=? AND division_id=?');
        $audit=$pdo->prepare('INSERT INTO sx_ygo_audit_log(entity_type,entity_id,action,payload) VALUES(?,?,?,?)');
        foreach($moves as $move) {
            $update->execute([$move['to_division_id'],$move['deck_id'],$move['from_division_id']]);
            $audit->execute(['deck',$move['deck_id'],'division_'.$move['kind'],json_encode(['competition_id'=>$competitionId]+$move)]);
        }
        $pdo->prepare('UPDATE sx_ygo_divisions dv SET deck_count=(SELECT COUNT(*) FROM sx_ygo_decks d WHERE d.division_id=dv.id AND d.active=1) WHERE league_competition_id=?')->execute([$competitionId]);
        $audit->execute(['competition',$competitionId,'division_movements_applied',json_encode(['moves'=>$moves])]);
        if ($ownsTransaction) $pdo->commit();
        return count($moves);
    } catch(Throwable $error) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function ygo_league_deck_assignments(PDO $pdo): array
{
    return $pdo->query("SELECT DISTINCT c.id competition_id,c.season_id,c.name competition_name,d.id deck_id
        FROM sx_ygo_competitions c JOIN sx_ygo_decks d ON d.active=1 AND (
            EXISTS(SELECT 1 FROM sx_ygo_divisions dv WHERE dv.id=d.division_id AND dv.active=1 AND dv.season_id=c.season_id AND dv.league_competition_id=c.id)
            OR EXISTS(SELECT 1 FROM sx_ygo_competition_decks cd WHERE cd.competition_id=c.id AND cd.deck_id=d.id AND cd.active=1))
        WHERE c.active=1 AND c.type='league' AND LOWER(c.status) NOT IN ('completed','closed','cancelled','canceled','archived','finalized','finalizado','finalizada','anulado','anulada')
        ORDER BY d.id,c.id")->fetchAll(PDO::FETCH_ASSOC);
}

function ygo_available_league_deck_ids(PDO $pdo, int $competitionId, array $ids): array
{
    $stmt=$pdo->prepare('SELECT id,season_id,type FROM sx_ygo_competitions WHERE id=?');
    $stmt->execute([$competitionId]);$competition=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!$competition || $competition['type']!=='league') return array_values($ids);
    $blocked=[];
    foreach(ygo_league_deck_assignments($pdo) as $row) {
        if ((int)$row['season_id']===(int)$competition['season_id'] && (int)$row['competition_id']!==$competitionId) $blocked[(int)$row['deck_id']]=true;
    }
    return array_values(array_filter($ids,static fn($id)=>!isset($blocked[(int)$id])));
}

function ygo_validate_league_roster(PDO $pdo, int $competitionId, array $ids): void
{
    $ids=array_values(array_unique(array_map('intval',$ids)));
    if (!$ids) return;
    // Serialize concurrent roster saves for the same decks before checking other leagues.
    sort($ids);
    $lock=$pdo->prepare('SELECT id FROM sx_ygo_decks WHERE id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY id FOR UPDATE');
    $lock->execute($ids);$lock->fetchAll(PDO::FETCH_COLUMN);
    if (array_diff($ids,ygo_available_league_deck_ids($pdo,$competitionId,$ids))) {
        throw new InvalidArgumentException('No se puede inscribir el deck: ya esta asignado o inscrito en otra liga de esta temporada. Puede participar en copas y otros eventos.');
    }
}

function ygo_league_round_robin_plan(PDO $pdo, int $competitionId, array $selectedDeckIds, int $participantsPerSide, bool $doubleRound): array
{
    $divisions=ygo_league_divisions($pdo,$competitionId);
    $owned=array_column($divisions,null,'id');
    $ids=array_values(array_unique(array_map('intval',$selectedDeckIds)));
    if(!$ids) return ['groups'=>[],'rounds'=>[]];
    $stmt=$pdo->prepare('SELECT id,division_id FROM sx_ygo_decks WHERE active=1 AND id IN ('.implode(',',array_fill(0,count($ids),'?')).')');
    $stmt->execute($ids);$deckDivisions=$stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach($ids as $id) if(!isset($deckDivisions[$id]) || !isset($owned[(int)$deckDivisions[$id]])) throw new InvalidArgumentException('Asigna cada participante a una division de esta liga antes de generar sus combates.');
    $groups=[];$rounds=[];
    foreach($divisions as $division) {
        $localIds=array_values(array_filter($ids,static fn($id)=>(int)$deckDivisions[$id]===(int)$division['id']));
        if(!$localIds) continue;
        $localGroups=ygo_roster_groups_for_match($pdo,$localIds,$participantsPerSide);
        if(count($localGroups)<2) throw new InvalidArgumentException('Cada division con participantes necesita al menos dos lados completos para generar combates.');
        $offset=count($groups);$groups=array_merge($groups,$localGroups);
        foreach(ygo_round_robin_pairs($localGroups,$doubleRound) as $roundIndex=>$pairs) {
            foreach($pairs as $tableIndex=>$pair) $rounds[$roundIndex][]=[$offset+$pair[0],$offset+$pair[1],(int)$division['id'],$tableIndex+1];
        }
    }
    ksort($rounds);
    return ['groups'=>$groups,'rounds'=>array_values($rounds)];
}
