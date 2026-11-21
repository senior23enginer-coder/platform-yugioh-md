<?php
declare(strict_types=1);

/**
 * Per-deck analysis progress: bookkeeping only. No AI execution and no
 * catalogue-wide jobs. The canonical task definitions originate in GPT Prompts.
 */
function ygo_progress_manifest(string $app): array {
    $file=$app.'/assets/editorial/a01-a10-task-manifest.json';
    $doc=json_decode((string)file_get_contents($file),true,64,JSON_THROW_ON_ERROR);
    if(($doc['schema']??null)!=='station-x-a01-a10-task-manifest/1' ||
        ($doc['required_sections']??null)!==['main','extra','side'] ||
        ($doc['optional_sections']??null)!==['reserve'] ||
        !is_array($doc['stages']??null) || count($doc['stages'])!==11)
        throw new LogicException('Manifiesto de análisis incompatible.');
    $ids=[];$mandatory=0;$optional=0;
    foreach($doc['stages'] as $stage){
        if(!preg_match('/^(A0[1-9]|A10A|A10B)$/',(string)($stage['stage_id']??'')) ||
           !is_bool($stage['mandatory']??null) ||
           ($stage['mandatory']===($stage['stage_id']==='A10B')) ||
           !is_array($stage['tasks']??null) || !$stage['tasks'])
            throw new LogicException('Etapa del manifiesto inválida.');
        foreach($stage['tasks'] as $task){
            $id=(string)($task['task_id']??'');
            if(!preg_match('/^'.preg_quote($stage['stage_id'],'/').'-T[0-9]+$/',$id) ||
               isset($ids[$id]) || !is_string($task['description']??null) ||
               trim($task['description'])==='') throw new LogicException('Tarea inválida o duplicada.');
            $ids[$id]=true;
            if($stage['mandatory'])$mandatory++;else $optional++;
        }
    }
    if($mandatory!==35||$optional!==3)throw new LogicException('Cantidad de tareas no coincide con el contrato.');
    return $doc;
}
function ygo_progress_inventory_counts(array $inventory): array {
    $counts=['main'=>0,'extra'=>0,'side'=>0,'reserve'=>0];
    $seen=[];
    foreach($inventory['cards']??[] as $card){
        if(!is_array($card))throw new DomainException('Fila de inventario inválida.');
        $id=filter_var($card['id']??$card['card_id']??null,FILTER_VALIDATE_INT);
        $qty=filter_var($card['quantity']??null,FILTER_VALIDATE_INT);
        $zone=(string)($card['section']??'');
        if($zone==='tech')$zone='reserve';
        if(!$id||isset($seen[$id])||$qty===false||$qty<=0||!array_key_exists($zone,$counts))
            throw new DomainException('El inventario contiene filas duplicadas o no válidas.');
        $seen[$id]=true;
        $counts[$zone]+=$qty;
    }
    return $counts;
}
function ygo_progress_require_assigned(int $deckId,bool $explicit,array $counts): void {
    if($deckId<=0||!$explicit)throw new DomainException('Se requiere asignación expresa de un único deck.');
    foreach(['main','extra','side'] as $required){
        if(($counts[$required]??0)<=0)
            throw new DomainException('El deck no está cargado en '.strtoupper($required).'.');
    }
}
function ygo_progress_new_ledger(int $deckId,array $inventory,array $manifest,bool $explicit,string $now,bool $calculator=false): array {
    $counts=ygo_progress_inventory_counts($inventory);
    ygo_progress_require_assigned($deckId,$explicit,$counts);
    if((int)($inventory['deck']['id']??0)!==$deckId)throw new DomainException('La ficha no corresponde al deck asignado.');
    $tasks=[];
    foreach($manifest['stages'] as $stage)foreach($stage['tasks'] as $task){
        $tasks[]=['task_id'=>$task['task_id'],'stage_id'=>$stage['stage_id'],
          'mandatory'=>$stage['mandatory'],'description'=>$task['description'],
          'status'=>'PENDING','evidence'=>null,'block_reason'=>null,
          'started_at'=>null,'finished_at'=>null,'elapsed_seconds'=>0,'attempts'=>0];
    }
    return ['schema'=>'station-x-deck-analysis-ledger/1','deck_id'=>$deckId,
      'explicitly_user_assigned'=>true,'inventory_counts'=>$counts,
      'created_at'=>$now,'updated_at'=>$now,'calculator_requested'=>$calculator,
      'revision'=>1,'tasks'=>$tasks];
}
function ygo_progress_transition(array $ledger,string $taskId,string $action,string $now,?string $evidence=null,?string $reason=null): array {
    if(($ledger['schema']??null)!=='station-x-deck-analysis-ledger/1')
        throw new DomainException('Seguimiento incompatible.');
    $index=null;
    foreach($ledger['tasks'] as $i=>$task)if($task['task_id']===$taskId){$index=$i;break;}
    if($index===null)throw new InvalidArgumentException('Tarea no encontrada.');
    $task=$ledger['tasks'][$index];
    if(!$task['mandatory']&&empty($ledger['calculator_requested']))
        throw new DomainException('A10B no ha sido solicitado.');
    // Only the first still-unfinished required task may begin. No jumping.
    if($action==='start'){
        if(!in_array($task['status'],['PENDING','BLOCKED'],true))
            throw new DomainException('Esta tarea no está disponible para iniciarse.');
        foreach($ledger['tasks'] as $preceding){
            if($preceding['task_id']===$taskId)break;
            if(($preceding['mandatory']||!empty($ledger['calculator_requested'])) &&
               $preceding['status']!=='COMPLETE')
                throw new DomainException('Primero se debe terminar la tarea anterior.');
        }
        foreach($ledger['tasks'] as $other)if($other['status']==='RUNNING')
            throw new DomainException('Ya existe una tarea en ejecución.');
        $task['status']='RUNNING';$task['started_at']=$now;
        $task['finished_at']=null;$task['evidence']=null;$task['block_reason']=null;
        $task['attempts']++;
    }else{
        if(!in_array($action,['complete','block'],true)||$task['status']!=='RUNNING')
            throw new DomainException('Para cerrar una tarea primero debe estar en ejecución.');
        if($action==='complete'&&trim((string)$evidence)==='')
            throw new DomainException('No es posible completar sin evidencia verificable.');
        if($action==='block'&&trim((string)$reason)==='')
            throw new DomainException('La tarea bloqueada exige una causa concreta.');
        $elapsed=strtotime($now)-strtotime((string)$task['started_at']);
        if($elapsed<0)throw new DomainException('Las fechas de ejecución son inconsistentes.');
        $task['elapsed_seconds']+=$elapsed;
        $task['status']=$action==='complete'?'COMPLETE':'BLOCKED';
        $task['evidence']=$action==='complete'?trim((string)$evidence):null;
        $task['block_reason']=$action==='block'?trim((string)$reason):null;
        $task['started_at']=null;$task['finished_at']=$now;
    }
    $ledger['tasks'][$index]=$task;$ledger['updated_at']=$now;
    $ledger['revision']++;
    return $ledger;
}
function ygo_progress_summary(array $ledger,array $samples=[]): array {
    $tasks=array_values(array_filter($ledger['tasks'],static fn($t)=>$t['mandatory']||!empty($ledger['calculator_requested'])));
    $done=count(array_filter($tasks,static fn($t)=>$t['status']==='COMPLETE'));
    $blocked=count(array_filter($tasks,static fn($t)=>$t['status']==='BLOCKED'));
    $stages=[];
    foreach($tasks as $task){
        $id=$task['stage_id'];
        $stages[$id]??=['stage_id'=>$id,'total'=>0,'complete'=>0,'blocked'=>0,'elapsed_seconds'=>0];
        $stages[$id]['total']++;
        if($task['status']==='COMPLETE')$stages[$id]['complete']++;
        if($task['status']==='BLOCKED')$stages[$id]['blocked']++;
        $stages[$id]['elapsed_seconds']+=$task['elapsed_seconds'];
    }
    // Calculate an ETA per stage, not only a global figure. No fabricated
    // minutes: each unfinished task requires >=3 comparable measured samples.
    foreach($stages as &$stage){
        $stage['eta_seconds']=0;
        $stage['eta_status']='CALIBRATED_FROM_REAL_HISTORY';
    }
    unset($stage);
    $eta=0;$ready=true;
    foreach($tasks as $task){
        if($task['status']==='COMPLETE')continue;
        $valid=array_values(array_filter($samples[$task['task_id']]??[],
          static fn($v)=>is_numeric($v)&&(float)$v>0));
        $id=$task['stage_id'];
        if(count($valid)<3){
            $ready=false;
            $stages[$id]['eta_seconds']=null;
            $stages[$id]['eta_status']='INSUFFICIENT_REAL_SAMPLES';
            continue;
        }
        sort($valid,SORT_NUMERIC);
        $seconds=(int)$valid[intdiv(count($valid),2)];
        if($stages[$id]['eta_seconds']!==null)$stages[$id]['eta_seconds']+=$seconds;
        $eta+=$seconds;
    }
    return ['deck_id'=>$ledger['deck_id'],'total'=>count($tasks),'complete'=>$done,
      'remaining'=>count($tasks)-$done,'blocked'=>$blocked,
      'elapsed_seconds'=>array_sum(array_column($tasks,'elapsed_seconds')),
      'eta_seconds'=>$ready?$eta:null,
      'eta_status'=>$ready?'CALIBRATED_FROM_REAL_HISTORY':'INSUFFICIENT_REAL_SAMPLES',
      'stages'=>array_values($stages)];
}
function ygo_progress_schema(PDO $pdo): void {
    $pdo->exec('CREATE TABLE IF NOT EXISTS sx_ygo_deck_analysis_progress (
      deck_id BIGINT PRIMARY KEY,
      inventory_fingerprint VARCHAR(128) NOT NULL,
      format VARCHAR(32) NOT NULL,
      revision INTEGER NOT NULL,
      payload_json LONGTEXT NOT NULL,
      updated_at VARCHAR(32) NOT NULL
    )');
}
function ygo_progress_saved(PDO $pdo,int $deckId): ?array {
    $stmt=$pdo->prepare('SELECT inventory_fingerprint,format,revision,payload_json FROM sx_ygo_deck_analysis_progress WHERE deck_id=?');
    $stmt->execute([$deckId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row)return null;
    return ['fingerprint'=>$row['inventory_fingerprint'],'format'=>$row['format'],
      'revision'=>(int)$row['revision'],
      'ledger'=>json_decode($row['payload_json'],true,64,JSON_THROW_ON_ERROR)];
}
function ygo_progress_write(PDO $pdo,array $ledger,string $fingerprint,string $format,?int $expectedRevision): void {
    $payload=json_encode($ledger,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    if($expectedRevision===null){
        $stmt=$pdo->prepare('INSERT INTO sx_ygo_deck_analysis_progress(deck_id,inventory_fingerprint,format,revision,payload_json,updated_at) VALUES(?,?,?,?,?,?)');
        $stmt->execute([$ledger['deck_id'],$fingerprint,$format,$ledger['revision'],$payload,$ledger['updated_at']]);
    }else{
        $stmt=$pdo->prepare('UPDATE sx_ygo_deck_analysis_progress SET revision=?,payload_json=?,updated_at=? WHERE deck_id=? AND inventory_fingerprint=? AND revision=?');
        $stmt->execute([$ledger['revision'],$payload,$ledger['updated_at'],
          $ledger['deck_id'],$fingerprint,$expectedRevision]);
        if($stmt->rowCount()!==1)throw new DomainException('El seguimiento cambió durante la edición. Actualiza el grid.');
    }
}
function ygo_progress_history(PDO $pdo,array $ledger,string $format): array {
    $rows=$pdo->query('SELECT format,payload_json FROM sx_ygo_deck_analysis_progress')->fetchAll(PDO::FETCH_ASSOC);
    $samples=[];$sum=array_sum($ledger['inventory_counts']);
    foreach($rows as $row){
        if($row['format']!==$format)continue;
        $old=json_decode($row['payload_json'],true,64);
        if(!is_array($old)||($old['schema']??'')!==$ledger['schema']||
          ($old['deck_id']??null)===$ledger['deck_id'])continue;
        $oldSum=array_sum($old['inventory_counts']??[]);
        if($sum<=0||abs($oldSum-$sum)/$sum>0.2)continue;
        foreach($old['tasks']??[] as $task)if(($task['status']??null)==='COMPLETE'&&
            is_numeric($task['elapsed_seconds']??null)&&$task['elapsed_seconds']>0)
            $samples[$task['task_id']][]=(float)$task['elapsed_seconds'];
    }
    return $samples;
}
