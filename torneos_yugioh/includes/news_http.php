<?php
declare(strict_types=1);
require_once __DIR__.'/news.php';

function ygo_news_items(PDO $pdo,array $input): array {
 $where=[];$params=[];$sources=ygo_news_sources();
 $source=(string)($input['source']??'');if($source){if(!isset($sources[$source]))throw new InvalidArgumentException('Fuente no válida.');$where[]='source_key=?';$params[]=$source;}
 foreach(['format'=>['tcg','ocg','rush_duel','goat','master_duel','genesys','general'],'topic'=>['news','banlist','tournament','cards','guides']] as $key=>$allowed){$value=(string)($input[$key]??'');if(!$value)continue;if(!in_array($value,$allowed,true))throw new InvalidArgumentException('Filtro no válido.');$where[]=($key==='format'?'format_code':'topic').'=?';$params[]=$value;}
 $view=(string)($input['view']??'all');if(!in_array($view,['all','unread','saved'],true))throw new InvalidArgumentException('Vista no válida.');if($view==='unread')$where[]='read_at IS NULL';if($view==='saved')$where[]='bookmarked=1';
 $query=ygo_news_text($input['q']??'',100);if($query!==''){$where[]='(title LIKE ? OR excerpt LIKE ?)';$params[]='%'.$query.'%';$params[]='%'.$query.'%';}
 $days=(int)($input['days']??0);if($days){if(!in_array($days,[7,30,90],true))throw new InvalidArgumentException('Período no válido.');$where[]='published_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL '.$days.' DAY)';}
 $clause=$where?' WHERE '.implode(' AND ',$where):'';$count=$pdo->prepare('SELECT COUNT(*) FROM sx_ygo_news_items'.$clause);$count->execute($params);$total=(int)$count->fetchColumn();$pages=max(1,(int)ceil($total/12));$page=max(1,min($pages,(int)($input['page']??1)));
 $q=$pdo->prepare('SELECT id,source_key,url,title,excerpt,image_url,published_at,date_precision,format_code,topic,first_seen_at,updated_at,read_at,bookmarked FROM sx_ygo_news_items'.$clause.' ORDER BY (published_at IS NULL) ASC,published_at DESC,first_seen_at DESC,id DESC LIMIT 12 OFFSET '.(($page-1)*12));$q->execute($params);$items=$q->fetchAll(PDO::FETCH_ASSOC);
 $decks=$pdo->query('SELECT id,name FROM sx_ygo_decks WHERE active=1 ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
 foreach($items as &$item){$item['id']=(int)$item['id'];$item['bookmarked']=(bool)$item['bookmarked'];$item['unread']=$item['read_at']===null;$item['has_image']=!empty($item['image_url']);unset($item['image_url']);$item['related_decks']=[];
  foreach($decks as $d){$term=trim(preg_replace('/\s+(Pure|K9|Horus)$/i','',$d['name']));if(mb_strlen($term)>=4&&mb_stripos($item['title'].' '.$item['excerpt'],$term)!==false)$item['related_decks'][]=['id'=>(int)$d['id'],'name'=>$d['name']];}
 }unset($item);
 $states=$pdo->query('SELECT * FROM sx_ygo_news_sources ORDER BY source_key')->fetchAll(PDO::FETCH_ASSOC);foreach($states as &$state){$key=$state['source_key'];$state=array_merge($sources[$key],$state);$c=$pdo->prepare('SELECT COUNT(*) FROM sx_ygo_news_items WHERE source_key=?');$c->execute([$key]);$state['count']=(int)$c->fetchColumn();}unset($state);
 return ['items'=>$items,'sources'=>$states,'total'=>$total,'page'=>$page,'pages'=>$pages,'unread'=>(int)$pdo->query('SELECT COUNT(*) FROM sx_ygo_news_items WHERE read_at IS NULL')->fetchColumn(),'saved'=>(int)$pdo->query('SELECT COUNT(*) FROM sx_ygo_news_items WHERE bookmarked=1')->fetchColumn(),'timezone'=>'America/Bogota','refresh_minutes'=>30];
}
function ygo_news_image(PDO $pdo,int $id): never {
 $s=$pdo->prepare('SELECT image_url FROM sx_ygo_news_items WHERE id=?');$s->execute([$id]);$url=(string)$s->fetchColumn();if(!$url||!ygo_news_url($url,'',true)){http_response_code(404);exit;}
 $dir=dirname(__DIR__).'/assets/cache/news';if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('No se pudo preparar la miniatura.');$path=$dir.'/'.hash('sha256',$url).'.img';
 if(!is_file($path)){
  $lock=fopen($path.'.lock','c+');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Miniatura ocupada.');
  try{if(!is_file($path)){$body=ygo_news_fetch($url,2000000);$type=@getimagesizefromstring($body)['mime']??'';if(!in_array($type,['image/jpeg','image/png','image/webp','image/gif'],true))throw new RuntimeException('Miniatura no disponible.');$tmp=$path.'.'.bin2hex(random_bytes(4)).'.tmp';if(file_put_contents($tmp,$body,LOCK_EX)===false||!rename($tmp,$path)){@unlink($tmp);throw new RuntimeException('No se pudo guardar la miniatura.');}}}finally{flock($lock,LOCK_UN);fclose($lock);}
 }
 $type=@getimagesize($path)['mime']??'';if(!$type){http_response_code(404);exit;}header('Content-Type: '.$type);header('Cache-Control: public, max-age=86400');header('X-Content-Type-Options: nosniff');readfile($path);exit;
}
function ygo_news_http(): never {
 header('Cache-Control: no-store');
 try{
  sx_start();$prefix='';$entity=1;$pdo=connect_store_common($prefix,$entity);ygo_news_schema($pdo);$_SESSION['news_token']??=bin2hex(random_bytes(24));
  $op=(string)($_GET['op']??'list');$method=$_SERVER['REQUEST_METHOD']??'GET';
  if($method==='GET'&&$op==='image'){session_write_close();ygo_news_image($pdo,(int)($_GET['id']??0));}
  if($method==='POST'){
   $raw=file_get_contents('php://input');if(strlen($raw)>10000)throw new InvalidArgumentException('Solicitud demasiado grande.');$input=json_decode($raw,true,16,JSON_THROW_ON_ERROR);
   if(!is_array($input)||!hash_equals($_SESSION['news_token'],(string)($input['token']??'')))throw new InvalidArgumentException('La sesión cambió. Recarga la página.');session_write_close();
   if($op==='sync'){$answer=ygo_news_sync($pdo,($input['retry_failed']??false)===true);}
   elseif(in_array($op,['read','bookmark'],true)){$id=(int)($input['id']??0);if($id<1)throw new InvalidArgumentException('Publicación no válida.');$value=(bool)($input['value']??true);$sql=$op==='read'?('read_at='.($value?'UTC_TIMESTAMP()':'NULL')):('bookmarked='.($value?'1':'0'));$q=$pdo->prepare('UPDATE sx_ygo_news_items SET '.$sql.' WHERE id=?');$q->execute([$id]);$answer=['id'=>$id,'value'=>$value];}
   elseif($op==='read_all'){$pdo->exec('UPDATE sx_ygo_news_items SET read_at=UTC_TIMESTAMP() WHERE read_at IS NULL');$answer=['updated'=>true];}
   else throw new InvalidArgumentException('Operación no válida.');
  }elseif($method==='GET'&&$op==='list'){$token=$_SESSION['news_token'];session_write_close();$answer=ygo_news_items($pdo,$_GET)+['token'=>$token];}
  else throw new InvalidArgumentException('Operación no válida.');
  header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>true]+$answer,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
 }catch(Throwable $e){http_response_code($e instanceof InvalidArgumentException?422:503);header('Content-Type: application/json; charset=utf-8');if(!($e instanceof InvalidArgumentException))error_log('StationX news: '.$e->getMessage());echo json_encode(['ok'=>false,'message'=>$e instanceof InvalidArgumentException?$e->getMessage():'No se pudo consultar el centro de noticias. El historial guardado se conserva.'],JSON_UNESCAPED_UNICODE);}
 exit;
}
