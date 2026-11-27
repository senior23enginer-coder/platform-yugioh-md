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

    $pdo->exec('ALTER TABLE sx_ygo_decks ADD COLUMN division_id INTEGER; ALTER TABLE sx_ygo_decks ADD COLUMN created_at TEXT; CREATE TABLE sx_ygo_seasons(id INTEGER PRIMARY KEY,name TEXT); CREATE TABLE sx_ygo_competitions(id INTEGER PRIMARY KEY,season_id INTEGER,name TEXT,type TEXT,status TEXT,starts_on TEXT,ends_on TEXT,active INTEGER); CREATE TABLE sx_ygo_divisions(id INTEGER PRIMARY KEY,league_competition_id INTEGER,code TEXT,name TEXT,tier_order INTEGER,active INTEGER); CREATE TABLE sx_ygo_matches(id INTEGER PRIMARY KEY,competition_id INTEGER,division_id INTEGER,phase TEXT,round_number INTEGER,table_number INTEGER,deck_a_id INTEGER,deck_b_id INTEGER,deck_c_id INTEGER,status TEXT,scheduled_on TEXT,starts_at TEXT,venue TEXT)');
    $pdo->exec("INSERT INTO sx_ygo_seasons VALUES(1,'Test season'); INSERT INTO sx_ygo_competitions VALUES(10,1,'Test League','league','active',NULL,NULL,1),(11,1,'Test Cup','cup','active',NULL,NULL,1); INSERT INTO sx_ygo_divisions VALUES(20,10,'TEST','Test division',1,1); UPDATE sx_ygo_decks SET division_id=20,created_at='2026-11-27 12:00:00' WHERE id IN (665,999); INSERT INTO sx_ygo_matches VALUES(1,10,20,'league',1,1,665,999,NULL,'PENDIENTE','2026-11-29','19:00:00','Test venue'),(2,10,20,'league',2,1,999,665,NULL,'PENDIENTE',NULL,NULL,NULL),(3,11,20,'cup',1,1,665,999,NULL,'PENDIENTE','2026-11-30',NULL,NULL)");

    $password=trim(stream_get_contents(STDIN));
    $config=['resource'=>getenv('MCP_TEST_RESOURCE'),'owner_password_hash'=>password_hash($password,PASSWORD_DEFAULT),'state_dir'=>dirname($dbPath).'/state'];
    file_put_contents(getenv('STATION_X_CHATGPT_CONFIG'),"<?php\nreturn ".var_export($config,true).";\n");
    require_once dirname(__DIR__).'/includes/chatgpt/data.php';$data=new YgoMcpData($pdo,getenv('MCP_TEST_EDITORIAL_APP'),'https://example.test/');
    // Verify the real publication guard even when the detailed library is stale.
    $editorial=getenv('MCP_TEST_EDITORIAL_APP');
    $manifest=json_decode(file_get_contents($editorial.'/assets/editorial/deck-deliveries.json'),true,512,JSON_THROW_ON_ERROR);
    $guide=json_decode(file_get_contents($editorial.'/'.substr(parse_url($manifest['decks']['665']['url'],PHP_URL_PATH),2)),true,512,JSON_THROW_ON_ERROR);
    $library=json_decode(file_get_contents($editorial.'/assets/editorial/deck-library/deck-665.json'),true,512,JSON_THROW_ON_ERROR);
    $matching=(int)($library['deck_id']??0)===665&&!empty($guide['source_sha256'])&&hash_equals((string)$guide['source_sha256'],(string)($library['source_sha256']??''));
    foreach(['card_profiles','documents','verification'] as $section){
        try {
            $part=$data->guide(665,$section);
            if(!$matching||$part['total_characters']<5)throw new RuntimeException('Detailed library guard failed.');
        } catch(InvalidArgumentException $e) {
            if($matching||!str_contains($e->getMessage(),'No se mezclan entregas.'))throw $e;
        }
    }
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
