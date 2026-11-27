<?php
declare(strict_types=1);

final class YgoMcpProtocol
{
    public const VERSION='1.1.0';
    public const PROTOCOLS=['2025-11-25','2025-06-18','2025-03-26','2024-11-05'];
    public static function tools(): array
    {
        $integer=['type'=>'integer','minimum'=>1,'maximum'=>2147483647];
        $offset=['type'=>'integer','minimum'=>0,'maximum'=>16000000];
        $text=['type'=>'string','minLength'=>1,'maxLength'=>160];
        $definitions=[
            ['search','Busca nombres de decks y títulos de combos propios. Devuelve IDs estables para fetch; no busca texto completo de guías.',['query'=>$text],['query']],
            ['fetch','Lee un resultado por ID: deck:665, guide:665:combos:0 o combo:UUID. Conserva datos y fuentes.',['id'=>['type'=>'string','minLength'=>1,'maxLength'=>150]],['id']],
            ['list_decks','Lista el catálogo real y si tiene Main cargado. Usa query para buscar y next_offset para continuar.',['query'=>['type'=>'string','maxLength'=>160],'offset'=>$offset],[]],
            ['get_deck','Lee cartas y cantidades reales del deck, Main/Extra/Side/reserva separados, y perfil. Consultar antes de sugerir combos.',['deck_id'=>$integer],['deck_id']],
            ['get_guide','Lee una sección documental publicada para un deck con cartas cargadas. Usa offset y next_offset hasta null para conservar toda la información. Contrasta la lista actual; no certifica simulación ni legalidad.',['deck_id'=>$integer,'section'=>['type'=>'string','enum'=>YgoMcpData::SECTIONS],'offset'=>$offset],['deck_id','section']],
            ['list_combos','Lista los combos y estrategias creados por el usuario y asignados al deck.',['deck_id'=>$integer,'offset'=>$offset],['deck_id']],
            ['list_leagues','Lista ligas activas, divisiones y decks inscritos; filtra opcionalmente por temporada. Usa next_offset.',['season_id'=>$integer,'offset'=>$offset],[]],
            ['list_matches','Consulta partidas registradas de ligas activas y fechas reales o nulas. Filtra liga, división, deck o temporada; usa next_offset.',['league_id'=>$integer,'division_id'=>$integer,'deck_id'=>$integer,'season_id'=>$integer,'offset'=>$offset],[]],
            ['get_deck_schedule','Consulta la liga y división actuales del deck y sus partidas registradas en ligas, con totales de programación.',['deck_id'=>$integer,'season_id'=>$integer,'offset'=>$offset],['deck_id']],
            ['get_combo','Lee un combo propio con pasos, conexiones, cartas de la instantánea y advertencia si cambió el inventario.',['combo_id'=>['type'=>'string','pattern'=>'^[A-Za-z0-9-]{16,64}$']],['combo_id']],
        ];
        $security=[['type'=>'oauth2','scopes'=>[YgoMcpAuth::SCOPE]]];$tools=[];
        foreach($definitions as [$name,$description,$properties,$required])$tools[]=['name'=>$name,'description'=>$description,'inputSchema'=>['type'=>'object','properties'=>$properties?:new stdClass(),'required'=>$required,'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>true,'destructiveHint'=>false,'idempotentHint'=>true,'openWorldHint'=>false],'securitySchemes'=>$security,'_meta'=>['securitySchemes'=>$security]];
        return $tools;
    }

    public static function arguments(string $name,mixed $args): array
    {
        $definition=null;foreach(self::tools() as $tool)if($tool['name']===$name)$definition=$tool;
        if(!$definition || !is_array($args) || (array_is_list($args)&&$args!==[]))throw new InvalidArgumentException('Herramienta o argumentos no válidos.');
        $schema=$definition['inputSchema'];foreach($schema['required'] as $key)if(!array_key_exists($key,$args))throw new InvalidArgumentException('Falta el campo '.$key);
        foreach($args as $key=>$value){
            $spec=$schema['properties'][$key]??null;if(!$spec)throw new InvalidArgumentException('Campo desconocido.');
            if($spec['type']==='integer'&&(!is_int($value)||$value<($spec['minimum']??0)||$value>($spec['maximum']??PHP_INT_MAX)))throw new InvalidArgumentException('Entero fuera de rango.');
            if($spec['type']==='string'&&(!is_string($value)||mb_strlen($value)<($spec['minLength']??0)||mb_strlen($value)>($spec['maxLength']??1000)||(isset($spec['enum'])&&!in_array($value,$spec['enum'],true))||(isset($spec['pattern'])&&!preg_match('~'.$spec['pattern'].'~D',$value))))throw new InvalidArgumentException('Texto no válido.');
        }return $args;
    }

    public static function call(YgoMcpData $data,string $name,array $a): array
    {
        return match($name){
            'search'=>$data->search($a['query']), 'fetch'=>$data->fetch($a['id']),
            'list_decks'=>$data->decks($a['query']??'',$a['offset']??0), 'get_deck'=>$data->deck($a['deck_id']),
            'get_guide'=>$data->guide($a['deck_id'],$a['section'],$a['offset']??0),
            'list_combos'=>$data->combos($a['deck_id'],$a['offset']??0), 'get_combo'=>$data->combo($a['combo_id']),
            'list_leagues'=>$data->leagues($a['season_id']??null,$a['offset']??0),
            'list_matches'=>$data->matches($a['league_id']??null,$a['division_id']??null,$a['deck_id']??null,$a['season_id']??null,$a['offset']??0),
            'get_deck_schedule'=>$data->deckSchedule($a['deck_id'],$a['season_id']??null,$a['offset']??0),
            default=>throw new InvalidArgumentException('Herramienta desconocida.')};
    }
}
