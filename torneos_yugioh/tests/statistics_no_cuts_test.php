<?php
require_once __DIR__.'/../includes/statistics_v8.php';
require_once __DIR__.'/../includes/statistics_store.php';
function requireLive(bool $ok): void { if(!$ok)throw new RuntimeException('Live statistics contract failed'); }
$pdo=new PDO('sqlite::memory:');
requireLive(ygo_stats_policy()['global_cadence']==='per_result');
requireLive(ygo_stats_publish_due($pdo)===0);
try { ygo_stats_publish($pdo,'global','tcg_advanced','2026-10-31 23:59:59','legacy'); throw new RuntimeException('Unexpected publication'); }
catch(InvalidArgumentException $e) { requireLive(str_contains($e->getMessage(),'retirados')); }
$data=ygo_stats_calculate([],[],[],['global_cadence'=>'monthly'],'2026-11-13 12:00:00');
requireLive($data['policy']['global_cadence']==='per_result');
requireLive(count($pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll())===0);
echo "OK: live cadence overrides legacy cadence; manual and automatic cuts perform zero SQL writes.\n";
