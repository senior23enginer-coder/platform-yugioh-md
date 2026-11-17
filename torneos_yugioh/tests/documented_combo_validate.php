<?php
declare(strict_types=1);
require __DIR__.'/../includes/decks/combo-creator.php';
$rows=json_decode(file_get_contents($argv[1]),true,128,JSON_THROW_ON_ERROR);
foreach($rows as $row){$clean=ygo_combo_document($row['document'],$row['inventory']);if($clean!=$row['document'])throw new RuntimeException('El guardado cambió el documento: '.$row['document']['id']);}
echo json_encode(['ok'=>true,'nativeDocumentsValidated'=>count($rows)]),PHP_EOL;
