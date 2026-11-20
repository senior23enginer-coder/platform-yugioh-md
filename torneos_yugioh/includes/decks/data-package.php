<?php
declare(strict_types=1);

/** Deck-only editorial packages. No SQL writes, archive extraction or executable uploads. */
function ygo_data_json(mixed $value): string { return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR); }
function ygo_data_read(string $path): array {
    $v=json_decode((string)file_get_contents($path),true,128,JSON_THROW_ON_ERROR);
    if(!is_array($v))throw new InvalidArgumentException('Documento JSON inválido.');return $v;
}
function ygo_data_path(string $name): string {
    $name=str_replace('\\','/',$name);
    if($name===''||strlen($name)>240||str_starts_with($name,'/')||preg_match('~(^|/)\.\.?(/|$)|[:\x00-\x1f]~',$name))throw new InvalidArgumentException('El ZIP contiene una ruta no permitida.');
    return $name;
}
function ygo_data_local(string $app,string $url): ?string {
    $url=preg_replace('~^\./~','',$url);
    if(!str_starts_with($url,'assets/editorial/')&&!str_starts_with($url,'assets/cards/'))return null;
    ygo_data_path($url);$path=realpath($app.'/'.$url);$base=realpath($app.'/assets');
    return $path&&$base&&str_starts_with(str_replace('\\','/',$path),str_replace('\\','/',$base).'/')?$path:null;
}
function ygo_data_source_image(array $files,string $value): ?string {
    $value=preg_replace('~^\./~','',$value);$matches=[];
    foreach($files as $path=>$bytes)if($path===$value||str_ends_with($path,'/'.$value))$matches[]=$path;
    return count($matches)===1?$matches[0]:null;
}
function ygo_data_archive(string $path): array {
    if(!class_exists('ZipArchive'))throw new RuntimeException('El servidor necesita la extensión PHP ZIP para esta herramienta.');
    $zip=new ZipArchive();if($zip->open($path)!==true)throw new InvalidArgumentException('No se pudo abrir el ZIP.');
    try {
        if($zip->numFiles>3000)throw new InvalidArgumentException('El ZIP supera los 3000 archivos.');
        $files=[];$total=0;
        for($i=0;$i<$zip->numFiles;$i++){
            $s=$zip->statIndex($i);$name=ygo_data_path($s['name']);if(str_ends_with($name,'/'))continue;
            $total+=(int)$s['size'];
            if($total>120*1024*1024||$s['size']>24*1024*1024||($s['size']>1024*1024&&$s['size']/max(1,$s['comp_size'])>300))throw new InvalidArgumentException('El ZIP supera el límite de contenido descomprimido.');
            if(isset($files[$name])||!preg_match('~\.(md|txt|json|png|jpe?g|webp|gif|html|svg)$~i',$name))throw new InvalidArgumentException('Archivo duplicado o tipo no admitido: '.$name);
            $opsys=0;$attrs=0;$zip->getExternalAttributesIndex($i,$opsys,$attrs);
            if(($attrs>>16&0170000)===0120000)throw new InvalidArgumentException('No se admiten enlaces simbólicos.');
            $bytes=$zip->getFromIndex($i);if($bytes===false||strlen($bytes)!==(int)$s['size']||sprintf('%u',crc32($bytes))!==sprintf('%u',$s['crc']))throw new InvalidArgumentException('Archivo ZIP dañado.');
            $files[$name]=$bytes;
        }return $files;
    }finally{$zip->close();}
}
function ygo_data_sections(string $raw): array {
    preg_match_all('/^(#{2,4}) (.*?)\R(.*?)(?=^#{1,4} |\z)/msu',$raw,$m,PREG_SET_ORDER);return $m;
}
function ygo_data_plain(string $s): string { return trim(str_replace(['**','`'],'',$s)); }
function ygo_data_photos(string $text,array $cards): array {
    $photos=[];foreach($cards as $c){
        $names=array_unique(array_merge([$c['name']],$c['source_aliases']??[]));$found=false;
        foreach($names as $n)if(mb_strlen($n)>3&&preg_match('~(?<![\pL\pN])'.preg_quote($n,'~').'(?![\pL\pN])~iu',$text))$found=true;
        if(!empty($c['cid'])&&preg_match('~(?:cid=|CID\s*|#)'.(int)$c['cid'].'\b~i',$text))$found=true;
        if($found)$photos[]=array_intersect_key($c,array_flip(['cid','name','image','full_image']))+['label'=>'Carta mencionada en el paso'];
    }return $photos;
}
/** Preserve one explicit simulator contract; prose never becomes an executable effect. */
function ygo_data_calculator(string $raw,array $data,int $deckId): array {
    preg_match_all('~```json\s*\R(.*?)\R```~s',$raw,$blocks,PREG_SET_ORDER);$found=false;
    foreach($blocks as $block){
        $value=json_decode($block[1],true);
        if(json_last_error()!==JSON_ERROR_NONE&&str_contains($block[1],'station-x-calculator/1'))throw new InvalidArgumentException('JSON de calculadora dañado; no se descartará silenciosamente.');
        if(!is_array($value)||($value['schema']??'')!=='station-x-calculator/1')continue;
        if($found||strlen($block[1])>131072||(int)($value['deck_id']??0)!==$deckId||($value['format']??'')!==($data['format']??'')||!is_array($value['rules']??null)||count($value['rules'])>100)throw new InvalidArgumentException('Contrato de calculadora duplicado, de otro deck o incompleto.');
        $found=true;$ids=[];$cids=array_column($data['cards']??[],'cid');
        foreach($value['rules'] as $rule){
            if(!is_array($rule)||($rule['version']??0)!==1||($rule['deckId']??0)!==$deckId||($rule['format']??'')!==$value['format']||!is_string($rule['id']??null)||isset($ids[$rule['id']])||!in_array($rule['actor']['cid']??null,$cids,true))throw new InvalidArgumentException('Regla de calculadora de otro deck o carta no identificada.');
            $ids[$rule['id']]=true;
            foreach(['guards','select','costs','resolve','tags','assumptions'] as $field)if(!is_array($rule[$field]??null))throw new InvalidArgumentException('Regla sin '.$field);
        }
        $data['calculator']=$value;
    }
    return $data;
}
/** Keep simulator metadata in the package, outside player-facing explanations. */
function ygo_data_without_calculator(string $raw): string {
    return preg_replace_callback('~```json\s*\R(.*?)\R```~s',static function(array $m):string{
        $v=json_decode($m[1],true);return is_array($v)&&($v['schema']??'')==='station-x-calculator/1'?'':$m[0];
    },$raw);
}
function ygo_data_markdown(string $raw,array $base,int $deckId): array {
    $data=ygo_data_calculator($raw,$base,$deckId);$raw=ygo_data_without_calculator($raw);$by=[];foreach($data['routes']??[] as $r)$by[$r['id']]=$r;
    $parsed=0;
    foreach(ygo_data_sections($raw) as $part){
        [,,$title,$body]=$part;$rid=null;
        if(preg_match('/`('.$deckId.':A09:[^`]+)`/u',$title,$m))$rid=$m[1];
        elseif(preg_match('/^(?:MEMD-)?((?:[PSG]-)?[CV]\d{2})\b/u',$title,$m))$rid=$deckId.':A09:'.$m[1];
        if($rid&&preg_match('/^1\.\s/m',$body)){
            preg_match_all('/^(\d+)\.\s+(.*?)(?=^\d+\.\s|^#{1,4} |\n\n|\z)/msu',$body,$matches,PREG_SET_ORDER);
            $steps=[];foreach($matches as $m){if((int)$m[1]!==count($steps)+1)throw new InvalidArgumentException('Numeración incompleta en '.$rid);$text=ygo_data_plain($m[2]);$steps[]=['number'=>(int)$m[1],'action'=>$text,'detail'=>$text,'photos'=>ygo_data_photos($text,$data['cards']??[]),'requirements'=>[],'resources'=>[],'board'=>[]];}
            $r=$by[$rid]??['id'=>$rid,'name'=>ygo_data_plain($title),'title'=>ygo_data_plain($title),'family'=>'Desarrollo de la jugada','purpose'=>'','category'=>'APERTURA'];
            $old=$r['steps']??[];foreach($steps as $i=>&$step)if(($old[$i]['action']??null)===$step['action'])$step=$old[$i];unset($step);
            if(array_column($old,'action')!==array_column($steps,'action')){
                unset($r['endboard']);$r['board']=[];$r['resources']=[];$r['result']='Estado final pendiente de especificar en la secuencia actualizada.';
            }
            preg_match('/^1\.\s/m',$body,$first,PREG_OFFSET_CAPTURE);$opening=ygo_data_plain(substr($body,0,$first[0][1]));
            $r['steps']=$steps;$r['opening']=$opening;$r['initial_details']=$opening;$r['hand_photos']=ygo_data_photos($opening,$data['cards']??[]);
            $r['technical']=ygo_data_plain($body);$r['documentary_source']=$body;$r['editorial_status']='pending';
            $r['notice']='Secuencia documental importada; no certificada mediante simulación.';
            foreach(['native_editor_document','native_editor_cards','native_editor_revision'] as $key)unset($r[$key]);
            if(preg_match('/^- \*\*(?:Objetivo|Propósito)[^\n]*?\*\*[: ]*(.+)$/mu',$body,$m))$r['purpose']=ygo_data_plain($m[1]);
            if(preg_match('/\*\*(?:Campo final|Tablero final|Final|Resultado|Salida)[^\n]*?(?:\R.*?)(?=\n\n|\z)/su',$body,$m)||preg_match('/\*\*(?:Campo final|Tablero final|Final|Resultado|Salida)[^\n]*/u',$body,$m)){
                $result=ygo_data_plain($m[0]);if($result!==($r['result']??'')){unset($r['endboard']);$r['board']=[];$r['resources']=[];}$r['result']=$result;
            }
            $r+=['result'=>'Estado final no especificado.','board'=>[],'resources'=>[]];$by[$rid]=$r;$parsed++;
        }
        if(preg_match('/^(?:\d+\.\s+)(.+)$/u',$title,$m)){
            foreach($data['cards']??[] as $i=>$card)if(mb_strtolower(trim($m[1]))===mb_strtolower($card['name'])){
                preg_match_all('/^- \*\*(.*?)\*\*[:：]?\s*(.*?)(?=^- \*\*|\n\n|\z)/msu',$body,$fs,PREG_SET_ORDER);
                foreach($fs as $field){$label=rtrim(ygo_data_plain($field[1]),':');$text=ygo_data_plain($field[2]);$found=false;
                    foreach($data['cards'][$i]['fields'] as &$f)if($f['title']===$label){$f['text']=$text;$found=true;}unset($f);
                    if(!$found)$data['cards'][$i]['fields'][]=['title'=>$label,'text'=>$text];
                }
            }
        }
    }
    if(!$parsed&&empty($base['routes']))$data['routes']=[];else $data['routes']=array_values($by);
    $data['imported_source_text']=$raw;return $data;
}
/** Presentation readiness is separate from legal rulings and simulator coverage. */
function ygo_data_presentation_quality(array $record): array {
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

function ygo_data_validate(array $record,array $inventory): void {
    $id=(int)$inventory['deck']['id'];
    if(($record['schema']??'')!=='station-x-curated-v62'||(int)($record['deck_id']??0)!==$id)throw new InvalidArgumentException('El contenido no corresponde al deck de esta ficha.');
    if(isset($record['calculator']))ygo_data_calculator("```json\n".ygo_data_json($record['calculator'])."\n```",$record,$id);
    // New GPT packages may certify visual completeness. False certification is
    // rejected; older documentary packages remain importable with an explicit warning.
    if(isset($record['presentation_contract'])&&!in_array($record['presentation_contract'],['station-x-visual-v1'],true))
        throw new InvalidArgumentException('Contrato visual desconocido.');
    if(($record['presentation_contract']??'')==='station-x-visual-v1') {
        $quality=ygo_data_presentation_quality($record);
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
function ygo_data_reader(string $name,string $raw): string {
    $raw=ygo_data_without_calculator($raw);
    $e=static fn($v)=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$html='';
    foreach(explode("\n",$raw) as $line){if(preg_match('/^(#{1,6})\s+(.*)$/u',$line,$m)){$n=min(6,strlen($m[1])+1);$html.='<h'.$n.'>'.$e(ygo_data_plain($m[2])).'</h'.$n.'>';}
        else $html.='<div class="line">'.$e($line).'</div>';
    }
    return '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="Content-Security-Policy" content="default-src &#39;none&#39;; style-src &#39;unsafe-inline&#39;"><title>'.$e($name).'</title><style>body{max-width:1000px;margin:24px auto;padding:16px;background:#081827;color:#e2efff;font:17px/1.6 system-ui}.line{white-space:pre-wrap;overflow-wrap:anywhere}h2,h3{color:#60dded}</style><h1>'.$e($name).'</h1>'.$html.'</html>';
}
/** Atomic pointer replacement under one global lock; immutable old revisions remain available. */
function ygo_data_publish(string $app,int $id,array $record,array $files,string $expected): array {
    $root=$app.'/assets/editorial';$registry=$root.'/strategy-content-state.json';
    $lock=fopen($root.'/.deck-data.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('No se pudo bloquear la publicación.');
    try{
        $state=ygo_data_read($registry);$old=$state['checked_studies'][(string)$id]??null;
        if(hash('sha256',ygo_data_json($old))!==$expected)throw new DomainException('El deck cambió desde la previsualización. Vuelve a seleccionar el ZIP.');
        $version='deck-data-'.$id.'-'.gmdate('YmdHis').'-'.bin2hex(random_bytes(4));$dir=$root.'/'.$version;
        if(!mkdir($dir,0755,true))throw new RuntimeException('No se pudo crear la versión del deck.');
        $prefix='./assets/editorial/'.$version.'/';$map=[];
        foreach($files as $name=>$bytes)if(preg_match('/\.(png|jpe?g|webp|gif)$/i',$name)){
            $info=@getimagesizefromstring($bytes);if(!$info||!in_array($info['mime'],['image/png','image/jpeg','image/webp','image/gif'],true))throw new InvalidArgumentException('Imagen inválida: '.$name);
            $ext=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp','image/gif'=>'gif'][$info['mime']];$dest=hash('sha256',$bytes).'.'.$ext;
            if(file_put_contents($dir.'/'.$dest,$bytes)!==strlen($bytes))throw new RuntimeException('No se pudo escribir la imagen.');$map[$name]=$prefix.$dest;
        }
        array_walk_recursive($record,static function(&$v)use($map){if(is_string($v)&&isset($map[$v]))$v=$map[$v];});
        $raw=$record['imported_source_text']??'';if($raw!==''){
            file_put_contents($dir.'/guide.html',ygo_data_reader($record['name'],$raw));$record['folder_delivery']['reader']=$prefix.'guide.html';
        }
        $record['folder_delivery']['source_file']='ZIP importado desde la ficha del deck';
        $record['folder_delivery']['imported_at']=gmdate('c');
        $json=ygo_data_json($record);if(file_put_contents($dir.'/'.$id.'.json',$json)!==strlen($json))throw new RuntimeException('No se pudo escribir el contenido.');
        // Keep the exact submitted package privately within a ZIP (HTML/SVG never served directly).
        $zip=new ZipArchive();if($zip->open($dir.'/source.zip',ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('No se pudo guardar el original.');
        foreach($files as $name=>$bytes)$zip->addFromString($name,$bytes);if(!$zip->close())throw new RuntimeException('No se pudo cerrar el original.');
        $entry=['path'=>$prefix.$id.'.json','cards'=>count($record['cards']),'routes'=>count($record['routes']),'sha256'=>hash('sha256',$json),'presentation'=>'curated-v62','source_zip'=>$prefix.'source.zip'];
        $state['checked_studies'][(string)$id]=$entry;$state['version']=$version;
        file_put_contents($dir.'/previous-entry.json',ygo_data_json($old));
        $temp=$registry.'.'.bin2hex(random_bytes(4)).'.tmp';$encoded=ygo_data_json($state);
        if(file_put_contents($temp,$encoded)!==strlen($encoded)||!rename($temp,$registry))throw new RuntimeException('No se pudo activar la versión; el contenido anterior sigue disponible.');
        return $entry;
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
