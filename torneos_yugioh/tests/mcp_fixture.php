<?php
declare(strict_types=1);
// Test-only database/config bootstrap. Never publish this file.
$dbPath=getenv('MCP_TEST_DB');if(!$dbPath)throw new RuntimeException('Isolated test database required.');
if(PHP_SAPI==='cli'){
    $pdo=new PDO('sqlite:'.$dbPath);$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE sx_ygo_decks(id INTEGER PRIMARY KEY,name TEXT,style TEXT,archetype TEXT,notes TEXT,active INTEGER); CREATE TABLE sx_ygo_deck_cards(id INTEGER PRIMARY KEY,deck_id INTEGER,card_name TEXT,quantity INTEGER,section TEXT,ygoprodeck_id INTEGER); CREATE TABLE sx_ygo_created_combos(id TEXT PRIMARY KEY,deck_id INTEGER,title TEXT,revision INTEGER,inventory_sha256 TEXT,document_json TEXT,inventory_json TEXT,created_at TEXT,updated_at TEXT)');
    $fixture=json_decode(file_get_contents(__DIR__.'/fixtures/calculator_inventory.json'),true);
    $q=$pdo->prepare('INSERT INTO sx_ygo_decks VALUES(?,?,?,?,?,1)');foreach($fixture['allDecks'] as $d)$q->execute([$d['id'],$d['name'],$d['format_codes'][0]??'',null,'']);$q->execute([999,'Empty test deck','tcg_advanced',null,'']);
    $q=$pdo->prepare('INSERT INTO sx_ygo_deck_cards VALUES(?,?,?,?,?,?)');foreach($fixture['cards'] as $c)$q->execute([$c['id'],$c['deck_id'],$c['card_name'],$c['quantity'],$c['section'],$c['ygoprodeck_id']??null]);
    require_once dirname(__DIR__).'/includes/decks/combo-creator.php';$inventory=ygo_combo_inventory($pdo,665);
    $q=$pdo->prepare('INSERT INTO sx_ygo_created_combos VALUES(?,?,?,?,?,?,?,?,?)');$q->execute(['test-combo-1234567',665,'User diagram',1,$inventory['inventory_sha256'],'{"nodes":[],"edges":[]}',json_encode($inventory['cards']),gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')]);
    $password=trim(stream_get_contents(STDIN));
    $config=['resource'=>getenv('MCP_TEST_RESOURCE'),'owner_password_hash'=>password_hash($password,PASSWORD_DEFAULT),'state_dir'=>dirname($dbPath).'/state'];
    file_put_contents(getenv('STATION_X_CHATGPT_CONFIG'),"<?php\nreturn ".var_export($config,true).";\n");
    require_once dirname(__DIR__).'/includes/chatgpt/data.php';$data=new YgoMcpData($pdo,getenv('MCP_TEST_EDITORIAL_APP'),'https://example.test/');
    foreach(['card_profiles','documents','verification'] as $section){$part=$data->guide(665,$section);if($part['total_characters']<5)throw new RuntimeException('Detailed published content missing.');}
    try{$pdo->exec("UPDATE sx_ygo_decks SET name='bad'");throw new RuntimeException('Read-only transaction did not protect the database.');}catch(PDOException $e){if(!str_contains($e->getMessage(),'readonly'))throw $e;}
    unset($data);echo "Isolated fixture and read-only guard ready.\n";exit;
}
function connect_store_common(string &$prefix,int &$entity): PDO { return new PDO('sqlite:'.getenv('MCP_TEST_DB')); }
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(str_starts_with($path,'/mcp/')){require dirname(__DIR__).'/mcp/index.php';exit;}
if($path==='/.well-known/oauth-authorization-server/mcp/oauth'){
    require dirname(__DIR__).'/includes/chatgpt/auth.php';header('Content-Type: application/json');echo json_encode(YgoMcpAuth::load()->metadata());exit;
}
return false;
