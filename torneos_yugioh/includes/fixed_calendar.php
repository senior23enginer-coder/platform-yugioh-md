<?php
declare(strict_types=1);

function ygo_fixed_program(PDO $pdo): array {
    return json_decode(ygo_settings($pdo)['fixed_program_2026'] ?? '[]',true) ?: [];
}

function ygo_schedule_fixed_cup_round(PDO $pdo,int $competitionId,string $phase,int $round): void {
    $program=ygo_fixed_program($pdo);
    if((int)($program['cup_id'] ?? 0)!==$competitionId) return;
    $stmt=$pdo->prepare('SELECT * FROM sx_ygo_matches WHERE competition_id=? AND phase=? AND round_number=? ORDER BY table_number,id');$stmt->execute([$competitionId,$phase,$round]);$matches=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $prev=$pdo->prepare('SELECT MAX(scheduled_on) FROM sx_ygo_matches WHERE competition_id=? AND round_number<?');$prev->execute([$competitionId,$round]);$previous=(string)$prev->fetchColumn();
    $start=max(date('Y-m-d'),$program['cup_start'],$previous!==''?(new DateTimeImmutable($previous))->modify('+1 day')->format('Y-m-d'):$program['cup_start']);
    $used=$pdo->prepare('SELECT deck_a_id,deck_b_id,starts_at FROM sx_ygo_matches WHERE scheduled_on=? AND competition_id IS NOT NULL AND NOT(competition_id=? AND phase=? AND round_number=?)');
    $update=$pdo->prepare('UPDATE sx_ygo_matches SET scheduled_on=?,starts_at="21:00:00" WHERE id=?');
    $cursor=new DateTimeImmutable($start);
    foreach($matches as $match){
        $notBefore=ygo_deck_available_from($pdo,[(int)$match['deck_a_id'],(int)$match['deck_b_id']]);if($notBefore!=='' && $cursor->format('Y-m-d')<$notBefore)$cursor=new DateTimeImmutable($notBefore);
        while(true){$date=$cursor->format('Y-m-d');if($date>$program['ends_on'])throw new RuntimeException('La siguiente fase de copa excede el cierre de temporada.');$weekday=(int)$cursor->format('N');
            $used->execute([$date,$competitionId,$phase,$round]);$rows=$used->fetchAll(PDO::FETCH_ASSOC);$busy=false;foreach($rows as $r)if(substr((string)$r['starts_at'],0,5)==='21:00'||array_intersect([(int)$match['deck_a_id'],(int)$match['deck_b_id']],[(int)$r['deck_a_id'],(int)$r['deck_b_id']]))$busy=true;
            if((in_array($weekday,[1,3,5,7],true)||$date===$program['cup_start'])&&count($rows)<3&&!$busy)break;$cursor=$cursor->modify('+1 day');}
        $update->execute([$date,$match['id']]);$cursor=$cursor->modify('+1 day');
    }
    $pdo->prepare('UPDATE sx_ygo_competitions SET ends_on=GREATEST(COALESCE(ends_on,?),?) WHERE id=?')->execute([$date,$date,$competitionId]);
}

function ygo_install_fixed_calendar_2026(PDO $pdo): array {
    if(!$pdo->inTransaction())throw new RuntimeException('Se requiere una transaccion para instalar el calendario.');
    $season=ygo_season_find($pdo,1,true);if(!$season||!in_array($season['status'],['draft','active'],true))throw new RuntimeException('Temporada no disponible.');
    if(ygo_fixed_program($pdo))throw new RuntimeException('El calendario fijo ya fue instalado.');
    $competitions=$pdo->query('SELECT * FROM sx_ygo_competitions WHERE active=1 AND season_id=1 ORDER BY id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);$byId=array_column($competitions,null,'id');
    foreach([16,19,20,25,26,27,28] as $id)if(!isset($byId[$id]))throw new RuntimeException('Falta un evento esperado.');
    $leagueIds=[19,20,25,26,27,28];
    if((int)$pdo->query('SELECT COUNT(*) FROM sx_ygo_matches WHERE competition_id IN(19,20,25,26,27,28)')->fetchColumn()!==0)throw new RuntimeException('Las ligas ya tienen encuentros; no se sobrescribiran.');
    $cup=$pdo->query('SELECT * FROM sx_ygo_matches WHERE competition_id=16 ORDER BY display_order,id')->fetchAll(PDO::FETCH_ASSOC);
    if(count($cup)!==6||array_filter($cup,static fn($m)=>$m['status']!=='PENDIENTE'))throw new RuntimeException('La copa no conserva sus seis cruces pendientes.');
    if((int)$pdo->query("SELECT COUNT(*) FROM sx_ygo_match_results r JOIN sx_ygo_matches m ON m.id=r.match_id WHERE m.competition_id IN(16,19,20,25,26,27,28) AND COALESCE(r.result,'')<>''")->fetchColumn())throw new RuntimeException('Existen resultados; no se modificaran.');
    $pdo->exec("UPDATE sx_ygo_seasons SET ends_on='2027-10-27' WHERE id=1");
    $divisions=$pdo->query('SELECT * FROM sx_ygo_divisions WHERE season_id=1 AND active=1 ORDER BY tier_order,id')->fetchAll(PDO::FETCH_ASSOC);$divByCode=array_column($divisions,null,'code');
    $anchors=[1=>[['APEX_1','19:00:00'],['ELITE_1','20:00:00']],3=>[['VANGUARD','19:00:00'],['ASCENSION','20:00:00']],5=>[['GOAT','19:00:00'],['RUSH_DUEL','20:00:00']],7=>[['APEX_2','19:00:00'],['ELITE_2','20:00:00']]];
    foreach($anchors as $slots)foreach($slots as [$code,$time])if(!isset($divByCode[$code]))throw new RuntimeException('Division faltante: '.$code);
    $rosterInsert=$pdo->prepare('INSERT INTO sx_ygo_competition_decks(competition_id,deck_id,sort_order,active) VALUES(?,?,?,1) ON DUPLICATE KEY UPDATE active=1,sort_order=VALUES(sort_order)');$extraRoster=[];
    foreach($leagueIds as $id){$league=$byId[$id];if($league['execution_model']!=='double_round_robin')throw new RuntimeException('La liga no esta en ida y vuelta.');
        $decks=$pdo->prepare('SELECT d.id FROM sx_ygo_decks d JOIN sx_ygo_divisions v ON v.id=d.division_id WHERE d.active=1 AND v.active=1 AND v.league_competition_id=? AND v.season_id=1 ORDER BY v.tier_order,d.seed_rank,d.id');$decks->execute([$id]);$ids=array_map('intval',$decks->fetchAll(PDO::FETCH_COLUMN));
        ygo_validate_format_roster($pdo,(string)$league['event_ruleset'],$ids);ygo_validate_league_roster($pdo,$id,$ids);
        $existing=$pdo->query('SELECT deck_id FROM sx_ygo_competition_decks WHERE competition_id='.$id.' AND active=1')->fetchAll(PDO::FETCH_COLUMN);if(array_diff($existing,$ids))throw new RuntimeException('La liga tiene inscritos ajenos a sus divisiones.');
        $extraRoster[$id]=array_values(array_diff($ids,array_map('intval',$existing)));foreach($ids as $index=>$deck)$rosterInsert->execute([$id,$deck,$index+1]);
        ygo_schedule_competition_by_model($pdo,$league,$ids,'2026-10-16','consecutive','1v1',1,3);
        $rule=ygo_valid_game_rule_id($pdo,1,(int)($league['game_rule_id']??0),(int)($league['game_rule_id']??0)?:null,'league','double_round_robin');
        $pdo->prepare('UPDATE sx_ygo_competitions SET game_rule_id=? WHERE id=?')->execute([$rule,$id]);$pdo->prepare('UPDATE sx_ygo_matches SET scheduled_on=NULL,starts_at=NULL,game_rule_id=? WHERE competition_id=?')->execute([$rule,$id]);
    }
    $leagueByDivision=[];foreach($divisions as $dv)$leagueByDivision[(int)$dv['id']]=(int)$dv['league_competition_id'];
    $queues=[];$all=$pdo->query('SELECT * FROM sx_ygo_matches WHERE competition_id IN(19,20,25,26,27,28) ORDER BY round_number,table_number,id')->fetchAll(PDO::FETCH_ASSOC);foreach($all as $m)$queues[(int)$m['division_id']][]=$m;
    if(count($all)!==516)throw new RuntimeException('Se esperaban 516 combates de liga.');
    $update=$pdo->prepare('UPDATE sx_ygo_matches SET scheduled_on=?,starts_at=?,notes=CONCAT(COALESCE(notes,"")," Calendario fijo 2026.") WHERE id=?');$cupDates=['2026-10-17','2026-10-18','2026-10-19','2026-10-21','2026-10-23','2026-10-25'];$cupByDate=[];foreach($cup as $index=>$m){$cupByDate[$cupDates[$index]]=$m;$update->execute([$cupDates[$index],'21:00:00',$m['id']]);}
    $scheduled=[];$first=[];$last=[];$replacements=0;
    $take=function(int $division,string $date,string $time,array &$used)use(&$queues,$update,&$scheduled,&$first,&$last){$queue=$queues[$division]??[];if(!$queue)return false;$round=(int)$queue[0]['round_number'];foreach($queue as $index=>$match){if((int)$match['round_number']!==$round)break;$a=(int)$match['deck_a_id'];$b=(int)$match['deck_b_id'];if(isset($used[$a])||isset($used[$b]))continue;$update->execute([$date,$time,$match['id']]);$used[$a]=true;$used[$b]=true;array_splice($queues[$division],$index,1);$scheduled[]=['date'=>$date,'time'=>$time,'division'=>$division,'match'=>(int)$match['id']];$first[$division]??=$date;$last[$division]=$date;return true;}return false;};
    $cursor=new DateTimeImmutable('2026-10-16');
    while(array_sum(array_map('count',$queues))>0){$date=$cursor->format('Y-m-d');if($date>'2027-10-27')throw new RuntimeException('El calendario excede el cierre autorizado.');$weekday=(int)$cursor->format('N');$used=[];$usedLeagues=[];
        if(isset($cupByDate[$date])){$used[(int)$cupByDate[$date]['deck_a_id']]=true;$used[(int)$cupByDate[$date]['deck_b_id']]=true;}
        $slots=$anchors[$weekday]??[];
        if($weekday===6)$slots=$date==='2026-10-17'?[['extra','19:00:00'],['extra','20:00:00']]:[['extra','19:00:00'],['extra','20:00:00'],['extra','21:00:00']];
        foreach($slots as [$code,$time]){$done=false;$allowExtra=$code==='extra';if(!$allowExtra){$division=(int)$divByCode[$code]['id'];$allowExtra=empty($queues[$division]);if(!$allowExtra)$done=$take($division,$date,$time,$used);}
            if(!$done&&$allowExtra){$priorities=array_keys($queues);usort($priorities,static fn($a,$b)=>(isset($usedLeagues[$leagueByDivision[$a]])<=>isset($usedLeagues[$leagueByDivision[$b]]))?:(count($queues[$b])<=>count($queues[$a]))?:$a<=>$b);foreach($priorities as $division)if($take($division,$date,$time,$used)){$done=true;if($code!=='extra')$replacements++;break;}}if($done)$usedLeagues[$leagueByDivision[$division]]=true;}
        $cursor=$cursor->modify('+1 day');
    }
    $program=['version'=>1,'season_id'=>1,'starts_on'=>'2026-10-16','ends_on'=>'2027-10-27','cup_start'=>'2026-10-17','cup_id'=>16,'anchors'=>$anchors,'saturday_extra'=>3,'reuse_finished_league_slots'=>true];ygo_save_setting($pdo,'fixed_program_2026',json_encode($program));
    $summary=[];foreach([16,...$leagueIds] as $id){$range=$pdo->query('SELECT MIN(scheduled_on) first_day,MAX(scheduled_on) last_day,COUNT(*) matches_count FROM sx_ygo_matches WHERE competition_id='.$id)->fetch(PDO::FETCH_ASSOC);$end=$id===16?'2026-11-02':$range['last_day'];$pdo->prepare("UPDATE sx_ygo_competitions SET starts_on=?,ends_on=?,status='pending_event',qualifier_matches_per_day=3,configuration_locked=1,schedule_strategy='fixed_weekly' WHERE id=?")->execute([$range['first_day'],$end,$id]);ygo_rebuild_competition_calendar_events_from_matches($pdo,1,$id,$id===16?'cup':'league');$summary[]=['id'=>$id,'name'=>$byId[$id]['name'],'first'=>$range['first_day'],'last_scheduled'=>$range['last_day'],'planned_end'=>$end,'matches'=>(int)$range['matches_count']];}
    foreach($first as $division=>$date)$pdo->prepare('UPDATE sx_ygo_divisions SET starts_on=?,ends_on=? WHERE id=?')->execute([$date,$last[$division],$division]);
    ygo_validate_fixed_calendar_2026($pdo);
    $pdo->prepare('INSERT INTO sx_ygo_audit_log(entity_type,entity_id,action,payload) VALUES(?,?,?,?)')->execute(['season',1,'install_fixed_calendar_2026',json_encode(['program'=>$program,'summary'=>$summary,'added_roster'=>$extraRoster,'reused_slots'=>$replacements])]);
    return ['events'=>$summary,'league_matches'=>516,'cup_initial_matches'=>6,'added_roster'=>$extraRoster,'first_week'=>array_values(array_filter($scheduled,static fn($r)=>$r['date']<='2026-10-22')),'reused_slots'=>$replacements];
}

function ygo_validate_fixed_calendar_2026(PDO $pdo): void {
    $rows=$pdo->query('SELECT m.*,c.type FROM sx_ygo_matches m JOIN sx_ygo_competitions c ON c.id=m.competition_id WHERE c.season_id=1 AND c.active=1 ORDER BY m.scheduled_on,m.starts_at,m.id')->fetchAll(PDO::FETCH_ASSOC);$days=[];$seen=[];$times=[];
    $deckDiv=$pdo->query('SELECT id,division_id FROM sx_ygo_decks')->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach($rows as $m){$date=(string)$m['scheduled_on'];$time=substr((string)$m['starts_at'],0,5);if($date<'2026-10-16'||$date>'2027-10-27')throw new RuntimeException('Fecha fuera del periodo autorizado.');if(isset($times[$date][$time]))throw new RuntimeException('Horario repetido.');$times[$date][$time]=true;$days[$date]=($days[$date]??0)+1;if($days[$date]>3)throw new RuntimeException('Se exceden tres combates por dia.');
        foreach(['deck_a_id','deck_b_id'] as $field){$deck=(int)$m[$field];if(isset($seen[$date][$deck]))throw new RuntimeException('Un deck juega dos veces el mismo dia.');$seen[$date][$deck]=true;if($m['type']==='league'&&(int)$deckDiv[$deck] !== (int)$m['division_id'])throw new RuntimeException('Cruce entre divisiones.');}
    }
}

function ygo_expand_recent_decks_calendar_2026(PDO $pdo): array {
    $action='recent_decks_calendar_20261127_v1';
    $done=$pdo->prepare('SELECT payload FROM sx_ygo_audit_log WHERE action=? ORDER BY id DESC LIMIT 1');$done->execute([$action]);$payload=$done->fetchColumn();
    if($payload!==false)return json_decode((string)$payload,true) ?: ['applied'=>true];

    $targetIds=[734,735,736,737,738,739,740,741];
    $targetQuery=$pdo->prepare('SELECT id,name,division_id FROM sx_ygo_decks WHERE active=1 AND id IN('.implode(',',array_fill(0,count($targetIds),'?')).') ORDER BY id');$targetQuery->execute($targetIds);$targets=$targetQuery->fetchAll(PDO::FETCH_ASSOC);
    if(!$targets)return ['applied'=>false,'reason'=>'target_decks_absent'];
    if(count($targets)!==count($targetIds)||array_map('intval',array_column($targets,'id'))!==$targetIds)throw new RuntimeException('El lote de decks nuevos esta incompleto; no se modifico el calendario.');

    $specs=[
        ['competition_id'=>27,'division_id'=>26327,'target_ids'=>[737,738,739,740,741],'expected'=>20,'starts_on'=>'2026-11-28'],
        ['competition_id'=>27,'division_id'=>26323,'target_ids'=>[735,736],'expected'=>26,'starts_on'=>'2026-11-28'],
        ['competition_id'=>28,'division_id'=>26324,'target_ids'=>[734],'expected'=>18,'starts_on'=>'2026-12-20'],
    ];
    $ownsTransaction=!$pdo->inTransaction();if($ownsTransaction)$pdo->beginTransaction();
    try {
        $lock=$pdo->query('SELECT id FROM sx_ygo_competitions WHERE id IN(27,28) ORDER BY id FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN);if(array_map('intval',$lock)!==[27,28])throw new RuntimeException('No se encontraron las ligas esperadas.');
        $done->execute([$action]);if(($payload=$done->fetchColumn())!==false){if($ownsTransaction)$pdo->commit();return json_decode((string)$payload,true) ?: ['applied'=>true];}

        $competitionRows=$pdo->query('SELECT * FROM sx_ygo_competitions WHERE id IN(27,28)')->fetchAll(PDO::FETCH_ASSOC);$competitions=array_column($competitionRows,null,'id');
        foreach($specs as $spec){$division=$pdo->prepare('SELECT league_competition_id FROM sx_ygo_divisions WHERE id=? AND season_id=1 AND active=1');$division->execute([$spec['division_id']]);if((int)$division->fetchColumn()!==$spec['competition_id'])throw new RuntimeException('Una division nueva no pertenece a la liga esperada.');foreach($spec['target_ids'] as $id){$row=$targets[array_search($id,$targetIds,true)]??null;if((int)($row['division_id']??0)!==$spec['division_id'])throw new RuntimeException('Un deck nuevo cambio de division; no se modifico el calendario.');}}

        $rosterInsert=$pdo->prepare('INSERT INTO sx_ygo_competition_decks(competition_id,deck_id,sort_order,active) VALUES(?,?,?,1) ON DUPLICATE KEY UPDATE active=1,sort_order=VALUES(sort_order)');
        foreach([27,28] as $competitionId){$roster=$pdo->prepare('SELECT d.id FROM sx_ygo_decks d JOIN sx_ygo_divisions v ON v.id=d.division_id WHERE d.active=1 AND v.active=1 AND v.season_id=1 AND v.league_competition_id=? ORDER BY v.tier_order,d.seed_rank,d.id');$roster->execute([$competitionId]);foreach(array_map('intval',$roster->fetchAll(PDO::FETCH_COLUMN)) as $index=>$deckId)$rosterInsert->execute([$competitionId,$deckId,$index+1]);}

        $occupiedTimes=[];$busyDecks=[];$dailyCounts=[];$occupied=$pdo->query('SELECT scheduled_on,starts_at,deck_a_id,deck_b_id,deck_c_id,deck_d_id,deck_e_id,deck_f_id FROM sx_ygo_matches WHERE scheduled_on IS NOT NULL')->fetchAll(PDO::FETCH_ASSOC);
        foreach($occupied as $match){$date=(string)$match['scheduled_on'];$time=(string)$match['starts_at'];if($time!=='')$occupiedTimes[$date][$time]=true;$dailyCounts[$date]=($dailyCounts[$date]??0)+1;foreach(['deck_a_id','deck_b_id','deck_c_id','deck_d_id','deck_e_id','deck_f_id'] as $field)if((int)($match[$field]??0)>0)$busyDecks[$date][(int)$match[$field]]=true;}

        $insert=$pdo->prepare('INSERT INTO sx_ygo_matches (division_id,competition_id,game_rule_id,phase,match_format,best_of,participants_per_side,round_number,table_number,bracket_round_label,bracket_slot,bracket_side,display_order,generation_number,deck_a_id,deck_b_id,status,scheduled_on,starts_at,venue,notes) VALUES (?,?,?,\'league\',?,?,?,?,?,?,?,\'main\',?,1,?,?,\'PENDIENTE\',?,?,\'Station X Tulua\',?)');
        $summary=[];$total=0;
        foreach($specs as $spec){$deckQuery=$pdo->prepare('SELECT id FROM sx_ygo_decks WHERE active=1 AND division_id=? ORDER BY seed_rank,id');$deckQuery->execute([$spec['division_id']]);$deckIds=array_map('intval',$deckQuery->fetchAll(PDO::FETCH_COLUMN));$pairQuery=$pdo->prepare('SELECT deck_a_id,deck_b_id FROM sx_ygo_matches WHERE competition_id=? AND division_id=? AND phase=\'league\'');$pairQuery->execute([$spec['competition_id'],$spec['division_id']]);$pairs=array_map(static fn($row)=>(int)$row['deck_a_id'].':'.(int)$row['deck_b_id'],$pairQuery->fetchAll(PDO::FETCH_ASSOC));$lastRoundQuery=$pdo->prepare('SELECT COALESCE(MAX(round_number),0) FROM sx_ygo_matches WHERE competition_id=? AND division_id=? AND phase=\'league\'');$lastRoundQuery->execute([$spec['competition_id'],$spec['division_id']]);
            $fixtures=\StationX\Yugioh\Domain\Calendar\LeagueExpansionPlan::missingFixtures($deckIds,$pairs,(int)$lastRoundQuery->fetchColumn());if(count($fixtures)!==$spec['expected'])throw new RuntimeException('La cantidad de cruces faltantes cambio; no se modifico el calendario.');$fixtures=\StationX\Yugioh\Domain\Calendar\LeagueExpansionPlan::schedule($fixtures,$spec['starts_on'],'2027-10-27',$occupiedTimes,$busyDecks,$dailyCounts);$event=$competitions[$spec['competition_id']];$first='';$last='';
            foreach($fixtures as $index=>$fixture){$display=((int)$fixture['round_number']-1)*100+(int)$fixture['table_number'];$insert->execute([$spec['division_id'],$spec['competition_id'],$event['game_rule_id']?:null,$event['match_format']?:'1v1',(int)($event['best_of']?:5),(int)($event['participants_per_side']?:1),$fixture['round_number'],$fixture['table_number'],'Ronda '.$fixture['round_number'],$fixture['table_number'],$display,$fixture['deck_a_id'],$fixture['deck_b_id'],$fixture['scheduled_on'],$fixture['starts_at'],'Ampliacion incremental de liga para decks incorporados el 2026-11-27.']);$first=$first===''?$fixture['scheduled_on']:min($first,$fixture['scheduled_on']);$last=max($last,$fixture['scheduled_on']);}
            $pdo->prepare('UPDATE sx_ygo_divisions SET starts_on=LEAST(COALESCE(starts_on,?),?),ends_on=GREATEST(COALESCE(ends_on,?),?) WHERE id=?')->execute([$first,$first,$last,$last,$spec['division_id']]);$pdo->prepare('UPDATE sx_ygo_competitions SET ends_on=GREATEST(COALESCE(ends_on,?),?) WHERE id=?')->execute([$last,$last,$spec['competition_id']]);$summary[]=['competition_id'=>$spec['competition_id'],'division_id'=>$spec['division_id'],'matches'=>count($fixtures),'first'=>$first,'last'=>$last];$total+=count($fixtures);
        }
        foreach([27,28] as $competitionId)ygo_rebuild_competition_calendar_events_from_matches($pdo,1,$competitionId,'league');
        ygo_validate_fixed_calendar_2026($pdo);$result=['applied'=>true,'matches_created'=>$total,'decks'=>$targetIds,'divisions'=>$summary];$pdo->prepare('INSERT INTO sx_ygo_audit_log(entity_type,entity_id,action,payload) VALUES(?,?,?,?)')->execute(['season',1,$action,json_encode($result,JSON_UNESCAPED_UNICODE)]);if($ownsTransaction)$pdo->commit();return $result;
    } catch(Throwable $error){if($ownsTransaction&&$pdo->inTransaction())$pdo->rollBack();throw $error;}
}


function ygo_deck_available_from(PDO $pdo,array $deckIds): string {
    $rules=json_decode(ygo_settings($pdo)['deck_not_before_2026'] ?? '[]',true) ?: [];
    $date='';foreach($deckIds as $id)if(!empty($rules[(int)$id]))$date=max($date,(string)$rules[(int)$id]);return $date;
}

function ygo_delay_named_decks_2026(PDO $pdo): array {
    if(!$pdo->inTransaction())throw new RuntimeException('Se requiere transaccion.');
    $names=['Megalith','Zoodiac','Gouki','Magikey','Inzektor','Mannadium','SC Mannadium','Dogmatika','P.U.N.K.','Jinzo','Crystal Beast','Ancient Machine'];
    $query=$pdo->prepare('SELECT id,name FROM sx_ygo_decks WHERE active=1 AND name IN('.implode(',',array_fill(0,count($names),'?')).') ORDER BY id');$query->execute($names);$decks=$query->fetchAll(PDO::FETCH_ASSOC);if(count($decks)!==12)throw new RuntimeException('No se encontraron exactamente los doce decks.');
    $ids=array_map('intval',array_column($decks,'id'));$restricted=array_fill_keys($ids,true);$cutoff='2026-10-26';$dateRules=json_decode(ygo_settings($pdo)['deck_not_before_2026'] ?? '[]',true) ?: [];foreach($ids as $id)$dateRules[$id]=$cutoff;ygo_save_setting($pdo,'deck_not_before_2026',json_encode($dateRules));
    $all=$pdo->query('SELECT m.*,c.type competition_type FROM sx_ygo_matches m LEFT JOIN sx_ygo_competitions c ON c.id=m.competition_id WHERE c.season_id=1 AND c.active=1 ORDER BY m.scheduled_on,m.starts_at,m.id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
    $hasRestricted=static function($m)use($restricted){foreach(['deck_a_id','deck_b_id','deck_c_id','deck_d_id','deck_e_id','deck_f_id'] as $key)if(isset($restricted[(int)($m[$key]??0)]))return true;return false;};
    $early=array_values(array_filter($all,static fn($m)=>$m['scheduled_on']!==null&&$m['scheduled_on']<$cutoff&&$hasRestricted($m)));foreach($early as $m)if($m['status']==='FINALIZADA')throw new RuntimeException('No se pueden mover resultados finalizados.');
    $affected=array_fill_keys(array_map(static fn($m)=>(int)$m['division_id'],$early),true);$groups=[];$original=[];$owners=[];$occupancy=[];$usedDecks=[];
    foreach($all as $m){$id=(int)$m['id'];$original[$id]=$m;$date=$m['scheduled_on'];$time=$m['starts_at'];$division=(int)$m['division_id'];
        if(isset($affected[$division])&&$m['competition_type']==='league'&&$m['status']!=='FINALIZADA'){$groups[$division][]=$m;$owners[$date.' '.$time]=$division;continue;}
        if($date){$occupancy[$date][$time]=$id;foreach(['deck_a_id','deck_b_id','deck_c_id','deck_d_id','deck_e_id','deck_f_id'] as $key)if(!empty($m[$key]))$usedDecks[$date][(int)$m[$key]]=true;}}
    if(count($groups)!==count($affected))throw new RuntimeException('Se encontro un evento sin division compatible con esta reprogramacion.');
    $clear=$pdo->prepare('UPDATE sx_ygo_matches SET scheduled_on=NULL,starts_at=NULL WHERE id=?');foreach($groups as $matches)foreach($matches as $m)$clear->execute([$m['id']]);
    $update=$pdo->prepare('UPDATE sx_ygo_matches SET scheduled_on=?,starts_at=? WHERE id=?');$changed=[];
    foreach($groups as $division=>$matches){$remaining=$matches;usort($remaining,static fn($a,$b)=>[(int)$a['round_number'],$a['scheduled_on'],$a['starts_at'],(int)$a['id']]<=>[(int)$b['round_number'],$b['scheduled_on'],$b['starts_at'],(int)$b['id']]);$slots=array_map(static fn($m)=>[$m['scheduled_on'],$m['starts_at'],(int)$m['id']],$matches);$lastOriginal=max(array_column($matches,'scheduled_on'));
        $place=function(string $date,string $time,?int $preferred=null)use(&$remaining,&$occupancy,&$usedDecks,&$changed,$original,$hasRestricted,$cutoff,$update){if(isset($occupancy[$date][$time])||count($occupancy[$date]??[])>=3||!$remaining)return false;$round=(int)$remaining[0]['round_number'];$order=array_keys($remaining);if($preferred)usort($order,static fn($a,$b)=>((int)$remaining[$b]['id']===$preferred)<=>((int)$remaining[$a]['id']===$preferred));foreach($order as $index){$m=$remaining[$index];if((int)$m['round_number']!==$round)continue;if($date<$cutoff&&$hasRestricted($m))continue;$busy=false;foreach(['deck_a_id','deck_b_id','deck_c_id','deck_d_id','deck_e_id','deck_f_id'] as $key)if(isset($usedDecks[$date][(int)($m[$key]??0)]))$busy=true;if($busy)continue;
                $update->execute([$date,$time,$m['id']]);$occupancy[$date][$time]=(int)$m['id'];foreach(['deck_a_id','deck_b_id','deck_c_id','deck_d_id','deck_e_id','deck_f_id'] as $key)if(!empty($m[$key]))$usedDecks[$date][(int)$m[$key]]=true;
                if($original[(int)$m['id']]['scheduled_on']!==$date||$original[(int)$m['id']]['starts_at']!==$time)$changed[]=['id'=>(int)$m['id'],'from'=>$original[(int)$m['id']]['scheduled_on'],'to'=>$date,'time'=>$time];array_splice($remaining,$index,1);return true;}return false;};
        foreach($slots as [$date,$time,$preferred])$place($date,$time,$preferred);
        $divisionStmt=$pdo->prepare('SELECT code,league_competition_id FROM sx_ygo_divisions WHERE id=?');$divisionStmt->execute([$division]);$dv=$divisionStmt->fetch(PDO::FETCH_ASSOC);$anchor=['APEX_2'=>[7,'19:00:00'],'ELITE_1'=>[1,'20:00:00'],'ASCENSION'=>[3,'20:00:00']][$dv['code']]??null;if(!$anchor)throw new RuntimeException('Division sin horario de referencia.');
        $cursor=new DateTimeImmutable(max($lastOriginal,$cutoff));$cursor=$cursor->modify('+1 day');while($remaining){$date=$cursor->format('Y-m-d');if($date>'2027-10-27')throw new RuntimeException('La restriccion excede el cierre de temporada.');$weekday=(int)$cursor->format('N');$times=$weekday===6?['19:00:00','20:00:00','21:00:00']:($weekday===$anchor[0]?[$anchor[1]]:[]);foreach($times as $time){$owner=$owners[$date.' '.$time]??$division;if($owner!==$division)continue;$place($date,$time);}$cursor=$cursor->modify('+1 day');}
        $pdo->prepare('UPDATE sx_ygo_divisions SET starts_on=(SELECT MIN(scheduled_on) FROM sx_ygo_matches WHERE division_id=? AND competition_id=?),ends_on=(SELECT MAX(scheduled_on) FROM sx_ygo_matches WHERE division_id=? AND competition_id=?) WHERE id=?')->execute([$division,$dv['league_competition_id'],$division,$dv['league_competition_id'],$division]);
    }
    // Fill early slots left empty by the restriction with eligible pending league matches.
    // Lower rounds must already have been scheduled before that date.
    $freed=[];foreach($owners as $key=>$division){[$date,$time]=explode(' ',$key,2);if($date<$cutoff&&!isset($occupancy[$date][$time]))$freed[]=[$date,$time];}
    sort($freed);$backfills=[];
    foreach($freed as [$date,$time]){
        $candidates=$pdo->query("SELECT * FROM sx_ygo_matches WHERE competition_id IN(25,26) AND status='PENDIENTE' AND scheduled_on>='2026-10-26' ORDER BY round_number,scheduled_on,starts_at,id")->fetchAll(PDO::FETCH_ASSOC);
        $playedLeague=[];foreach($pdo->query("SELECT DISTINCT competition_id FROM sx_ygo_matches WHERE scheduled_on='".$date."' AND competition_id IN(19,20,25,26,27,28)")->fetchAll(PDO::FETCH_COLUMN) as $id)$playedLeague[(int)$id]=true;
        usort($candidates,static fn($a,$b)=>(isset($playedLeague[(int)$a['competition_id']])<=>isset($playedLeague[(int)$b['competition_id']]))?:[$a['scheduled_on'],$a['starts_at'],(int)$a['id']]<=>[$b['scheduled_on'],$b['starts_at'],(int)$b['id']]);
        foreach($candidates as $m){if($hasRestricted($m))continue;$busy=false;foreach(['deck_a_id','deck_b_id'] as $key)if(isset($usedDecks[$date][(int)$m[$key]]))$busy=true;if($busy)continue;
            $prior=$pdo->prepare('SELECT COUNT(*) FROM sx_ygo_matches WHERE competition_id=? AND division_id=? AND round_number<? AND scheduled_on>=?');$prior->execute([$m['competition_id'],$m['division_id'],$m['round_number'],$date]);if((int)$prior->fetchColumn())continue;
            $oldDate=$m['scheduled_on'];$oldTime=$m['starts_at'];$update->execute([$date,$time,$m['id']]);unset($occupancy[$oldDate][$oldTime]);foreach(['deck_a_id','deck_b_id'] as $key){unset($usedDecks[$oldDate][(int)$m[$key]]);$usedDecks[$date][(int)$m[$key]]=true;}$occupancy[$date][$time]=(int)$m['id'];
            $changed[]=['id'=>(int)$m['id'],'from'=>$original[(int)$m['id']]['scheduled_on'],'to'=>$date,'time'=>$time];$backfills[]=(int)$m['id'];break;}
    }
    $changed=array_values(array_column($changed,null,'id'));
    foreach([25,26,28] as $id){$pdo->prepare('UPDATE sx_ygo_competitions SET starts_on=(SELECT MIN(scheduled_on) FROM sx_ygo_matches WHERE competition_id=?),ends_on=(SELECT MAX(scheduled_on) FROM sx_ygo_matches WHERE competition_id=?) WHERE id=?')->execute([$id,$id,$id]);ygo_rebuild_competition_calendar_events_from_matches($pdo,1,$id,'league');}
    foreach($pdo->query('SELECT m.* FROM sx_ygo_matches m JOIN sx_ygo_competitions c ON c.id=m.competition_id WHERE c.season_id=1')->fetchAll(PDO::FETCH_ASSOC) as $m)if($hasRestricted($m)&&$m['scheduled_on']<$cutoff)throw new RuntimeException('Queda un encuentro antes de la fecha minima.');
    ygo_validate_fixed_calendar_2026($pdo);$first=[];foreach($decks as $d){$stmt=$pdo->prepare('SELECT MIN(scheduled_on) FROM sx_ygo_matches WHERE ? IN(deck_a_id,deck_b_id,COALESCE(deck_c_id,0),COALESCE(deck_d_id,0),COALESCE(deck_e_id,0),COALESCE(deck_f_id,0))');$stmt->execute([(int)$d['id']]);$first[]=['id'=>(int)$d['id'],'name'=>$d['name'],'first_match'=>$stmt->fetchColumn()];}
    $result=['cutoff'=>$cutoff,'decks'=>$first,'early_matches_before'=>count($early),'changed_matches'=>$changed,'backfilled_slots'=>$backfills];$pdo->prepare('INSERT INTO sx_ygo_audit_log(entity_type,entity_id,action,payload) VALUES(?,?,?,?)')->execute(['season',1,'decks_available_from_20261026',json_encode($result)]);return $result;
}
