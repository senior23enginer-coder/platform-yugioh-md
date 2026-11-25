<?php
declare(strict_types=1);

/**
 * A03 one-time, explicitly scoped metadata reconciliation.
 * Usage: php tools/reconcile_a03_cids.php native.json official.json
 * Dry run is the default and does not connect to a database.
 * Apply only from a trusted CLI with --apply --approved-aliases=path.json.
 * PDO connection via STATIONX_PDO_DSN, STATIONX_PDO_USER, STATIONX_PDO_PASSWORD.
 * Never run during deployment. No DELETE, INSERT into inventory, or renumbering.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function readEvidence(string $path, string $sha): array {
    $raw = file_get_contents($path);
    if ($raw === false || !hash_equals($sha, hash('sha256', $raw))) {
        throw new RuntimeException('EVIDENCE_DIGEST_MISMATCH');
    }
    return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
}
function requireCondition(bool $value, string $message): void {
    if (!$value) { throw new RuntimeException($message); }
}
function nativeProjection(array $row): array {
    return [
        'id'=>(int)$row['id'], 'deck_id'=>(int)$row['deck_id'],
        'card_name'=>(string)$row['card_name'], 'section'=>(string)$row['section'],
        'quantity'=>(int)$row['quantity'], 'role'=>$row['role']??null,
        'notes'=>$row['notes']??null, 'ygoprodeck_id'=>$row['ygoprodeck_id']??null,
        'created_at'=>(string)$row['created_at'],
    ];
}
function buildA03Plan(array $native, array $official): array {
    $scope = [660=>36,673=>39,699=>39,705=>16];
    $snapshots = array_column($native, null, 'id');
    $decks = array_column($official['decks'], null, 'deck_id');
    $plan=[];
    foreach ($scope as $deckId=>$expectedCount) {
        $nativeRows=$snapshots[$deckId]['cards'];
        $byId=array_column($nativeRows,null,'id');
        requireCondition(count($byId)===$expectedCount && count($nativeRows)===$expectedCount, 'ROW_COUNT_'.$deckId);
        $seen=[];
        foreach ($decks[$deckId]['entries'] as $entry) {
            $cid=(int)$entry['cid'];
            $url=parse_url($entry['source']);
            parse_str($url['query']??'', $query);
            requireCondition(($entry['retrieved']??false)===true && $cid>0 &&
                ($url['scheme']??'')==='https' && ($url['host']??'')==='www.db.yugioh-card.com' &&
                ($query['cid']??'')===(string)$cid && ($url['path']??'')===($deckId===705?'/rushdb/card_search.action':'/yugiohdb/card_search.action') &&
                preg_match('/^[a-f0-9]{64}$/D',$entry['text_sha256'])===1, 'SOURCE_'.$cid);
            foreach ($entry['card_id'] as $id) {
                requireCondition(isset($byId[$id]) && !isset($seen[$id]), 'ROW_ID_'.$id);
                $seen[$id]=true;
                $row=nativeProjection($byId[$id]);
                requireCondition($row['deck_id']===$deckId && $row['card_name']===$entry['inventory_name'], 'IDENTITY_'.$id);
                $notes=json_decode($row['notes']??'{}',true,512,JSON_THROW_ON_ERROR);
                requireCondition(is_array($notes) && empty($notes['konami_cid']) && empty($row['ygoprodeck_id']), 'ALREADY_IDENTIFIED_'.$id);
                $notes['konami_cid']=$cid;
                $plan[$deckId][]=['before'=>$row,'new_notes'=>json_encode($notes,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
                    'cid'=>$cid,'title'=>$entry['official_title'],'source'=>$entry['source'],
                    'alias_review_required'=>!$entry['name_exact_normalized']];
            }
        }
        requireCondition(count($seen)===$expectedCount,'INCOMPLETE_'.$deckId);
    }
    return $plan;
}

function main(array $args): void {
    requireCondition(count($args)>=3,'USAGE: native.json official.json [--apply --approved-aliases=path.json]');
    $native=readEvidence($args[1],'a22d172d04c5e7f8127c97726a9a121aa46ba66fd22f33015a8be0d601845fac');
    $official=readEvidence($args[2],'81b5ceb032531695817d36fecc4ee7957b1f10f54189728143abdc842b7a222d');
    $plan=buildA03Plan($native,$official);
    $apply=in_array('--apply',$args,true);
    $approved=[];
    foreach ($args as $arg) {
        if (str_starts_with($arg,'--approved-aliases=')) {
            $approved=json_decode(file_get_contents(substr($arg,19)),true,512,JSON_THROW_ON_ERROR);
        }
    }
    $report=[];
    foreach ($plan as $deckId=>$rows) {
        foreach ($rows as $item) {
            $id=$item['before']['id'];
            $accepted=!$item['alias_review_required'] ||
                (isset($approved[(string)$id]) && $approved[(string)$id]===['cid'=>$item['cid'],'official_title'=>$item['title']]);
            $report[]=['deck_id'=>$deckId,'card_id'=>$id,'name'=>$item['before']['card_name'],
                'cid'=>$item['cid'],'official_title'=>$item['title'],'eligible'=>$accepted];
        }
    }
    echo json_encode(['mode'=>$apply?'apply':'dry_run','rows'=>count($report),
        'eligible'=>count(array_filter($report,fn($r)=>$r['eligible'])),'entries'=>$report],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),PHP_EOL;
    if (!$apply) { return; }
    requireCondition(count(array_filter($report,fn($r)=>$r['eligible']))===130,'ALIAS_APPROVAL_REQUIRED');
    $dsn=getenv('STATIONX_PDO_DSN');
    requireCondition(is_string($dsn)&&str_starts_with($dsn,'mysql:'),'MYSQL_DSN_REQUIRED');
    $pdo=new PDO($dsn,getenv('STATIONX_PDO_USER')?:'',getenv('STATIONX_PDO_PASSWORD')?:'',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $pdo->beginTransaction();
    try {
        $lock=$pdo->prepare('SELECT id FROM sx_ygo_decks WHERE id=? AND active=1 FOR UPDATE');
        $read=$pdo->prepare('SELECT * FROM sx_ygo_deck_cards WHERE deck_id=? ORDER BY id FOR UPDATE');
        $update=$pdo->prepare('UPDATE sx_ygo_deck_cards SET notes=? WHERE id=? AND deck_id=?');
        $audit=$pdo->prepare('INSERT INTO sx_ygo_audit_log(entity_type,entity_id,action,payload) VALUES(?,?,?,?)');
        foreach ($plan as $deckId=>$rows) {
            $lock->execute([$deckId]); requireCondition((bool)$lock->fetch(),'DECK_NOT_ACTIVE');
            $read->execute([$deckId]); $before=array_map('nativeProjection',$read->fetchAll());
            $expected=array_map(fn($x)=>$x['before'],$rows);
            usort($expected,fn($a,$b)=>$a['id']<=>$b['id']);
            requireCondition($before===$expected,'STALE_SNAPSHOT_'.$deckId);
            $afterExpected=[];
            foreach ($rows as $item) {
                $r=$item['before'];
                $update->execute([$item['new_notes'],$r['id'],$deckId]);
                requireCondition($update->rowCount()===1,'WRITE_COUNT_'.$r['id']);
                $r['notes']=$item['new_notes']; $afterExpected[]=$r;
            }
            usort($afterExpected,fn($a,$b)=>$a['id']<=>$b['id']);
            $read->execute([$deckId]); $after=array_map('nativeProjection',$read->fetchAll());
            requireCondition($after===$afterExpected,'POST_WRITE_INVARIANT_'.$deckId);
            $audit->execute(['deck',$deckId,'a03_cid_metadata_reconciliation',json_encode(
                ['before'=>$before,'after'=>$after,'source_run'=>45515416575],
                JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
        }
        $pdo->commit();
        echo "COMMITTED 130 metadata rows; IDs, names, zones, quantities and YGOPRODeck IDs unchanged.\n";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}
if (realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__) {
    try { main($argv); }
    catch (Throwable $e) { fwrite(STDERR,$e->getMessage().PHP_EOL); exit(1); }
}
