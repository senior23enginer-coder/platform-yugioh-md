<?php
declare(strict_types=1);
// Isolated local HTTP fixture: no production configuration or persistent database.
require_once __DIR__.'/../includes/decks/data-http.php';
function sx_start():void{session_start();if(empty($_SESSION['sx_csrf']))$_SESSION['sx_csrf']=bin2hex(random_bytes(32));}
function connect_store_common(string &$prefix,int &$entity):PDO{
    $pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE sx_ygo_decks(id INT,name TEXT);CREATE TABLE sx_ygo_deck_cards(id INT,deck_id INT,card_name TEXT,quantity INT,section TEXT,ygoprodeck_id INT);CREATE TABLE sx_ygo_created_combos(id TEXT,deck_id INT,revision INT,document_json TEXT,inventory_json TEXT,updated_at TEXT)');
    $id=(int)($_GET['deck_id']??665);$state=ygo_data_read(__DIR__.'/../assets/editorial/strategy-content-state.json');$record=ygo_data_read(__DIR__.'/../'.substr($state['checked_studies'][(string)$id]['path'],2));
    $q=$pdo->prepare('INSERT INTO sx_ygo_decks VALUES (?,?)');$q->execute([$id,$record['name']]);
    $q=$pdo->prepare('INSERT INTO sx_ygo_deck_cards VALUES (?,?,?,?,?,?)');
    foreach($record['cards'] as $c)foreach($c['slots'] as $s)$q->execute([$s['card_id'],$id,$c['name'],$s['quantity'],$s['section'],null]);return $pdo;
}
$testApp=getenv('YGO_DATA_TEST_APP')?:null;
if(($_GET['op']??'')==='commit'&&!$testApp){http_response_code(405);echo 'HTTP fixture cannot publish outside isolated test directory';exit;}
if(isset($_GET['api']))ygo_data_http($testApp);echo 'Local deck-data fixture';
