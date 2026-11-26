<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decks\Packages;

use RuntimeException;
use StationX\Yugioh\Domain\Decks\Packages\PackageFiles;
use StationX\Yugioh\Domain\Decks\Packages\PackageFolder;
use StationX\Yugioh\Domain\Decks\Packages\CalculatorContract;
use StationX\Yugioh\Domain\Decks\Packages\MarkdownStudy;
use StationX\Yugioh\Domain\Decks\Packages\StudyValidation;
use StationX\Yugioh\Domain\Decks\Packages\PortableInventory;
use StationX\Yugioh\Domain\Decks\Packages\SafeMarkdownImages;

final readonly class ExportDeckPackage
{
    public function __construct(private DeckPackageMedia $media) {}

    public function execute(array $inventory, ?array $record, array $sources, array $saved): array
    {
        $media = $this->media;
        $id=(int)$inventory['deck']['id'];$folder=PackageFolder::folder($sources,$id,$inventory['deck']['name']);$files=[];
        foreach($sources as $path=>$bytes){
            if(in_array(basename($path),['stationx-deck.json','editor-revisions.json','LEEME_CHATGPT.txt'],true))continue;
            $parts=explode('/',$path);$offset=array_search($folder,$parts,true);
            $relative=$offset!==false?implode('/',array_slice($parts,$offset)):$folder.'/'.$path;
            $files[$relative]=$bytes;
        }
        if(!$record){
            $record=['schema'=>'station-x-curated-v62','deck_id'=>$id,'name'=>$inventory['deck']['name'],'format'=>match($inventory['format']){'rush'=>'rush_duel','goat'=>'goat','tcg'=>'tcg_advanced',default=>'unspecified'},'cards'=>[],'skills'=>[],'profile'=>[],'summary'=>[],'engine_maps'=>[],'routes'=>[],'decisions'=>[],'practice'=>[]];
            foreach($inventory['cards'] as $c)$record['cards'][]=['id'=>$id.':CARD:'.$c['id'],'name'=>$c['card_name'],'slots'=>[['card_id'=>$c['id'],'section'=>$c['section'],'quantity'=>$c['quantity']]],'image'=>'','full_image'=>'','fields'=>[],'sources'=>[],'synergy'=>['labels'=>[],'resource'=>'','relation'=>''],'related_routes'=>[],'text'=>''];
        }
        $sourceDocument='';foreach($files as $path=>$bytes)if(preg_match('/CONTENIDO_CURADO\.md$/i',$path))$sourceDocument=$path;
        if($sourceDocument===''){
            $sourceDocument=$folder.'/'.$folder.'_CONTENIDO_CURADO.md';
            $files[$sourceDocument]='# '.$record['name']."\n\nID: ".$id."\n\nActualizar los datos estructurados en datos/stationx-deck.json.\n";
        }
        array_walk_recursive($record,static function(&$value,$key)use($media,&$files,$folder){
            if(!is_string($value)||!in_array($key,['image','full_image'],true)||$value==='')return;
            $source=PackageFiles::sourceImage($files,$value);if($source){$value=$source;return;}
            $image=$media->read($value);if(!$image)throw new RuntimeException('No se pudo localizar una fotografía del deck.');
            $target=$folder.'/imagenes/importados/'.$image['sha256'].'.'.$image['extension'];$files[$target]=$image['bytes'];$value=$target;
        });
        $package=['schema'=>'station-x-deck-package/1','deck_id'=>$id,'deck_name'=>$inventory['deck']['name'],'package_root'=>$folder,
            'structure_reference'=>['deck'=>'Kashtira Pure','deck_id'=>665,'scope'=>'Structure only; never copy its cards, combos or rules to another deck.','sections'=>['profile','cards','skills','engine_maps','routes','decisions','practice'],'document_sections'=>'APARTADO_01 through APARTADO_10; preserve the full authored content.'],
            'content_mode'=>'auto','source_document'=>$sourceDocument,'inventory_sha256'=>$inventory['inventory_sha256'],'inventory'=>$inventory['cards'],'record'=>$record,
            'baseline'=>['record_sha256'=>hash('sha256',PackageFiles::json($record)),'source_sha256'=>hash('sha256',$files[$sourceDocument])],
            'instructions'=>'CONTRATO DE ENTREGA STATION X: devuelve el mismo ZIP con una sola carpeta de ESTE deck y datos/stationx-deck.json; no entregues scripts ni instrucciones para Codex. Mantén schema=station-x-deck-package/1, deck_id, package_root, inventory_sha256, inventory, baseline y source_document. Actualiza record con schema=station-x-curated-v62 y todas sus secciones: profile, cards, skills, summary, engine_maps, routes, decisions, practice. Las rutas A09 deben conservar IDs estables, pasos numerados, requisitos, recursos, estados y fotos vinculadas; las guias A10 son material didáctico y no reglas ejecutables. Para la calculadora usa únicamente record.calculator con schema=station-x-calculator/1, deck_id y format idénticos al record y rules declarativas admitidas; nunca conviertas prosa en reglas ni afirmes cobertura cuando rules esté vacío o falten operadores. Usa Kashtira Pure solo como referencia de organización; no copies cartas o combos. Conserva IDs, CID, cantidades, zonas Main/Extra/Side/Reserva, Skills e imágenes. El JSON estructurado es la fuente de la UI de combos, estrategias y calculadora: si editas JSON y Markdown, declara content_mode=structured y mantén la explicación documental coherente; content_mode=markdown solo permite extraer una parte de A09 y campos de cartas, no reconstruye automáticamente las secciones interactivas. No alteres inventario ni resultados, ni apliques listas de prohibidas/limitadas. Las revisiones nativas guardadas conservan prioridad. Devuelve un ZIP importable desde la web sin Codex ni scripts locales, y distingue claramente contenido documentado de efectos ejecutables no cubiertos.'];
        if($saved)$package['editor_revisions']=$saved;
        $files[$folder.'/datos/stationx-deck.json']=PackageFiles::json($package);
        foreach(array_keys($files) as $path)if(!str_starts_with($path,$folder.'/'))throw new RuntimeException('El ZIP no quedó contenido en una sola carpeta.');
        return $files;
    }
}
