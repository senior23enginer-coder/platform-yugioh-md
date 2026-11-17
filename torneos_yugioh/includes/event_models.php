<?php
declare(strict_types=1);
require_once __DIR__.'/event_workflows.php';
require_once __DIR__.'/event_admission.php';
require_once __DIR__.'/event_recovery.php';
require_once __DIR__.'/event_teams.php';
require_once __DIR__.'/event_contracts.php';
require_once __DIR__.'/event_catalog_rules.php';
require_once __DIR__.'/free_for_all.php';

/** Capabilities describe implemented algorithms, not merely entries in a selector. */
function ygo_execution_capabilities(): array
{
    $missing = [
        'custom' => 'El motor personalizado requiere una definicion implementada y verificable.',
    ];
    $out = [];
    foreach (['manual', 'round_robin', 'double_round_robin', 'league_points', 'single_elimination', 'bracket', 'free_for_all'] as $code) {
        $out[$code] = ['status' => 'IMPLEMENTADO', 'reason' => '', 'automatic' => !in_array($code,['manual','free_for_all'],true)];
    }
    foreach ($missing as $code => $reason) $out[$code] = ['status' => 'INCOMPLETO', 'reason' => $reason, 'automatic' => false];
    foreach(ygo_workflow_models() as $code)$out[$code]=['status'=>'IMPLEMENTADO','reason'=>'','automatic'=>true,'policy'=>'STATION_X_2026_V1'];
    foreach(['top_cut','playoffs'] as $code)$out[$code]=['status'=>'IMPLEMENTADO','reason'=>'','automatic'=>true,'admission_required'=>true];
    return $out;
}

/** A source catalogue entry is not an automatically enabled competition template. */
function ygo_extended_event_catalog(): array
{
    $rows=json_decode((string)file_get_contents(__DIR__.'/event_catalog_extended.json'),true) ?: [];
    $normalize=static function(string $s): string {
        $s=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s) ?: $s;
        return strtolower((string)preg_replace('/[^a-zA-Z0-9]/','',$s));
    };
    $templates=[];foreach(ygo_event_available_templates() as $t)$templates[$normalize($t['label'])]=$t;
    $aliases=['worldchampionshipwcs'=>'worldchampionship','yugiohchampionshipseries'=>'ycsyugiohchampionshipseries','publiceventsplayoff'=>'publiceventplayoff','tagdueltournament'=>'tagduel','timewizardtournament'=>'timewizard','masterdueltournament'=>'masterdueltournament','duellinkstournament'=>'duellinkstournament'];
    foreach($rows as &$row){
        $key=$normalize($row['name']);$key=$aliases[$key]??$key;$t=$templates[$key]??null;
        $row['source']='catalogo_106';$row['template_code']=$t['code']??null;
        $row['event_type']=$t['type']??null;
        $row['operational_status']=$t['operational_status']??'REQUIERE_DEFINICION';
        $row['diagnostic']=$t?implode(' ',$t['activation_restrictions']):'Denominacion de negocio conservada. Requiere definir su tipo, motor, parametros y reglamento; no crea eventos automaticamente.';
    }
    unset($row);return $rows;
}

function ygo_correct_template_contract(array $template): array
{
    $code = (string) $template['code'];
    if (($template['type'] ?? '') === 'league') $template['best_of'] = 5;
    static $descriptors=null;$descriptors??=json_decode((string)file_get_contents(__DIR__.'/event_model_descriptors.json'),true,512,JSON_THROW_ON_ERROR);
    $template['purpose']=$descriptors[$code]['purpose']??'';$template['difference']=$descriptors[$code]['difference']??'';
    if (in_array($code, ['liga', 'team_league', 'online_league'], true)) {
        $template = array_merge($template, ['execution_model'=>'round_robin', 'system'=>'Todos contra todos', 'schedule'=>'auto_round_robin', 'phase_structure'=>'REGULAR_SEASON']);
    }
    if ($code === 'swiss_top_cut') $template=array_merge($template,['type'=>'tournament','participant_source'=>'MANUAL','event_flow'=>'STANDALONE','advancement_rule'=>'TOP_N']);
    if(in_array($code,['super_cup','champions_cup'],true))$template=array_merge($template,['participant_source'=>'PREVIOUS_EVENT','event_flow'=>'RECEIVES_FROM_EVENT']);
    if ($code === 'group_league') $template=array_merge($template,['execution_model'=>'groups','phase_structure'=>'GROUPS','system'=>'Round Robin por grupos']);
    if ($code === 'grand_final') $template = array_merge($template, ['execution_model'=>'series','system'=>'Serie final','phase_structure'=>'FINAL']);
    if ($code === 'world_championship') $template = array_merge($template, ['execution_model'=>'groups_knockout','phase_structure'=>'GROUPS_TOP_CUT_FINAL','schedule'=>'auto_knockout']);
    if ($code === 'yugioh_day') $template['type'] = 'special';
    if ($code === 'best_of_1_event') $template['best_of'] = 1;
    if ($code === 'best_of_3_event') $template['best_of'] = 3;
    if ($code === 'all_star_event') $template=array_merge($template,['execution_model'=>'manual','system'=>'Cruces autorizados de invitados','schedule'=>'manual','match_format'=>'1v1','participants_per_side'=>1]);
    $phaseByModel=['swiss_top_cut'=>'TOP_CUT','groups'=>'GROUPS','groups_knockout'=>'GROUPS_TOP_CUT_FINAL','season_playoffs'=>'SEASON_PLAYOFF_FINAL','series'=>'FINAL'];
    if(isset($phaseByModel[$template['execution_model']]))$template['phase_structure']=$phaseByModel[$template['execution_model']];
    $engine=$template['execution_model'];
    if(in_array($engine,['swiss','round_robin','double_round_robin','league_points','groups','ladder'],true))$template['advancement_rule']=$template['type']==='qualifier'?'SOURCE_ADMISSION':'FINAL_STANDINGS';
    if(in_array($engine,['swiss_top_cut','season_playoffs'],true))$template['advancement_rule']='TOP_N';
    if($engine==='groups_knockout')$template['advancement_rule']='GROUP_TOP_K';
    if(in_array($engine,['series','king_of_the_hill','gauntlet','flights'],true))$template['advancement_rule']='WINNER_ONLY';
    if($engine==='manual')$template['advancement_rule']='MANUAL_ENTRIES';
    if(in_array($template['execution_model'],['single_elimination','bracket','double_elimination','top_cut','playoffs'],true))$template['advancement_rule']='WINNER_ONLY';
    if($code==='pegasus_challenge')$template=array_replace($template,['ruleset'=>'tcg_advanced','rules'=>'TCG Avanzado o Tradicional','execution_model'=>'swiss','system'=>'Suizo, eliminacion directa o liga configurable','match_format'=>'1v1','participants_per_side'=>1,'participation_code'=>'INDIVIDUAL_1V1','phase_structure'=>'NONE','advancement_rule'=>'FINAL_STANDINGS','allowed_execution_models'=>['swiss','single_elimination','round_robin','double_round_robin','league_points'],'purpose'=>'Desafio Pegasus individual con formato y sistema configurables.','difference'=>'TCG Avanzado o Tradicional; no se inventan efectos especiales ni se validan cartas.']);
    $template['required_inputs'] = [];
    if(($template['participant_source']??'')==='PREVIOUS_EVENT')$template['required_inputs'][]='Guardar la ficha y configurar Fuentes y clasificados: eventos cerrados, cupos y participantes verificados antes de programar.';
    if (in_array($code, ['team_ycs','ots_tournament_series','team_3v3','team_league','team_cup','team_championship'], true)) {
        $template['match_format']='3v3'; $template['participants_per_side']=3;
        $template['required_inputs'][]='Reglamento interno: equipos de tres, alineacion fija y victoria por dos resultados individuales. Configura Equipos y alineaciones en la ficha; las posiciones se fijan al comenzar.';
    }
    $diagnostic = ygo_model_diagnostics($template);
    $template['operational_status'] = $diagnostic ? 'REQUIERE_CONFIGURACION' : 'IMPLEMENTADO';
    $template['activation_restrictions'] = $diagnostic;
    if(in_array($code,['liga','team_league','online_league','group_league','swiss_top_cut','grand_final','world_championship','yugioh_day','best_of_1_event','best_of_3_event','all_star_event','pegasus_challenge'],true)){
        $template['format']=strtolower($template['system'].' | '.$template['construction'].' | '.$template['rules']);
        $template['notes']='Plantilla: '.$template['label']."\nSistema: ".$template['system']."\nMotor: ".$template['execution_model']."\nParticipacion: ".($template['match_format'] ?: 'Requiere eleccion explicita')."\nReglas: ".$template['rules']."\nEstado: ".$template['operational_status'];
    }
    return $template;
}

function ygo_event_choice_errors(array $config): array
{
    $errors=[];
    if(function_exists('ygo_event_model_options'))foreach(ygo_event_model_options() as $field=>$choices){
        if($field==='competition_system')continue;
        $raw=$config[$field.'_code']??$config[$field]??null;
        if($raw!==null && $raw!=='' && !array_key_exists(strtoupper((string)$raw),$choices))$errors[]='Valor no admitido para '.$field.'.';
    }
    return $errors;
}

/** Drafts remain representable; only supported, defined configurations may generate or activate. */
function ygo_model_diagnostics(array $config): array
{
    $errors=ygo_event_choice_errors($config);
    $execution = (string) ($config['execution_model'] ?? 'manual');
    $cap = ygo_execution_capabilities()[$execution] ?? ['reason'=>'Motor desconocido; no se sustituira por otro algoritmo.'];
    if(!empty($cap['reason']))$errors[]=$cap['reason'];
    $code = ygo_template_base_code((string) ($config['prompt_template'] ?? $config['code'] ?? ''));
    if($code==='clasificatorio_divisiones_3_duelos'&&(int)($config['qualifier_duels_per_deck']??3)>3)$errors[]='La plantilla de tres enfrentamientos no admite un limite superior a tres. Elige Suizo personalizado para otro limite.';
    if (in_array($code, ['tag_duel','team_ycs','ots_tournament_series','team_3v3','team_league','team_cup','team_championship'], true)
        || (int) ($config['participants_per_side'] ?? 1) > 1) {
        if (empty($config['_teams_configured'])) $errors[] = 'Configura Equipos y alineaciones en la ficha antes de activar. Tag Duel usa una serie compartida; 3v3 usa tres puestos.';
        if(ygo_team_kind($config)==='')$errors[]='La modalidad no tiene un contrato de equipos implementado.';
        if(!in_array($execution,['swiss','swiss_top_cut','round_robin','double_round_robin','league_points','single_elimination','bracket','groups','groups_knockout','series','double_elimination','season_playoffs'],true))$errors[]='Motor no disponible para equipos.';
    }
    if($code==='pegasus_challenge'&&!in_array($config['event_ruleset']??$config['ruleset']??'tcg_advanced',['tcg_advanced','tcg_traditional'],true))$errors[]='Pegasus usa TCG Avanzado o Tradicional.';
    return array_values(array_unique($errors));
}

function ygo_require_supported_model(array $config): void
{
    $errors = ygo_model_diagnostics($config);
    if ($errors) throw new InvalidArgumentException('Modelo no disponible para nueva programacion/activacion: '.implode(' ', $errors));
}

function ygo_require_execution_type(string $type, string $execution): void
{
    if (!in_array($execution,ygo_allowed_execution_models_for_type($type),true)) {
        throw new InvalidArgumentException('El sistema '.$execution.' no es compatible con el tipo '.$type.'. Selecciona uno compatible; no se cambiara automaticamente.');
    }
}

function ygo_assert_fixture_mutable(PDO $pdo, int $competitionId): void
{
    if(ygo_table_exists($pdo,'sx_ygo_ffa_matches')){$q=$pdo->prepare('SELECT COUNT(*) FROM sx_ygo_ffa_matches WHERE competition_id=?');$q->execute([$competitionId]);if($q->fetchColumn())throw new InvalidArgumentException('El evento tiene mesas multijugador y su configuracion esta fijada.');}
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM sx_ygo_matches WHERE competition_id=?');
    $stmt->execute([$competitionId]);
    if ((int)$stmt->fetchColumn()>0) throw new InvalidArgumentException('El evento ya tiene combates. No se regenerara ni sustituira su calendario.');
}

/** Pure, shared contract for PHP/JS parity. Exact rows are never mutated or expanded. */
function ygo_evaluate_sequence(array $values, int $bestOf = 3, array $conditions = []): array
{
    $invalid = static fn(string $message): array => ['status'=>'INVALID','winner'=>null,'score'=>'0-0','validation'=>$message,'rule'=>'Integridad D1-D5'];
    if (count($values)>5) return $invalid('No se permite D6.');
    $played=[]; $gap=false;
    foreach($values as $value) {
        if ($value==='' || $value===null) {$gap=true;continue;}
        if ($gap) return $invalid('No se pueden saltar duelos.');
        if (!in_array($value,['A','B','E'],true)) return $invalid('Valor invalido');
        $played[]=$value;
    }
    $active=array_values(array_filter($conditions,static fn($c)=>(int)($c['active']??1)!==0 && ($c['operator']??'')==='sequence_equals' && ($c['target_value']??'')!==''));
    usort($active,static fn($a,$b)=>[(int)($a['sort_order']??0),(int)($a['id']??0)]<=>[(int)($b['sort_order']??0),(int)($b['id']??0)]);
    $span=max(1,min(5,$bestOf));
    foreach($active as $c) $span=max($span,min(5,count(explode(',',(string)$c['target_value']))));
    if (!$played) return ['status'=>'PENDIENTE','winner'=>null,'score'=>'0-0','validation'=>'OK','rule'=>'Sin duelos cargados'];
    $a=0;$b=0;$result=[];$prefix=[];
    foreach($played as $i=>$value) {
        $prefix[]=$value;$a+=(int)($value==='A');$b+=(int)($value==='B');$exact=null;
        foreach($active as $condition) if($condition['target_value']===implode(',',$prefix)) {$exact=$condition;break;}
        if($exact!==null) {
            $winner=(string)($exact['result_value']??'');
            $state=(string)(($exact['status_value']??'')?:($winner===''?'REQUIERE D5':'FINALIZADA'));
            $result=['status'=>$state,'winner'=>in_array($winner,['A','B','E'],true)?$winner:null,'score'=>(string)(($exact['score_value']??'')?:'0-0'),'validation'=>$state==='INVALID'?(string)(($exact['failure_message']??'')?:'Secuencia invalida por regla'):'OK','rule'=>(string)($exact['condition_label']??'Regla configurada')];
        } else {
            $closed=$a>=intdiv($span,2)+1 || $b>=intdiv($span,2)+1 || count($prefix)>=5 || ($span===1 && count($prefix)===1);
            $result=['status'=>$closed?'FINALIZADA':'EN CURSO','winner'=>$closed?($a===$b?'E':($a>$b?'A':'B')):null,'score'=>"$a-$b",'validation'=>'OK','rule'=>'Umbral BO'.$span.' de la regla aplicada'];
        }
        if($i<count($played)-1 && in_array($result['status'],['FINALIZADA','INVALID'],true)) return array_replace($invalid('Duelos despues de cierre.'), ['score'=>'-']);
    }
    return $result;
}
