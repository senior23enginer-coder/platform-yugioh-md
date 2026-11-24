<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/decks/data-http.php';
$sourceRoot=$argv[1]??'';
if(!is_dir($sourceRoot))throw new RuntimeException('GPT checkout required');
$app=dirname(__DIR__);
$state=ygo_data_read($app.'/assets/editorial/strategy-content-state.json');
$index=ygo_data_read($app.'/assets/editorial/deck-data-sources/index.json');
$assigned=[671,677,665,650,653,656,670,684,687,660,673,699,704,705,706,707];
$report=[];
foreach($assigned as $id){
 $stage='identity';$tmp=null;$scratch=null;
 try{
  $entry=$state['checked_studies'][(string)$id]??throw new RuntimeException('Unregistered deck');
  $source=$index[(string)$id]['source_document']??throw new RuntimeException('Missing source');
  $folder=preg_replace('/_CONTENIDO_CURADO\\.md$/','',$source);
  $root=$sourceRoot.'/01_INFORMACION_PARA_IMPORTAR/decks/'.$folder;
  $package=$folder.'/datos/stationx-deck.json';$doc=$folder.'/'.$source;
  $envelope=ygo_data_read($root.'/datos/stationx-deck.json');
  $record=$envelope['record'];
  if($envelope['deck_id']!==$id||$record['deck_id']!==$id)throw new RuntimeException('Wrong identity');
  $current=ygo_data_read(ygo_data_local($app,$entry['path']));
  $cards=[];$counts=['main'=>0,'extra'=>0,'side'=>0,'reserve'=>0];
  foreach($current['cards'] as $card)foreach($card['slots'] as $slot){
   $cards[]=['id'=>$slot['card_id'],'deck_id'=>$id,'card_name'=>$card['name'],'section'=>$slot['section'],'quantity'=>$slot['quantity'],'ygoprodeck_id'=>null];
   $counts[$slot['section']]+=$slot['quantity'];
  }
  if(!$counts['main']||(!in_array($record['format'],['goat','rush','rush_duel'],true)&&!$counts['extra']))throw new RuntimeException('Missing required zone');
  $inventory=['deck'=>['id'=>$id,'name'=>$current['name']],'cards'=>$cards,'format'=>$record['format'],'inventory_sha256'=>'isolated-'.$id];
  $stage='T4';ygo_data_validate_a01_profile($record);ygo_data_validate_player_guide($record);
  if(($record['player_guide']['strategy']['schema']??null)!=='station-x-player-strategy/1'||count($record['player_guide']['strategy']['sections']??[])!==8)throw new RuntimeException('Missing revised strategy');
  $fields=['Arquetipo','Tipo de estrategia','Estilo breve','Resumen para ficha','Cómo se juega','Qué vigilar'];
  $md=file_get_contents($root.'/'.$source);
  $offset=strpos($md,'## APARTADO_01');
  if($offset===false)throw new RuntimeException('Missing A01 section');
  $end=strpos($md,"\n## APARTADO_02",$offset);
  $section=substr($md,$offset,$end===false?null:$end-$offset);
  foreach($fields as $field)if(substr_count($section,'- **'.$field.':** '.$record['profile'][$field])!==1)throw new RuntimeException('Parity '.$field);
  $stage='T5 prepare';
  $tmp=tempnam(sys_get_temp_dir(),'a01-assigned-');
  $zip=new ZipArchive();if($zip->open($tmp,ZipArchive::OVERWRITE)!==true)throw new RuntimeException('ZIP');
  foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS)) as $file){
   if($file->isFile())$zip->addFile($file->getPathname(),$folder.'/'.substr($file->getPathname(),strlen($root)+1));
  }
  if(!$zip->close())throw new RuntimeException('ZIP close');
  $files=ygo_data_normalize_files(ygo_data_archive($tmp));
  $sources=ygo_data_sources($app,$id,$entry);
  $imported=ygo_data_prepare($app,$files,$inventory,$current,$sources);
  if($imported['profile']!==$record['profile']||$imported['player_guide']!==$record['player_guide'])throw new RuntimeException('Import lost A01');
  $signature=static function(array $rows):array{
   $result=[];foreach($rows as $r)$result[]=$r['id'].':'.$r['section'].':'.$r['quantity'];sort($result);return $result;
  };
  $recordRows=static function(array $r):array{
   $rows=[];foreach($r['cards'] as $c)foreach($c['slots'] as $s)$rows[]=['id'=>$s['card_id'],'section'=>$s['section'],'quantity'=>$s['quantity']];return $rows;
  };
  if($signature($cards)!==$signature($envelope['inventory'])||$signature($cards)!==$signature($recordRows($imported)))throw new RuntimeException('Inventory drift');
  $routeIds=static fn(array $r):array=>array_column($r['routes'],'id');
  if($routeIds($record)!==$routeIds($imported))throw new RuntimeException('Route identity drift');
  $stage='T5 rejection';$negative=0;
  $reject=static function(callable $change)use($envelope,$files,$package,$app,$inventory,$current,$sources,&$negative):void{
   $bad=$envelope;$change($bad);$candidate=$files;$candidate[$package]=ygo_data_json($bad);
   try{ygo_data_prepare($app,$candidate,$inventory,$current,$sources);}
   catch(InvalidArgumentException|DomainException $e){$negative++;return;}
   throw new RuntimeException('Invalid package accepted');
  };
  $reject(static function(&$x){unset($x['record']['profile']['Resumen para ficha']);});
  $reject(static function(&$x){$x['record']['profile']['Estilo breve']=str_repeat('x',191);});
  $reject(static function(&$x){$x['record']['a01_profile_contract']='invalid';});
  $reject(static function(&$x){$x['record']['player_guide']['featured_cards'][0]['cid']=99999999;});
  $reject(static function(&$x){$x['record']['player_guide']['phases'][0]['related_route_ids'][]='INVALID_ROUTE';});
  $reject(static function(&$x){$x['inventory'][0]['quantity']++;});
  $reject(static function(&$x){$x['record']['deck_id']=99999999;});
  $reject(static function(&$x){$x['record']['profile']['Tipo de estrategia']='Consultar el plan';});
  $reject(static function(&$x){array_pop($x['record']['player_guide']['strategy']['sections']);});
  $reject(static function(&$x){$x['record']['player_guide']['strategy']['sections'][0]['card_cids']=[99999999];});
  $reject(static function(&$x){$x['record']['player_guide']['strategy']['sections'][0]['actions'][0]='No escribir instrucciones para el jugador';});
  $reject(static function(&$x){$x['record']['player_guide']['strategy']['sections'][0]['related_route_ids'][]='INVALID_ROUTE';});
  $stage='T5 publish/export';
  $scratch=sys_get_temp_dir().'/stationx-a01-'.$id.'-'.bin2hex(random_bytes(8));
  mkdir($scratch.'/assets/editorial',0700,true);
  $registry=$scratch.'/assets/editorial/strategy-content-state.json';file_put_contents($registry,ygo_data_json($state));
  // Reproduce the existing public assets of the real app in the isolated root.
  // Do not invent, substitute or skip missing photographs. Copy verified bytes only.
  array_walk_recursive($imported,static function($value,$key)use($app,$scratch,$files):void{
   if(!is_string($value)||$value===''||!in_array($key,['image','full_image'],true)||isset($files[$value]))return;
   $source=ygo_data_local($app,$value);
   if(!$source)throw new RuntimeException('Existing photo missing: '.$value);
   $relative=preg_replace('~^\\./~','',$value);
   if(!preg_match('~^assets/(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_.-]+\\.(?:png|jpe?g|webp|gif)$~iD',$relative))throw new RuntimeException('Unexpected existing asset path: '.$value);
   $dest=$scratch.'/'.$relative;
   if(!is_dir(dirname($dest))&&!mkdir(dirname($dest),0700,true))throw new RuntimeException('Cannot reproduce asset directory');
   if(!is_file($dest)&&!copy($source,$dest))throw new RuntimeException('Cannot reproduce asset');
   if(hash_file('sha256',$source)!==hash_file('sha256',$dest)||!getimagesize($dest))throw new RuntimeException('Invalid reproduced asset');
  });
  ygo_data_publish($scratch,$id,$imported,$files,hash('sha256',ygo_data_json($entry)));
  [$activeEntry,$active]=ygo_data_current($scratch,$id);
  if($active['profile']!==$record['profile']||$active['player_guide']!==$record['player_guide'])throw new RuntimeException('Publish lost A01');
  if($signature($cards)!==$signature($recordRows($active))||$routeIds($record)!==$routeIds($active))throw new RuntimeException('Publish changed identities');
  $after=ygo_data_read($registry);
  foreach($state['checked_studies'] as $other=>$value)if((int)$other!==$id&&$after['checked_studies'][$other]!==$value)throw new RuntimeException('Cross-deck mutation');
  $photos=0;foreach($active['cards'] as $c){if(!empty($c['image'])&&ygo_data_local($scratch,$c['image']))$photos++;}
  if($photos!==count($active['cards']))throw new RuntimeException('Incomplete card photos');
  $export=ygo_data_export_files($scratch,$inventory,$active,$files,[]);
  $again=ygo_data_prepare($scratch,$export,$inventory,$active,$files);
  if($again['profile']!==$record['profile']||$again['player_guide']!==$record['player_guide']||$signature($cards)!==$signature($recordRows($again))||$routeIds($record)!==$routeIds($again))throw new RuntimeException('Round trip drift');
  $report[]=['deck_id'=>$id,'status'=>'ISOLATED_PASS','counts'=>$counts,'fields'=>6,'featured'=>count($record['player_guide']['featured_cards']),'phases'=>4,'lessons'=>8,'semantic_certification'=>false,'photos'=>$photos,'routes'=>count($record['routes']),'rejections'=>$negative];
 }catch(Throwable $e){$report[]=['deck_id'=>$id,'status'=>'FAIL','stage'=>$stage,'error'=>$e->getMessage()];}
 finally{
  if($tmp&&is_file($tmp))unlink($tmp);
  if($scratch&&is_dir($scratch)){foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scratch,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $f){if($f->isDir()&&!$f->isLink())rmdir($f->getPathname());else unlink($f->getPathname());}rmdir($scratch);}
 }
}
$out=['scope'=>'exact assigned 16 decks, isolated only','production_imports'=>0,'decks'=>$report];
mkdir(__DIR__.'/../../outputs/a01-assigned',0777,true);
file_put_contents(__DIR__.'/../../outputs/a01-assigned/import-report.json',json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
exit(count(array_filter($report,static fn($r)=>$r['status']!=='ISOLATED_PASS'))?1:0);
