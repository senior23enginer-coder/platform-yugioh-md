<?php
declare(strict_types=1);
$app=dirname(__DIR__);$uri=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(str_starts_with($uri,'/assets/'))return false;
require_once __DIR__.'/deck_worlds_fixture.php';
function sx_start(): void {if(session_status()!==PHP_SESSION_ACTIVE)session_start();$_SESSION['sx_csrf']??=bin2hex(random_bytes(24));}
function ygo_can_edit(): bool {return true;}
function connect_store_common(&$prefix,&$entity): PDO {return new PDO('sqlite:'.getenv('WORLD_TEST_DB'),null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);}
$data=json_decode(file_get_contents(__DIR__.'/fixtures/calculator_inventory.json'),true);
$data['allDecks'][]=['id'=>900,'name'=>'TCG sin cartas','team'=>'Equipo de prueba','format_codes'=>['tcg_advanced']];
$data['allDecks'][]=['id'=>901,'name'=>'Tradicional','format_codes'=>['tcg_traditional']];
$data['allDecks'][]=['id'=>902,'name'=>'GOAT test','format_codes'=>['goat']];
$pdo=connect_store_common($prefix,$entity);
if(!$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='sx_ygo_decks'")->fetchColumn()){
    ygo_worlds_test_schema($pdo);foreach($data['allDecks'] as $deck)ygo_worlds_test_deck($pdo,$deck['id'],$deck['name'],$deck['format_codes'][0]);
}
if(($_GET['api']??'')==='deck_attributes'){require $app.'/includes/decks/attributes-http.php';ygo_deck_attributes_http();}
sx_start();
?><!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="/assets/app.css"><link rel="stylesheet" href="/assets/modules/deck-attributes/style.css"><style>body{background:#061221;color:#eef6ff}main{max-width:1450px;padding:20px;margin:auto}.screen[hidden]{display:none!important}</style><main><?php require $app.'/includes/decks/attributes-view.php';?></main><script>window.DML_DATA=<?=json_encode($data)?>;</script><script src="/assets/modules/deck-attributes/model.js"></script><script src="/assets/modules/deck-attributes/ui.js"></script><script>function show(){const route=location.hash.slice(1)||'mundos';document.querySelectorAll('[data-screen]').forEach(n=>n.hidden=n.dataset.screen!==route);}addEventListener('hashchange',show);addEventListener('DOMContentLoaded',show);</script></html>
