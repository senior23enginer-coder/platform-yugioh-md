<?php
declare(strict_types=1);

function ygo_news_sources(): array {
 return [
 'ygorganization'=>['name'=>'YGOrganization','url'=>'https://ygorganization.com/','transport'=>'RSS','color'=>'#ffb45c','short'=>'ORG'],
 'ygoprodeck'=>['name'=>'YGOPRODeck','url'=>'https://ygoprodeck.com/','transport'=>'Artículos públicos','color'=>'#55d4ed','short'=>'YGO'],
 'masterduelmeta'=>['name'=>'Master Duel Meta','url'=>'https://www.masterduelmeta.com/','transport'=>'Artículos públicos','color'=>'#b49aff','short'=>'MD'],
 'yugiohmeta'=>['name'=>'Yu-Gi-Oh! Meta','url'=>'https://www.yugiohmeta.com/','transport'=>'Catálogo público del sitio','color'=>'#78e4b2','short'=>'META']];
}
function ygo_news_schema(PDO $pdo): void {
 try{if((int)$pdo->query('SELECT COUNT(*) FROM sx_ygo_news_sources')->fetchColumn()===count(ygo_news_sources()))return;}catch(PDOException $e){if($e->getCode()!=='42S02')throw $e;}
 $pdo->exec("CREATE TABLE IF NOT EXISTS sx_ygo_news_sources (source_key VARCHAR(32) PRIMARY KEY,last_attempt_at DATETIME NULL,last_success_at DATETIME NULL,next_attempt_at DATETIME NULL,last_error VARCHAR(300) NULL,last_imported INT NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("CREATE TABLE IF NOT EXISTS sx_ygo_news_items (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,source_key VARCHAR(32) NOT NULL,url_hash CHAR(64) NOT NULL UNIQUE,url TEXT NOT NULL,title VARCHAR(350) NOT NULL,excerpt TEXT NOT NULL,image_url TEXT NULL,published_at DATETIME NULL,date_precision VARCHAR(12) NOT NULL DEFAULT 'unknown',format_code VARCHAR(24) NOT NULL,topic VARCHAR(24) NOT NULL,content_hash CHAR(64) NOT NULL,first_seen_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,read_at DATETIME NULL,bookmarked TINYINT NOT NULL DEFAULT 0,INDEX(source_key,published_at),INDEX(format_code,topic),INDEX(first_seen_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 foreach(array_keys(ygo_news_sources()) as $key){$s=$pdo->prepare('INSERT IGNORE INTO sx_ygo_news_sources(source_key) VALUES(?)');$s->execute([$key]);}
}
function ygo_news_text(mixed $value,int $max=350): string {
 if(!is_scalar($value)&&$value!==null)return '';
 $s=html_entity_decode(strip_tags((string)$value),ENT_QUOTES|ENT_HTML5,'UTF-8');return mb_substr(trim(preg_replace('/\s+/u',' ',$s)??''),0,$max);
}
function ygo_news_url(string $value,string $base,bool $image=false): string {
 $value=html_entity_decode(trim($value),ENT_QUOTES|ENT_HTML5,'UTF-8');
 if(str_starts_with($value,'//'))$value='https:'.$value;elseif(str_starts_with($value,'/'))$value=rtrim($base,'/').$value;
 $host=strtolower((string)parse_url($value,PHP_URL_HOST));
 $hosts=$image?['ygorganization.com','cdn.ygorganization.com','ygoprodeck.com','images.ygoprodeck.com','s3.duellinksmeta.com','www.masterduelmeta.com','www.yugiohmeta.com']:array_unique(array_map(fn($s)=>parse_url($s['url'],PHP_URL_HOST),ygo_news_sources()));
 if(parse_url($value,PHP_URL_SCHEME)!=='https'||!in_array($host,$hosts,true)||parse_url($value,PHP_URL_USER)!==null||parse_url($value,PHP_URL_PORT)!==null)return '';
 return strtok($value,'#')?:'';
}
/** Fixed provider hosts only; no redirects or caller-controlled fetch targets. */
function ygo_news_fetch(string $url,int $max=3500000): string {
 if(!ygo_news_url($url,'',true))throw new RuntimeException('Dirección de la fuente no admitida.');
 $body='';$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>12,CURLOPT_USERAGENT=>'StationX-News/1.0 (+https://stationxtulua.com/yugioh/)',CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_ENCODING=>'',CURLOPT_WRITEFUNCTION=>static function($c,$chunk)use(&$body,$max){if(strlen($body)+strlen($chunk)>$max)return 0;$body.=$chunk;return strlen($chunk);}]);$ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
 if($ok===false||$status!==200)throw new RuntimeException('La fuente no respondió correctamente'.($status?' (HTTP '.$status.')':'').'.');return $body;
}
function ygo_news_dom(string $html): array {
 $previous=libxml_use_internal_errors(true);try{$dom=new DOMDocument();$dom->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);return [$dom,new DOMXPath($dom)];}finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
}
function ygo_news_date(string $value): array {
 if(!$value)return [null,'unknown'];try{
  if(!preg_match('/(?:Z|[+-]\d{2}:?\d{2}|GMT|UTC)$/i',$value))return [(new DateTimeImmutable($value))->format('Y-m-d').' 00:00:00','date'];
  return [(new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),'instant'];
 }catch(Throwable){return [null,'unknown'];}
}
function ygo_news_classify(string $source,string $text,string $category=''): array {
 $v=mb_strtolower($category.' '.$text);
 $format=$source==='masterduelmeta'?'master_duel':'general';
 if($source!=='masterduelmeta'){
  foreach(['rush_duel'=>'rush duel|rush-duel|rd/kp','master_duel'=>'master duel','goat'=>'\bgoat\b','genesys'=>'genesys','ocg'=>'\bocg\b','tcg'=>'\btc[g]\b'] as $key=>$pattern)if(preg_match('~'.$pattern.'~iu',$v)){$format=$key;break;}
 }
 $topic='news';foreach(['banlist'=>'banlist|forbidden|limited list|limits','tournament'=>'tournament|ycs|wcq|regional|duelist cup','cards'=>'card[- ]reveal|new card|new:|alternate art|set[- ]release|secret[- ]pack|rd/kp','guides'=>'guide|strategy|combo|archetype discussion'] as $key=>$pattern)if(preg_match('~'.$pattern.'~iu',$v)){$topic=$key;break;}
 return [$format,$topic];
}
function ygo_news_normalize(string $source,array $row): ?array {
 $base=ygo_news_sources()[$source]['url'];$url=ygo_news_url((string)($row['url']??''),$base);$title=ygo_news_text($row['title']??'');
 if(!$url||parse_url($url,PHP_URL_HOST)!==parse_url($base,PHP_URL_HOST)||mb_strlen($title)<4)return null;
 if($source==='yugiohmeta'&&str_starts_with((string)($row['image']??''),'/mdm_img/'))$row['image']='https://s3.duellinksmeta.com'.$row['image'];
 $url=rtrim(explode('?',$url)[0],'/');[$date,$precision]=ygo_news_date((string)($row['date']??''));
 [$format,$topic]=ygo_news_classify($source,$title.' '.($row['excerpt']??''),(string)($row['category']??''));
 return ['source_key'=>$source,'url'=>$url,'title'=>$title,'excerpt'=>ygo_news_text($row['excerpt']??'',320),'image_url'=>ygo_news_url((string)($row['image']??''),$base,true)?:null,'published_at'=>$date,'date_precision'=>$precision,'format_code'=>$format,'topic'=>$topic];
}
function ygo_news_parse_org(string $xml): array {
 $old=libxml_use_internal_errors(true);try{$root=simplexml_load_string($xml,SimpleXMLElement::class,LIBXML_NONET|LIBXML_NOCDATA);if(!$root||!isset($root->channel->item))throw new RuntimeException('El RSS de la fuente cambió.');$rows=[];
 foreach($root->channel->item as $item){$description=(string)$item->description;preg_match('~<img[^>]+src=[\'"]([^\'"]+)~i',$description,$m);$rows[]=['url'=>(string)$item->link,'title'=>(string)$item->title,'excerpt'=>$description,'date'=>(string)$item->pubDate,'image'=>$m[1]??'','category'=>implode(' ',array_map('strval',iterator_to_array($item->category,false)))];}return $rows;
 }finally{libxml_clear_errors();libxml_use_internal_errors($old);}
}
function ygo_news_org_images(string $html): array {
 [$dom,$xp]=ygo_news_dom($html);$images=[];
 foreach($xp->query('//article') as $article){$a=$xp->query('.//a[@href]',$article)->item(0);$img=$xp->query('.//img[contains(@src,"cdn.ygorganization.com")]',$article)->item(0);if(!$a||!$img)continue;$path=$a->getAttribute('href');if(str_starts_with($path,'./'))$path='/'.substr($path,2);$url=ygo_news_url($path,'https://ygorganization.com/');if($url)$images[rtrim($url,'/')]=$img->getAttribute('src');}return $images;
}
function ygo_news_parse_meta(string $json): array {
 $data=json_decode($json,true,64,JSON_THROW_ON_ERROR);if(!is_array($data)||!array_is_list($data))throw new RuntimeException('El catálogo público de la fuente cambió.');$rows=[];
 foreach($data as $r){if(!is_array($r)||!empty($r['hidden']))continue;$path=(string)($r['url']??'');if(!str_starts_with($path,'/articles/'))$path='/articles/'.ltrim($path,'/');$rows[]=['url'=>$path,'title'=>$r['title']??'','excerpt'=>$r['description']??'','date'=>$r['date']??'','image'=>$r['image']??'','category'=>($r['category']??'').' '.(!empty($r['ocg'])?'OCG':'').' '.(!empty($r['genesys'])?'Genesys':'')];}return $rows;
}
function ygo_news_parse_listing(string $source,string $html): array {
 [$dom,$xp]=ygo_news_dom($html);$master=$source==='masterduelmeta';$rows=[];
 foreach($xp->query('//a[contains(@href,"'.($master?'/articles/':'/article/').'")]') as $a){
  $title=$master?$xp->query('.//*[contains(concat(" ",normalize-space(@class)," ")," title ")]',$a)->item(0)?->textContent:$a->textContent;
  if(!$title)continue;$excerpt='';if(!$master)$excerpt=$xp->query('.//*[contains(@class,"card-excerpt")]',$a->parentNode)->item(0)?->textContent??'';
  $image=$xp->query('.//img',$a)->item(0);$img=$image?->getAttribute('src')??'';
  if(!$master&&!$img){$parent=$a->parentNode;for($i=0;$i<3&&$parent;$i++,$parent=$parent->parentNode){$found=$xp->query('.//img[contains(@src,"/images/articles/")]',$parent)->item(0);if($found){$img=$found->getAttribute('src');break;}}}
  if(!$img&&$image){$set=$image->getAttribute('srcset');if(preg_match('~https://wsrv\.nl/\?url=([^& ]+)~',$set,$m))$img=urldecode($m[1]);}
  $text=$master?($xp->query('.//*[contains(@class,"sub-text")]',$a)->item(0)?->textContent??''):'';$date='';if(preg_match('/(?:Published|Updated)\s+([A-Z][a-z]+ \d{1,2})(?:st|nd|rd|th)?, (\d{4})/',$text,$m))$date=$m[1].', '.$m[2];
  $row=['url'=>$a->getAttribute('href'),'title'=>$title,'excerpt'=>$excerpt,'image'=>$img,'date'=>$date,'category'=>$a->textContent];$rows[$row['url']]=$row;
 }
 return array_slice(array_values($rows),0,16);
}
function ygo_news_article_metadata(string $html): array {
 [$dom,$xp]=ygo_news_dom($html);$meta=[];
 foreach($xp->query('//meta[@content]') as $node)$meta[$node->getAttribute('property')?:$node->getAttribute('name')]=$node->getAttribute('content');
 $date=$meta['article:published_time']??'';
 if(!$date)foreach($xp->query('//script[@type="application/ld+json"]') as $script){$r=json_decode($script->textContent,true);if(is_array($r)&&isset($r['datePublished'])){$date=(string)$r['datePublished'];break;}}
 return ['excerpt'=>$meta['og:description']??$meta['description']??'','image'=>$meta['og:image']??'','date'=>$date];
}
function ygo_news_discover(string $source): array {
 $base=ygo_news_sources()[$source]['url'];
 if($source==='ygorganization'){$rows=ygo_news_parse_org(ygo_news_fetch($base.'feed'));try{$images=ygo_news_org_images(ygo_news_fetch($base));foreach($rows as &$row)$row['image']=$images[rtrim($row['url'],'/')]??($row['image']??'');unset($row);}catch(Throwable){}return $rows;}
 if($source==='yugiohmeta')return ygo_news_parse_meta(ygo_news_fetch($base.'api/v1/articles?hidden%5B%24ne%5D=true&limit=20&sort%5Bdate%5D=-1&fields%5Bmarkdown%5D=0'));
 return ygo_news_parse_listing($source,ygo_news_fetch($base));
}
function ygo_news_sync(PDO $pdo,bool $retryFailed=false): array {
 if(!$pdo->query("SELECT GET_LOCK('sx_ygo_news_sync',0)")->fetchColumn())return ['busy'=>true,'checked'=>false];
 try{
  $retry=$retryFailed?" OR (last_error IS NOT NULL AND last_attempt_at<=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 MINUTE))":"";
  $source=$pdo->query("SELECT * FROM sx_ygo_news_sources WHERE (next_attempt_at IS NULL OR next_attempt_at<=UTC_TIMESTAMP()".$retry.") ORDER BY COALESCE(last_attempt_at,'2000-01-01'),source_key LIMIT 1")->fetch(PDO::FETCH_ASSOC);
  if(!$source)return ['checked'=>false,'complete'=>true];$key=$source['source_key'];$pdo->prepare('UPDATE sx_ygo_news_sources SET last_attempt_at=UTC_TIMESTAMP(),next_attempt_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 MINUTE) WHERE source_key=?')->execute([$key]);
  try{
   $rows=ygo_news_discover($key);$items=[];$enriched=0;
   foreach($rows as $row){$r=ygo_news_normalize($key,$row);if(!$r)continue;
    if(in_array($key,['ygoprodeck','masterduelmeta'],true)&&$enriched<2){$q=$pdo->prepare('SELECT date_precision,excerpt,image_url FROM sx_ygo_news_items WHERE url_hash=?');$q->execute([hash('sha256',$r['url'])]);$old=$q->fetch(PDO::FETCH_ASSOC);
     if(!$old||$old['date_precision']==='unknown'||!$old['excerpt']){try{$extra=ygo_news_article_metadata(ygo_news_fetch($r['url']));foreach($extra as $field=>$value)if($value!=='')$row[$field]=$value;$r=ygo_news_normalize($key,$row);$enriched++;}catch(Throwable){$enriched++;}}
    }
    if($r)$items[]=$r;
   }
   if(!$items)throw new RuntimeException('La fuente no entregó publicaciones reconocibles. Se conserva el historial anterior.');
   $pdo->beginTransaction();$imported=0;
   $find=$pdo->prepare('SELECT id,content_hash,excerpt,image_url,published_at,date_precision FROM sx_ygo_news_items WHERE url_hash=?');
   foreach($items as $r){$hash=hash('sha256',$r['url']);$find->execute([$hash]);$old=$find->fetch(PDO::FETCH_ASSOC);
    if($old){if(!$r['excerpt'])$r['excerpt']=$old['excerpt'];if(!$r['image_url'])$r['image_url']=$old['image_url'];if(!$r['published_at']){$r['published_at']=$old['published_at'];$r['date_precision']=$old['date_precision'];}}
    $content=hash('sha256',json_encode($r,JSON_UNESCAPED_UNICODE));
    if(!$old){$read=null;$q=$pdo->prepare('INSERT INTO sx_ygo_news_items(source_key,url_hash,url,title,excerpt,image_url,published_at,date_precision,format_code,topic,content_hash,first_seen_at,updated_at,read_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP(),?)');$q->execute([$key,$hash,$r['url'],$r['title'],$r['excerpt'],$r['image_url'],$r['published_at'],$r['date_precision'],$r['format_code'],$r['topic'],$content,$read]);$imported++;}
    elseif($old['content_hash']!==$content){$q=$pdo->prepare('UPDATE sx_ygo_news_items SET title=?,excerpt=?,image_url=?,published_at=?,date_precision=?,format_code=?,topic=?,content_hash=?,updated_at=UTC_TIMESTAMP() WHERE id=?');$q->execute([$r['title'],$r['excerpt'],$r['image_url'],$r['published_at'],$r['date_precision'],$r['format_code'],$r['topic'],$content,$old['id']]);}
   }
   $pdo->prepare('UPDATE sx_ygo_news_sources SET last_success_at=UTC_TIMESTAMP(),last_error=NULL,last_imported=? WHERE source_key=?')->execute([$imported,$key]);$pdo->commit();return ['checked'=>true,'source'=>$key,'imported'=>$imported];
  }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$message=$e instanceof RuntimeException&&!($e instanceof PDOException)?$e->getMessage():'No se pudo interpretar la respuesta de la fuente.';$pdo->prepare('UPDATE sx_ygo_news_sources SET last_error=?,next_attempt_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 5 MINUTE) WHERE source_key=?')->execute([mb_substr($message,0,300),$key]);return ['checked'=>true,'source'=>$key,'error'=>$message];}
 }finally{$pdo->query("SELECT RELEASE_LOCK('sx_ygo_news_sync')");}
}
