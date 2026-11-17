<?php
declare(strict_types=1);

/** Read-only knowledge projection. Never executes instructions from notes or changes sporting data. */
function ygo_library_content(): array {
    static $content=null;
    return $content??=json_decode((string)file_get_contents(__DIR__.'/library-content.json'),true,512,JSON_THROW_ON_ERROR);
}

function ygo_library_documents(array $data): array {
    $kb=ygo_library_content();$docs=$kb['articles'];$options=ygo_event_model_options();
    $labels=static fn(string $field,string $code):string=>(string)($options[$field][$code]??$code);
    $types=$data['competitionTypeOptions']??ygo_catalog_label_options(ygo_catalog_items_from_defaults('competition_type'));
    $types=array_map(static fn($value):string=>is_array($value)?(string)($value['label']??$value['name']??$value['code']??'Evento'):(string)$value,$types);
    foreach($kb['engines'] as $code=>$row){
        $cap=ygo_execution_capabilities()[$code]??[];
        $docs[]=['id'=>'system-'.$code,'category'=>'Sistemas competitivos','title'=>$labels('competition_system',$code),'summary'=>$row['summary'],'aliases'=>array_merge([$code],$row['aliases']??[]),
            'sections'=>[['title'=>'Cómo funciona','paragraphs'=>$row['details']],['title'=>'Antes de programar','paragraphs'=>[$cap['reason']??'','Elige participantes compatibles y completa los requisitos indicados en la ficha. La configuración guardada del evento prevalece sobre los valores iniciales de su plantilla.']]],
            'related'=>['guide-create-event','term-type-model-system'],'source'=>'Sistemas competitivos de Station X','status'=>($cap['status']??'')==='IMPLEMENTADO'?'Disponible':'Requiere definición'];
    }
    foreach($types as $code=>$name)$docs[]=['id'=>'type-'.$code,'category'=>'Tipos de evento','title'=>$name,'summary'=>$kb['types'][$code]??'Categoría con la que se organiza y consulta el evento.',
        'aliases'=>[$code,'tipo '.$name],'sections'=>[['title'=>'Qué define','paragraphs'=>[$kb['types'][$code]??'El tipo organiza el evento. El sistema competitivo determina sus rondas y cruces.']],['title'=>'Modelos disponibles','paragraphs'=>array_values(array_map(fn($t)=>$t['label'],array_filter($data['eventPromptTemplates']??ygo_event_available_templates(),fn($t)=>$t['type']===$code)))]],
        'related'=>['term-type-model-system','guide-create-event'],'source'=>'Catálogo actual de tipos de evento'];
    foreach($data['eventPromptTemplates']??ygo_event_available_templates() as $t){
        $engine=$t['execution_model'];$engineDoc=$kb['engines'][$engine]??null;
        $values=['Tipo: '.($types[$t['type']]??$t['type']),'Sistema: '.$labels('competition_system',$engine),'Participación: '.($t['match_format']?:'Elegir en la ficha'),'Formato inicial: '.ygo_library_format_label($t['ruleset']),'Mejor de: '.($t['best_of']??3),'Avance: '.$labels('advancement_rule',$t['advancement_rule']),'Empate: '.$labels('draw_policy',$t['draw_policy']),'Impacto en Ranking Global: '.$labels('ranking_impact',$t['ranking_impact']),'Construcción declarada: '.$labels('construction',$t['construction_code']),'Fases: '.$labels('phase_structure',$t['phase_structure']),'Origen de participantes: '.$labels('participant_source',$t['participant_source']),'Flujo: '.$labels('event_flow',$t['event_flow']),'Política de deck declarada: '.$labels('deck_policy',$t['deck_policy'])];
        if($engine==='free_for_all')$values[]='Dispone de Elo Battle Royale independiente; el impacto NONE se refiere al Ranking Global individual.';
        $requirements=array_values(array_unique(array_merge($t['required_inputs']??[],$t['activation_restrictions']??[])));
        $docs[]=['id'=>'model-'.$t['code'],'category'=>'Modelos preconfigurados','title'=>$t['label'],'summary'=>$t['purpose']?:'Plantilla que combina '.($types[$t['type']]??$t['type']).' con '.$labels('competition_system',$engine).'.',
            'aliases'=>[$t['code'],$t['base_template']??'', $t['match_format']],
            'sections'=>[['title'=>'Qué configura al elegirlo','paragraphs'=>$values],['title'=>'Cómo se juega','paragraphs'=>$engineDoc['details']??['Consulta el sistema y los requisitos de esta plantilla.']],['title'=>'Qué debes preparar','paragraphs'=>$requirements?:['Guardar el evento, elegir participantes compatibles y revisar fechas antes de programar.']],['title'=>'Alcance','paragraphs'=>['Son configuraciones internas de Station X. El nombre de una competición no certifica un reglamento oficial. No se valida el contenido de cartas, listas, pools ni elecciones de Draft.']]],
            'related'=>['system-'.$engine,'type-'.$t['type'],'term-type-model-system'],'source'=>'Modelo disponible en el formulario de creación','status'=>$requirements?'Necesita preparación':'Disponible'];
    }
    foreach($kb['choices']??[] as $field=>$choices)foreach($choices as $code=>$description)$docs[]=['id'=>'option-'.$field.'-'.strtolower($code),'category'=>'Opciones del formulario','title'=>$labels($field,$code),'summary'=>$description,'aliases'=>[$code,$kb['choice_labels'][$field]],'sections'=>[['title'=>$kb['choice_labels'][$field],'paragraphs'=>[$description,'Consulta también el sistema del evento: una opción declarada no sustituye los requisitos de programación, admisión ni cierre.']]],'source'=>'Opciones y contratos del formulario','related'=>['guide-create-event','guide-admission']];
    foreach(ygo_catalog_items_from_defaults('ruleset') as $format)$docs[]=['id'=>'format-'.$format['code'],'category'=>'Formatos','title'=>$format['label'],'summary'=>'Familia de formato utilizada para agrupar decks y mantener compatibles los participantes de un evento.','aliases'=>[$format['code']],'sections'=>[['title'=>'En esta plataforma','paragraphs'=>['Seleccionar '.$format['label'].' determina la familia compatible de participantes. Cada ranking se consulta por formato. No mezcles decks de familias diferentes en el mismo evento.','La Biblioteca no es una banlist ni un validador de legalidad de cartas. Consulta la versión de reglamento guardada en la ficha para conocer la referencia declarada.']]],'related'=>['guide-participants','term-format-division'],'source'=>'Catálogo actual de formatos'];
    $rules=array_column($data['gameRules']??[],null,'id');
    foreach($rules as $rule){
        $sections=[['title'=>'Descripción registrada','paragraphs'=>array_values(array_filter([$rule['effect']??'', $rule['description']??'', $rule['notes']??'']))],['title'=>'Configuración registrada','paragraphs'=>['Tipo: '.(['xp'=>'Experiencia','bonus'=>'Bonificación','penalty'=>'Sanción','tie_break'=>'Desempate','custom'=>'Personalizada'][$rule['rule_type']]??$rule['rule_type']),'Aplicación declarada: '.($rule['scope']??'Sin dato'),'Cuando ocurre: '.($rule['trigger_event']?:'Sin dato'),'Valor XP / puntos guardado: '.($rule['points_value']??'Sin dato'),'Prioridad: '.($rule['priority_order']??'Sin dato')]]];$conditions=[];
        foreach($data['gameRuleConditions']??[] as $c)if((int)$c['game_rule_id']===(int)$rule['id']){
            $sequence=($c['operator']??'')==='sequence_equals'?str_replace(',', ' → ',(string)($c['target_value']??'')):($c['operator']??'Condición');
            $conditions[]=($c['condition_label']??'Condición').' · '.$sequence.' · Resultado: '.($c['result_value']?:'Pendiente').' · Estado: '.($c['status_value']?:'Según regla').' · Marcador: '.($c['score_value']?:'Sin marcador').(!empty($c['failure_message'])?' · '.$c['failure_message']:'');
        }
        $sections[]=['title'=>'Condiciones activas registradas','paragraphs'=>$conditions?:['No hay condiciones activas registradas en esta consulta.']];
        $docs[]=['id'=>'rule-'.$rule['id'],'category'=>'Reglas registradas','title'=>($rule['name']?:$rule['code']).' · '.$rule['code'],'summary'=>'Consulta la descripción y las condiciones activas de esta regla de resultados. A y B identifican los lados del combate; E significa empate.','aliases'=>[$rule['code']],'sections'=>$sections,'source'=>'Reglas y condiciones registradas en la temporada consultada','related'=>['guide-results','term-duel-match'],'status'=>empty($rule['active'])?'Inactiva':'Activa','href'=>'#reglamento','link_label'=>'Consultar reglas'];
    }
    foreach($data['competitions']??[] as $c){
        $name=(string)$c['name'];
        $facts=['Tipo: '.($types[$c['type']]??$c['type']),'Estado: '.(ygo_catalog_label_options(ygo_catalog_items_from_defaults('competition_status'))[$c['status']]??$c['status']),'Formato: '.ygo_library_format_label($c['event_ruleset']??''),'Sistema guardado: '.$labels('competition_system',$c['execution_model']??'manual'),'Participación: '.($c['match_format']??'Sin dato'),'Inicio: '.($c['starts_on']?:'Sin fecha registrada'),'Cierre: '.($c['ends_on']?:'Sin fecha registrada')];
        $facts[]='Temporada: '.($c['season_name']??'Sin dato');$facts[]='Mejor de: '.($c['best_of']??'Sin dato');
        foreach(['advancement_rule'=>'Avance','draw_policy'=>'Empate','ranking_impact'=>'Impacto en Ranking Global','participant_source'=>'Origen de participantes','phase_structure'=>'Fases'] as $key=>$label)$facts[]=$label.': '.$labels($key,$c[$key]??'');
        if($c['type']==='league')$facts[]='Divisiones: '.($c['division_count']??count($c['division_ids']??[]));
        $registered=array_values(array_filter($data['allDecks']??[],fn($d)=>in_array((int)$d['id'],array_map('intval',$c['selected_deck_ids']??[]),true)));
        $docs[]=['id'=>'event-'.$c['id'],'category'=>'Eventos registrados','title'=>$name,'summary'=>($types[$c['type']]??$c['type']).' de '.ygo_library_format_label($c['event_ruleset']??'').'. Consulta su configuración guardada y abre su ficha.','aliases'=>[$c['code']??'',(string)($c['id']??'')],'sections'=>[['title'=>'Configuración guardada','paragraphs'=>$facts],['title'=>'Participantes registrados','paragraphs'=>array_column($registered,'name')?:['Sin participantes registrados en la consulta actual.']]],'source'=>'Datos del evento en la temporada consultada','related'=>array_values(array_filter(['system-'.($c['execution_model']??'manual'),!empty($c['prompt_template'])?'model-'.$c['prompt_template']:null])),'href'=>'?event_id='.(int)$c['id'].'#evento_ficha','link_label'=>'Abrir ficha del evento'];
    }
    foreach($data['allDecks']??[] as $d){
        $team=array_values(array_filter($data['teams']??[],fn($t)=>(int)$t['id']===(int)($d['team_id']??0)))[0]??[];
        $docs[]=['id'=>'deck-'.$d['id'],'category'=>'Decks y equipos','title'=>$d['name'],'summary'=>'Deck registrado'.(!empty($team['name'])?' del equipo '.$team['name']:'').'.','aliases'=>array_values(array_filter([$d['archetype']??'',$d['style']??'',$team['name']??''])),'sections'=>[['title'=>'Datos registrados','paragraphs'=>['Equipo: '.($team['name']??'Sin dato'),'Estilo o variante: '.($d['style']??'Sin dato'),'Formato: '.implode(', ',$d['format_codes']??[]),'Arquetipo: '.($d['archetype']??'Sin dato')]]],'related'=>['guide-participants'],'source'=>'Catálogo de decks de la consulta actual','href'=>'?deck_id='.(int)$d['id'].'#deck_ficha','link_label'=>'Abrir ficha del deck'];
    }
    foreach($data['teams']??[] as $t){
        $decks=array_values(array_filter($data['allDecks']??[],fn($d)=>(int)($d['team_id']??0)===(int)$t['id']));
        $docs[]=['id'=>'team-'.$t['id'],'category'=>'Decks y equipos','title'=>$t['name'],'summary'=>'Equipo registrado con '.count($decks).' decks en la consulta actual.','aliases'=>['equipo '.$t['name']],'sections'=>[['title'=>'Decks asociados','paragraphs'=>array_column($decks,'name')?:['Sin decks en la consulta actual.']]],'related'=>array_slice(array_map(fn($d)=>'deck-'.$d['id'],$decks),0,8),'source'=>'Catálogo actual de equipos','href'=>'?team_id='.(int)$t['id'].'#equipo_ficha','link_label'=>'Abrir ficha del equipo'];
    }
    $seen=[];foreach($docs as &$d){if(isset($seen[$d['id']]))throw new LogicException('Artículo duplicado: '.$d['id']);$seen[$d['id']]=true;$d['source']??='Guía de uso de Station X';$d['related']??=[];$d['aliases']??=[];}unset($d);
    return ['version'=>'STATION_X_LIBRARY_1.0','reviewed_on'=>'2026-10-18','categories'=>array_values(array_unique(array_column($docs,'category'))),'articles'=>$docs];
}

function ygo_library_format_label(string $code):string {
    return ygo_catalog_label_options(ygo_catalog_items_from_defaults('ruleset'))[$code]??($code?:'Sin formato registrado');
}
