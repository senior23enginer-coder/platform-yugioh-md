<?php
declare(strict_types=1);

function ygo_catalog_contracts(): array {
    static $rows=null;return $rows??=json_decode((string)file_get_contents(__DIR__.'/event_catalog_contracts.json'),true,512,JSON_THROW_ON_ERROR);
}
function ygo_template_base_code(string $code): string {
    return ygo_catalog_contracts()[$code]['base']??$code;
}
/** Keep the original91 unchanged in identity; extended names are explicit local recipes. */
function ygo_event_available_templates(): array {
    $original=ygo_event_prompt_templates();$byCode=array_column($original,null,'code');$result=$original;
    $ffa=$byCode['all_star_event'];
    $ffa=array_replace($ffa,['code'=>'battle_royale','label'=>'Battle Royale / Free-for-All (3-4 jugadores)','type'=>'special','execution_model'=>'free_for_all','system'=>'Supervivencia multijugador','match_format'=>'ffa','participants_per_side'=>1,'participation_code'=>'FREE_FOR_ALL','participation'=>'3 o 4 jugadores independientes','best_of'=>1,'ranking_impact'=>'NONE','format'=>'Supervivencia | 3-4 jugadores independientes','draw_policy'=>'NOT_ALLOWED','phase_structure'=>'NONE','advancement_rule'=>'WINNER_ONLY','ruleset'=>'tcg_advanced','schedule'=>'manual','purpose'=>'Turnos por jugador; los eliminados no vuelven a jugar. Gana el ultimo superviviente.','difference'=>'Una mesa con 3 o 4 jugadores independientes; no son equipos ni varios duelos1v1.','required_inputs'=>['Inscribir participantes y crear mesas de3 o4 desde la ficha.'],'activation_restrictions'=>[],'operational_status'=>'IMPLEMENTADO','notes'=>'LP y fases individuales. Eliminacion por LP0, rendicion, deck agotado o efecto confirmado. Resultado propio, sin Elo binario.']);
    $result[]=$ffa;$byCode['battle_royale']=$ffa;
    foreach(ygo_catalog_contracts() as $code=>$contract){
        $t=array_replace($byCode[$contract['base']],$contract['overrides']??[]);
        $t['code']=$code;$t['label']=$contract['name'];$t['base_template']=$contract['base'];$t['catalog_original_id']=$contract['id'];
        $t['purpose']=$contract['description'];$t['difference']=$contract['difference'];
        $t['notes']='Reglamento interno Station X. '.$contract['description'].' '.$contract['difference'];
        $t['operational_status']='IMPLEMENTADO';$t['activation_restrictions']=ygo_model_diagnostics($t);if($t['activation_restrictions'])$t['operational_status']='REQUIERE_CONFIGURACION';
        $result[]=$t;
    }return $result;
}
