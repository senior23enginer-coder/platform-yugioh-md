<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decks\Packages;

use InvalidArgumentException;
use StationX\Yugioh\Domain\Decks\Packages\PackageFiles;
use StationX\Yugioh\Domain\Decks\Packages\PackageFolder;
use StationX\Yugioh\Domain\Decks\Packages\CalculatorContract;
use StationX\Yugioh\Domain\Decks\Packages\MarkdownStudy;
use StationX\Yugioh\Domain\Decks\Packages\StudyValidation;
use StationX\Yugioh\Domain\Decks\Packages\PortableInventory;
use StationX\Yugioh\Domain\Decks\Packages\SafeMarkdownImages;

final readonly class PrepareDeckPackage
{
    public function __construct(private DeckPackageMedia $media) {}

    public function execute(array $files, array $inventory, ?array $current, array $sources): array
    {
        $media = $this->media;
        $verifiedFolder=PackageFolder::checkFolder($files,$sources,$inventory);
        $id=(int)$inventory['deck']['id'];$envelope=null;$envelopeName=null;
        foreach($files as $name=>$raw)if(basename($name)==='stationx-deck.json'){
            if($envelope)throw new InvalidArgumentException('El ZIP contiene varios decks. Exporta e importa un deck a la vez.');
            $envelope=json_decode($raw,true,128,JSON_THROW_ON_ERROR);$envelopeName=$name;
        }
        if($envelope){
            if(($envelope['schema']??'')!=='station-x-deck-package/1'||(int)($envelope['deck_id']??0)!==$id)throw new InvalidArgumentException('Este ZIP pertenece a otro deck.');
            $portable=($envelope['inventory_binding']??'')==='deck-slots-v1';
            if($portable){
                $document=$envelope['source_document']??null;
                // GPT folder packages may declare the root document relative to package_root.
                // Resolve only a single basename inside the verified deck folder; never search other folders.
                if(is_string($document)&&basename($document)===$document&&isset($files[$verifiedFolder.'/'.$document])){
                    $document=$verifiedFolder.'/'.$document;
                    $envelope['source_document']=$document;
                }
                if(!is_string($document)||!isset($files[$document])||!preg_match('/CONTENIDO_CURADO\\.md$/i',$document))throw new InvalidArgumentException('El ZIP GPT no contiene su documento curado original.');
                PortableInventory::validate($envelope,$inventory,$verifiedFolder,$current);
            }elseif(($envelope['inventory_sha256']??'')!==$inventory['inventory_sha256']){
                throw new InvalidArgumentException('La lista de cartas cambió. Exporta la data actual y pide a ChatGPT actualizar ese paquete.');
            }
            $record=$envelope['record']??null;if(!is_array($record))throw new InvalidArgumentException('El paquete no contiene data del deck.');
            // Explicitly choose the structured document or Markdown as authority, never guess between conflicting edits.
            $mode=$envelope['content_mode']??'structured';
            if($mode==='auto'){
                $source=$envelope['source_document']??'';$baseline=$envelope['baseline']??[];
                if(!isset($files[$source],$baseline['record_sha256'],$baseline['source_sha256']))throw new InvalidArgumentException('Falta el documento o la referencia de la exportación automática. Exporta de nuevo la carpeta del deck.');
                $changedRecord=hash('sha256',PackageFiles::json($record))!==$baseline['record_sha256'];$changedSource=hash('sha256',$files[$source])!==$baseline['source_sha256'];
                if($changedRecord&&$changedSource)throw new InvalidArgumentException('El JSON y el Markdown fueron modificados. Pide a ChatGPT indicar content_mode=structured o markdown para elegir la fuente principal.');
                $mode=$changedSource?'markdown':'structured';
            }
            if(!in_array($mode,['structured','markdown'],true))throw new InvalidArgumentException('content_mode debe ser auto, structured o markdown.');
            if($mode==='markdown'){
                $source=$envelope['source_document']??'';
                if(!is_string($source)||!isset($files[$source])||!str_ends_with($source,'.md'))throw new InvalidArgumentException('Falta el documento Markdown declarado.');
                $record=MarkdownStudy::parse(SafeMarkdownImages::normalize($files[$source],$files),$record,$id);
            }
            $source=$envelope['source_document']??'';
            if(is_string($source)&&isset($files[$source]))$record['imported_source_text']=SafeMarkdownImages::normalize($files[$source],$files);
        }else{
            // Existing ChatGPT folder deliveries: resolve exact source filename, including owner-confirmed aliases.
            $expected=[];foreach($sources as $name=>$bytes)if(preg_match('/CONTENIDO_CURADO\.md$/i',$name))$expected[]=basename($name);
            $matches=[];foreach($files as $name=>$raw)if(in_array(basename($name),$expected,true))$matches[$name]=$raw;
            if(count($matches)!==1||!$current)throw new InvalidArgumentException('No se identificó inequívocamente este deck. Usa el ZIP de «Exportar data» como base para ChatGPT y conserva stationx-deck.json.');
            $record=MarkdownStudy::parse(SafeMarkdownImages::normalize(reset($matches),$files),$current,$id);
        }
        $record['name']=$inventory['deck']['name'];
        // A source package cannot replace a manually saved editor revision. The creator continues to use its native history.
        foreach($record['routes'] as &$route)foreach(['native_editor_document','native_editor_cards','native_editor_revision'] as $k)unset($route[$k]);unset($route);
        StudyValidation::validate($record,$inventory);
        if(!empty($portable) && $current && is_array($current['routes']??null)){
            $oldIds=array_column($current['routes'],'id');
            $newIds=array_column($record['routes'],'id');
            if(array_diff($oldIds,$newIds))throw new InvalidArgumentException('El ZIP GPT omite rutas ya existentes en Station X. No se sobrescribió el estudio.');
        }
        array_walk_recursive($record,static function(&$v,$k)use($files,$media){
            if(!is_string($v)||!in_array($k,['image','full_image'],true)||$v==='')return;
            $source=PackageFiles::sourceImage($files,$v);if($source)$v=$source;
            if(isset($files[$v])){if(!preg_match('/\.(png|jpe?g|webp|gif)$/i',$v))throw new InvalidArgumentException('Una fotografía no corresponde a una imagen admitida.');return;}
            if(!$media->exists($v))throw new InvalidArgumentException('Falta una imagen del paquete: '.$v);
        });
        return $record;
    }
}
