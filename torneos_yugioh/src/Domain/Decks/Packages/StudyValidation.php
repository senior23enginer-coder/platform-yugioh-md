<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Decks\Packages;

use InvalidArgumentException;

final class StudyValidation
{
    public static function presentationQuality(array $record): array
    {
        $routes=is_array($record['routes']??null)?$record['routes']:[];
        $steps=0;$illustrated=0;$fullBoards=0;$partialBoards=0;$illustrativeBoards=0;
        foreach($routes as $route) {
            if(!is_array($route))continue;
            foreach($route['steps']??[] as $step) {
                $steps++;
                if(!empty($step['photos'])&&is_array($step['photos']))$illustrated++;
            }
            if(!empty($route['endboard']['variants'])) {
                // A diagram based on documented zones can be useful without claiming
                // that an exact spatial board (columns/material placement) was verified.
                if(($route['endboard']['spatial_verified']??null)===false
                    ||array_filter($route['endboard']['variants'],static fn($v)=>($v['spatial_verified']??null)===false)) {
                    $illustrativeBoards++;
                }else $fullBoards++;
            }elseif(!empty($route['board']))$partialBoards++;
        }
        $style=is_array($record['profile']??null)?(string)($record['profile']['Estilo breve']??''):'';
        $intro=is_array($record['profile']??null)?(string)($record['profile']['Resumen para ficha']??''):'';
        $ready=trim($style)!==''&&trim($intro)!==''&&$steps>0&&$illustrated===$steps
            &&$fullBoards===count($routes);
        return ['routes'=>count($routes),'steps'=>$steps,'illustrated_steps'=>$illustrated,
            'full_endboards'=>$fullBoards,'illustrative_endboards'=>$illustrativeBoards,'partial_endboards'=>$partialBoards,
            'profile_summary'=>trim($style)!==''&&trim($intro)!=='',
            'visual_complete'=>$ready];
    }

    public static function validateA01Profile(array $record): void
    {
        if(!array_key_exists('a01_profile_contract',$record))return;
        if(($record['a01_profile_contract']??null)!=='station-x-a01-profile/1')
            throw new InvalidArgumentException('Contrato A01 del perfil desconocido.');
        $profile=$record['profile']??null;
        if(!is_array($profile))throw new InvalidArgumentException('Falta el perfil A01 de seis campos.');
        foreach(['Arquetipo','Tipo de estrategia','Estilo breve','Resumen para ficha','Cómo se juega','Qué vigilar'] as $field){
            $value=$profile[$field]??null;
            if(!is_string($value)||trim($value)===''||$value!==trim($value)
               ||preg_match('/[<>]/u',$value)||preg_match('/\\s{3,}/u',$value)
               ||preg_match('/cat[aá]logo completo|consultar el plan|^pendiente\\b|^sin datos\\b|dictamen editorial|ganas siempre/iu',$value))
                throw new InvalidArgumentException('Campo A01 inválido o incompleto: '.$field);
            $length=mb_strlen($value,'UTF-8');
            if(($field==='Estilo breve'&&$length>190)||($field==='Resumen para ficha'&&$length>250))
                throw new InvalidArgumentException('Campo A01 excede su longitud: '.$field);
        }
        if(preg_match('/estudio individual|iteraci[oó]n|versi[oó]n\\s*[v0-9]|dictamen|\\d{4}-\\d{2}-\\d{2}/iu',$profile['Arquetipo']))
            throw new InvalidArgumentException('El arquetipo A01 contiene auditoría, versión o fecha.');
        if(mb_strlen($profile['Cómo se juega'],'UTF-8')<60||mb_strlen($profile['Qué vigilar'],'UTF-8')<15)
            throw new InvalidArgumentException('Falta el plan completo o los riesgos estratégicos A01.');
    }

    public static function validatePlayerGuide(array $record): void
    {
        if(!array_key_exists('player_guide',$record))return; // historical packages remain readable, not newly certified
        $guide=$record['player_guide'];
        if(!is_array($guide)||($guide['schema']??null)!=='station-x-player-guide/1')
            throw new InvalidArgumentException('Contrato de guía del jugador desconocido.');
        $profile=$record['profile']??[];
        $audit='/\bCID\s*\d+\b|\b[CV]\d{2}\b|\bA(?:0[1-9]|10[AB]?)\s*[-–]?\s*T?\d*\b|\beffect_id\b|\broute_id\b|PARCIAL_AVANZADO|dictamen\s+editorial|PENDIENTE_REPLAY|cat[aá]logo completo|consultar el plan|estudio individual|iteraci[oó]n\s*\d+|cobertura\s+documental|validaci[oó]n\s+observada|evaluaci[oó]n\s+editorial|no\s+(?:escribir|redactar|inferir|inventar)\b|informe\s+de\s+auditor[ií]a|\b\d+\s+de\s+\d+\s+decks\b/iu';
        $check=static function($value,string $field,int $min)use($audit):void {
            if(!is_string($value)||trim($value)!==$value||mb_strlen($value,'UTF-8')<$min||preg_match($audit,$value))
                throw new InvalidArgumentException('Texto de guía incompleto o técnico: '.$field);
        };
        foreach(['Arquetipo','Tipo de estrategia','Estilo breve','Resumen para ficha','Cómo se juega','Qué vigilar'] as $field)
            $check($profile[$field]??null,$field,$field==='Cómo se juega'?400:($field==='Qué vigilar'?15:8));
        $byCid=[];foreach($record['cards']??[] as $card)$byCid[(int)($card['cid']??0)]=$card;
        $byRoute=[];foreach($record['routes']??[] as $route)$byRoute[$route['id']??'']=true;
        $featured=$guide['featured_cards']??null;
        if(!is_array($featured)||!array_is_list($featured)||count($featured)<1||count($featured)>4)
            throw new InvalidArgumentException('La guía requiere de una a cuatro cartas clave justificadas.');
        $seen=[];
        foreach($featured as $item){
            $cid=$item['cid']??null;
            if(!is_int($cid)||!isset($byCid[$cid])||isset($seen[$cid]))
                throw new InvalidArgumentException('Carta destacada inexistente o repetida.');
            $seen[$cid]=true;
            $allowed=false;
            foreach($byCid[$cid]['slots']??[] as $slot)if(in_array($slot['section']??null,['main','extra'],true))$allowed=true;
            if(!$allowed)throw new InvalidArgumentException('Las cartas destacadas requieren copia en Main o Extra.');
            foreach(['role'=>15,'reason'=>35,'when_to_use'=>30] as $name=>$min)$check($item[$name]??null,$name,$min);
            if(!is_array($item['related_cids']??null))throw new InvalidArgumentException('Faltan vínculos de carta destacada.');
            foreach($item['related_cids'] as $related)if(!is_int($related)||!isset($byCid[$related]))
                throw new InvalidArgumentException('Vínculo de carta inexistente.');
        }
        $phases=$guide['phases']??null;
        if(!is_array($phases)||!array_is_list($phases)||count($phases)!==4)
            throw new InvalidArgumentException('La guía requiere cuatro planes por fase.');
        $ids=[];
        foreach($phases as $phase){
            $id=$phase['id']??null;
            if(!in_array($id,['first','second','mid','late'],true)||isset($ids[$id]))
                throw new InvalidArgumentException('Fase duplicada o desconocida.');$ids[$id]=true;
            foreach(['title'=>6,'plan'=>100,'preconditions'=>35,'risks'=>35] as $name=>$min)$check($phase[$name]??null,$name,$min);
            if(!is_array($phase['related_route_ids']??null))throw new InvalidArgumentException('La fase no declaró sus referencias.');
            foreach($phase['related_route_ids'] as $rid)if(!is_string($rid)||!isset($byRoute[$rid]))
                throw new InvalidArgumentException('Referencia a jugada inexistente.');
        }
        // Optional on historical imports, mandatory for newly reviewed A01 closure.
        // This checks structure and references, not semantic quality or legal replay.
        if(array_key_exists('strategy',$guide)){
            $strategy=$guide['strategy'];
            $expected=['first','second','mid','late','weak_hand','disruption','win_condition','common_errors'];
            if(!is_array($strategy)||($strategy['schema']??null)!=='station-x-player-strategy/1'||!is_array($strategy['sections']??null)||!array_is_list($strategy['sections'])||array_column($strategy['sections'],'id')!==$expected)
                throw new InvalidArgumentException('El plan de juego requiere ocho lecciones en el orden definido.');
            $objectives=[];$actionLists=[];
            foreach($strategy['sections'] as $section){
                foreach(['title','objective','situation','expected_result','resources_to_keep','alternative'] as $key)$check($section[$key]??null,$section['id'].'_'.$key,$key==='title'?6:20);
                if(isset($objectives[$section['objective']]))throw new InvalidArgumentException('Objetivo de lección duplicado.');
                $objectives[$section['objective']]=true;
                $refs=$section['card_cids']??null;
                if(!is_array($refs)||!array_is_list($refs)||!$refs||count($refs)!==count(array_unique($refs,SORT_REGULAR)))throw new InvalidArgumentException('Referencias de cartas incompletas o duplicadas.');
                foreach($refs as $cid){
                    if(!is_int($cid)||!isset($byCid[$cid])||!array_filter($byCid[$cid]['slots']??[],static fn($slot)=>in_array($slot['section']??null,['main','extra'],true)))throw new InvalidArgumentException('Carta no disponible en el plan.');
                }
                $actions=$section['actions']??null;
                if(!is_array($actions)||!array_is_list($actions)||count($actions)<2||count($actions)>7)throw new InvalidArgumentException('La lección requiere acciones concretas.');
                foreach($actions as $action)$check($action,$section['id'].'_action',15);
                if(count($actions)!==count(array_unique($actions)))throw new InvalidArgumentException('Acción duplicada.');
                $signature=implode('|',$actions);
                if(isset($actionLists[$signature]))throw new InvalidArgumentException('Lecciones con las mismas acciones.');
                $actionLists[$signature]=true;
                $routeRefs=$section['related_route_ids']??null;
                if(!is_array($routeRefs)||!array_is_list($routeRefs))throw new InvalidArgumentException('Referencias de rutas ausentes.');
                foreach($routeRefs as $rid)if(!is_string($rid)||!isset($byRoute[$rid]))throw new InvalidArgumentException('Ruta no existente en el plan.');
                if(count($routeRefs)!==count(array_unique($routeRefs)))throw new InvalidArgumentException('Referencia de ruta duplicada.');
            }
        }

    }

    public static function validate(array $record,array $inventory): void
    {
        $id=(int)$inventory['deck']['id'];
        if(($record['schema']??'')!=='station-x-curated-v62'||(int)($record['deck_id']??0)!==$id)throw new InvalidArgumentException('El contenido no corresponde al deck de esta ficha.');
        ygo_data_validate_a01_profile($record);
        StudyValidation::validatePlayerGuide($record);
        if(isset($record['calculator']))CalculatorContract::extract("```json\n".PackageFiles::json($record['calculator'])."\n```",$record,$id);
        // New GPT packages may certify visual completeness. False certification is
        // rejected; older documentary packages remain importable with an explicit warning.
        if(isset($record['presentation_contract'])&&!in_array($record['presentation_contract'],['station-x-visual-v1'],true))
            throw new InvalidArgumentException('Contrato visual desconocido.');
        if(($record['presentation_contract']??'')==='station-x-visual-v1') {
            $quality=StudyValidation::presentationQuality($record);
            if(!$quality['visual_complete'])throw new InvalidArgumentException(
                'La entrega declara guía visual completa, pero faltan fotografías por paso, campos finales estructurados o resumen de estilo.'
            );
            foreach($record['routes'] as $route) {
                foreach($route['steps'] as $step) {
                    if(!is_string($step['action']??null)||trim($step['action'])===''||!is_string($step['detail']??null)||trim($step['detail'])==='')
                        throw new InvalidArgumentException('Una guía visual certificada no puede omitir su acción o detalle de paso.');
                    foreach($step['photos'] as $photo)if(!is_array($photo)||empty($photo['name'])||empty($photo['image']))
                        throw new InvalidArgumentException('Fotografía incompleta en guía visual.');
                }
                foreach($route['endboard']['variants'] as $variant) {
                    if(!is_array($variant)||!in_array((int)($variant['columns']??0),[3,5],true)
                       ||!is_string($variant['layout_note']??null)||trim($variant['layout_note'])===''
                       ||!is_array($variant['monster']??null)||!is_array($variant['spell']??null)||!is_array($variant['field']??null))
                        throw new InvalidArgumentException('Campo final sin zonas documentadas.');
                    $onBoard=array_merge($variant['monster'],$variant['spell'],$variant['field']);
                    if(!$onBoard&&empty($variant['empty_board_reason']))
                        throw new InvalidArgumentException('Campo final vacío sin justificación documental.');
                    foreach($onBoard as $card)if(!is_array($card)||!is_string($card['name']??null)||trim($card['name'])===''||!is_string($card['image']??null)||trim($card['image'])==='')
                        throw new InvalidArgumentException('Carta del campo final sin identidad o fotografía.');
                }
            }
        }
        foreach(['cards','routes','skills','profile','engine_maps','decisions','practice'] as $key)if(!isset($record[$key])||!is_array($record[$key]))throw new InvalidArgumentException('Falta la sección '.$key.'.');
        if(count($record['cards'])>300||count($record['routes'])>500)throw new InvalidArgumentException('Demasiadas cartas o rutas.');
        $native=[];foreach($inventory['cards'] as $c)$native[(int)$c['id']]=$c;
        $ids=[];$usedSlots=[];foreach($record['cards'] as $card){
            if(!is_string($card['id']??null)||!preg_match('/^[\w:.-]+$/D',$card['id'])||isset($ids[$card['id']])||!is_string($card['name']??null)||!is_array($card['fields']??null)||!is_array($card['slots']??null))throw new InvalidArgumentException('Ficha de carta incompleta o duplicada.');$ids[$card['id']]=true;
            foreach(['sources','synergy'] as $key)if(!is_array($card[$key]??null))throw new InvalidArgumentException('Falta '.$key.' en la carta '.$card['name']);
            if(!is_array($card['synergy']['labels']??null))throw new InvalidArgumentException('Faltan las funciones de la carta.');
            // Accesos documentales del Extra: se transportan por CID sin inflar record.routes ni simular combos.
            if(isset($card['extra_access'])){
                if(!is_array($card['extra_access'])||count($card['extra_access'])>20||!array_filter($card['slots'],static fn($slot)=>($slot['section']??'')==='extra'))
                    throw new InvalidArgumentException('Accesos Extra fuera de su carta o en formato inválido.');
                $accessIds=[];
                foreach($card['extra_access'] as $access){
                    if(!is_array($access)||(int)($access['cid']??0)!==(int)($card['cid']??0)
                        ||!is_string($access['id']??null)||!preg_match('/^[A-Z0-9:_.-]+$/D',$access['id'])||isset($accessIds[$access['id']])
                        ||!in_array($access['status']??null,['DOCUMENTADA_CONDICIONAL_SIN_REPLAY','TEORICA_PENDIENTE_VALIDACION'],true)
                        ||!is_array($access['steps']??null)||count($access['steps'])<1||count($access['steps'])>30)
                        throw new InvalidArgumentException('Acceso Extra incompleto, duplicado o sin estado documental.');
                    foreach(['opening','materials','result','limitation'] as $key)
                        if(!is_string($access[$key]??null)||trim($access[$key])==='')
                            throw new InvalidArgumentException('Faltan requisitos o resultados del acceso Extra '.$access['id']);
                    foreach($access['steps'] as $step)
                        if(!is_string($step)||trim($step)==='')throw new InvalidArgumentException('Paso vacío en el acceso Extra '.$access['id']);
                    $accessIds[$access['id']]=true;
                }
            }
            foreach($card['fields'] as $f)if(!is_string($f['title']??null)||!is_string($f['text']??null))throw new InvalidArgumentException('Explicación de carta incompleta.');
            foreach($card['slots'] as $s){$c=$native[(int)($s['card_id']??0)]??null;
                if(!$c||$c['section']!==($s['section']??'')||(int)$c['quantity']!==(int)($s['quantity']??-1))throw new InvalidArgumentException('El inventario del ZIP difiere del guardado. Actualiza primero la decklist y vuelve a exportar.');
                if(isset($usedSlots[$c['id']]))throw new InvalidArgumentException('Una carta guardada aparece duplicada en las fichas.');$usedSlots[$c['id']]=true;
            }
        }
        $ids=[];foreach($record['routes'] as $r){
            if(!is_string($r['id']??null)||!preg_match('~^[\pL\pN_:./—–-]+$~uD',$r['id'])||!str_starts_with($r['id'],$id.':')||isset($ids[$r['id']])||!is_string($r['title']??null)||mb_strlen($r['title'])>160||!is_array($r['steps']??null))throw new InvalidArgumentException('Jugada incompleta, duplicada o de otro deck.');$ids[$r['id']]=true;
            $previous=0;foreach($r['steps'] as $s){if((int)($s['number']??0)<=$previous||!is_string($s['action']??null)||trim($s['action'])==='')throw new InvalidArgumentException('Pasos incompletos en '.$r['id']);$previous=(int)$s['number'];}
        }
        foreach($record['decisions'] as $d)if(!is_string($d['id']??null)||!preg_match('/^[\w:.-]+$/D',$d['id'])||!is_array($d['options']??null)||!is_array($d['routes']??null))throw new InvalidArgumentException('Decisión incompleta.');
        foreach($record['practice'] as $p)if(!is_string($p['id']??null)||!preg_match('/^[\w:.-]+$/D',$p['id'])||!is_array($p['routes']??null))throw new InvalidArgumentException('Ejercicio incompleto.');
        // All downstream renderers receive plain editorial strings, never executable schemes or HTML.
        array_walk_recursive($record,static function($value){if(is_string($value)&&(preg_match('~(?:javascript|vbscript|data|file):~i',$value)||preg_match('~<\s*/?\s*(script|iframe|object|embed|svg|img|style|link)\b~i',$value)))throw new InvalidArgumentException('El contenido contiene HTML o enlaces ejecutables no admitidos.');});
    }
}
