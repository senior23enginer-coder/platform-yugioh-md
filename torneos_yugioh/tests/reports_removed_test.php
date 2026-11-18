<?php
declare(strict_types=1);
require __DIR__.'/../includes/catalogs.php';
function check(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
$app=dirname(__DIR__);$index=file_get_contents($app.'/index.php');
$dependencies=file_get_contents($app.'/includes/bootstrap/dependencies.php');
$endpoints=file_get_contents($app.'/includes/http/endpoints.php');
$presentation=$index.$dependencies;
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($app.'/includes/presentation')) as $file) {
    if($file->isFile() && $file->getExtension()==='php') $presentation.=file_get_contents($file->getPathname());
}
foreach(['includes/reports.php','includes/report_http.php','includes/report_formats.php','includes/report_view.php','assets/reports.js','assets/reports.css'] as $name)check(!file_exists($app.'/'.$name),'Exclusive file remains: '.$name);
foreach(['data-screen="reportes"','data-route="reportes"','ygo_report_http(','ygo_report_catalog(','ygo_catalog_reports(','assets/reports.js?','assets/reports.css?'] as $needle)check(!str_contains($presentation,$needle),'Reference remains: '.$needle);
check(str_contains($index,"http_response_code(410)"),'Retired endpoint must not fall through to an export.');
check(strpos($index,'http_response_code(410)')<strpos($index,"require_once __DIR__"),'Retired API must exit before loading DB.');
check(str_contains($endpoints,"if ($"."export === 'entity_report')"),'Shared entity export removed.');
check(str_contains($dependencies,"'/includes/simple_pdf.php'"),'Shared PDF removed.');
check(!isset(ygo_default_catalog_groups()['report']),'Default report catalogue remains.');
$db=new PDO('sqlite::memory:');$db->exec('CREATE TABLE sx_ygo_catalog_items(id INTEGER,season_id INTEGER,catalog_key TEXT,item_code TEXT,label TEXT,sort_order INTEGER,meta_json TEXT,icon TEXT,accent TEXT,active INTEGER)');
$db->exec("INSERT INTO sx_ygo_catalog_items VALUES(1,1,'report','fixture','Retirado',1,'{}','','',1),(2,1,'ruleset','tcg_advanced','TCG',1,'{}','','',1)");
$groups=ygo_load_catalog_groups($db,1);check(!isset($groups['report']),'Stored retired catalogue exposed.');check($groups['ruleset'][0]['label']==='TCG','Shared catalogue changed.');
check((int)$db->query('SELECT COUNT(*) FROM sx_ygo_catalog_items')->fetchColumn()===2,'Read projection deleted stored data.');
$sw=file_get_contents($app.'/service-worker.js');check(!str_contains($sw,'./assets/reports.'),'Removed assets still precached.');
echo json_encode(['ok'=>true,'exclusiveFilesRemoved'=>6,'noNavigationOrLoader'=>true,'retiredApiGuard'=>true,'sharedPdfPreserved'=>true,'catalogueFilteredWithoutMutation'=>true,'serviceWorkerClean'=>true]);
