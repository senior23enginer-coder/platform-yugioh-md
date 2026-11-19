<?php
declare(strict_types=1);
// Local browser test router only; never deployed. Uses the real module HTTP handler with SQLite.
$app=dirname(__DIR__);$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if ($path!=='/') return false;
function sx_start(): void { session_start();if(empty($_SESSION['sx_csrf']))$_SESSION['sx_csrf']=bin2hex(random_bytes(32)); }
function ygo_combo_fixture(): array {
    $data=json_decode(file_get_contents(__DIR__.'/fixtures/calculator_inventory.json'),true);
    if(getenv('COMBO_TEST_ROSTER')==='1')array_push($data['allDecks'],['id'=>99991,'name'=>'GOAT sin cartas','team'=>'Equipo prueba','format_codes'=>['goat']],['id'=>99992,'name'=>'TCG sin cartas','format_codes'=>['tcg_advanced']]);

    if(getenv('COMBO_TEST_STUDY_PHOTOS')==='1'){
        foreach([671,677] as $deckId){
            $study=json_decode(file_get_contents(__DIR__.'/../assets/editorial/20261117-folder-delivery-v40/'.$deckId.'.json'),true,512,JSON_THROW_ON_ERROR);
            $data['allDecks'][]=['id'=>$deckId,'name'=>$study['name'],'format_codes'=>['tcg_advanced']];
            foreach($study['cards'] as $card)foreach($card['slots'] as $slot){
                $data['cards'][]=[
                    'id'=>(int)$slot['card_id'], 'deck_id'=>$deckId,
                    'card_name'=>$card['name'], 'section'=>$slot['section'],
                    'quantity'=>(int)$slot['quantity'],
                    // Simulate a stale catalog entry plus regular native cards with no catalog ID.
                    'ygoprodeck_id'=>$deckId===671 && (int)$slot['card_id']===499 ? 999999 : null,
                ];
            }
        }
    }

    return $data;
}
function connect_store_common(string &$prefix,int &$entity): PDO {
    $pdo=new PDO('sqlite:'.getenv('COMBO_TEST_DB'),null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE TABLE IF NOT EXISTS sx_ygo_decks(id INT PRIMARY KEY,name TEXT); CREATE TABLE IF NOT EXISTS sx_ygo_deck_cards(id INT PRIMARY KEY,deck_id INT,card_name TEXT,quantity INT,section TEXT,ygoprodeck_id INT)');
    if (!$pdo->query('SELECT COUNT(*) FROM sx_ygo_decks')->fetchColumn()) {
        $data=ygo_combo_fixture();
        $q=$pdo->prepare('INSERT INTO sx_ygo_decks VALUES(?,?)');foreach($data['allDecks'] as $d)$q->execute([$d['id'],$d['name']]);
        $q=$pdo->prepare('INSERT INTO sx_ygo_deck_cards VALUES(?,?,?,?,?,?)');foreach($data['cards'] as $c)$q->execute([$c['id'],$c['deck_id'],$c['card_name'],$c['quantity'],$c['section'],$c['ygoprodeck_id']??null]);
    }
    return $pdo;
}
if (isset($_GET['api'])) { require_once $app.'/includes/decks/combo-creator-http.php';ygo_combo_http(); }
header('Content-Type: text/html;charset=utf-8');
?><!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Combo creator local test</title><link rel="stylesheet" href="./assets/modules/combo-creator/styles.css"><link rel="stylesheet" href="./assets/modules/combo-creator/step-lesson.css"><style>body{background:#071120;color:#e5effa;margin:0;font:14px system-ui}main{padding:20px;max-width:1800px;margin:auto}button{cursor:pointer}.btn{border:1px solid #387b97;background:#0c2f43;color:#a5ebef;padding:10px;border-radius:6px}.btn-primary{background:#0873ba;color:white}button:disabled{opacity:.4}</style><main><button type="button" data-route="creador_combos">Creador</button><div data-combo-creator></div><div data-deck-created-combos></div></main><script>window.DML_DATA=<?php echo json_encode(ygo_combo_fixture(),JSON_UNESCAPED_UNICODE|JSON_HEX_TAG); ?>;</script><script src="./assets/modules/combo-creator/model.js"></script><script src="./assets/modules/combo-creator/step-lesson.js"></script><script src="./assets/modules/combo-creator/ui.js"></script></html>
