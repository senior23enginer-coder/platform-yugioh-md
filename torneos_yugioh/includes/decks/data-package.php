<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/autoload.php';

/** Deck-only editorial packages. No SQL writes, archive extraction or executable uploads. */
function ygo_data_json(mixed $value): string {
    return \StationX\Yugioh\Domain\Decks\Packages\PackageFiles::json($value);
}
function ygo_data_read(string $path): array {
    return \StationX\Yugioh\Infrastructure\Editorial\DeckPackageJsonFile::read($path);
}
function ygo_data_path(string $name): string {
    return \StationX\Yugioh\Domain\Decks\Packages\PackageFiles::path($name);
}
function ygo_data_local(string $app,string $url): ?string {
    return (new \StationX\Yugioh\Infrastructure\Editorial\LocalDeckPackageMedia($app))->resolve($url);
}
function ygo_data_source_image(array $files,string $value): ?string {
    return \StationX\Yugioh\Domain\Decks\Packages\PackageFiles::sourceImage($files, $value);
}
function ygo_data_archive(string $path): array {
    return (new \StationX\Yugioh\Infrastructure\Editorial\ZipDeckPackageArchive())->read($path);
}
function ygo_data_sections(string $raw): array {
    return \StationX\Yugioh\Domain\Decks\Packages\MarkdownStudy::sections($raw);
}
function ygo_data_plain(string $s): string {
    return \StationX\Yugioh\Domain\Decks\Packages\MarkdownStudy::plain($s);
}
function ygo_data_photos(string $text,array $cards): array {
    return \StationX\Yugioh\Domain\Decks\Packages\MarkdownStudy::photos($text, $cards);
}
/** Preserve one explicit simulator contract; prose never becomes an executable effect. */
function ygo_data_calculator(string $raw,array $data,int $deckId): array {
    return \StationX\Yugioh\Domain\Decks\Packages\CalculatorContract::extract($raw, $data, $deckId);
}
/** Keep simulator metadata in the package, outside player-facing explanations. */
function ygo_data_without_calculator(string $raw): string {
    return \StationX\Yugioh\Domain\Decks\Packages\CalculatorContract::withoutContract($raw);
}
function ygo_data_markdown(string $raw,array $base,int $deckId): array {
    return \StationX\Yugioh\Domain\Decks\Packages\MarkdownStudy::parse($raw, $base, $deckId);
}
/** Presentation readiness is separate from legal rulings and simulator coverage. */
function ygo_data_presentation_quality(array $record): array {
    return \StationX\Yugioh\Domain\Decks\Packages\StudyValidation::presentationQuality($record);
}

/**
 * A01 profile contract is opt-in for newly certified GPT packages.
 * Older records remain readable but are not automatically certified A01-T4/T5.
 */
function ygo_data_validate_a01_profile(array $record): void {
    \StationX\Yugioh\Domain\Decks\Packages\StudyValidation::validateA01Profile($record);
}


/** Structured player guide: never certify a deck from editorial prose alone. */
function ygo_data_validate_player_guide(array $record): void {
    \StationX\Yugioh\Domain\Decks\Packages\StudyValidation::validatePlayerGuide($record);
}

function ygo_data_validate(array $record,array $inventory): void {
    \StationX\Yugioh\Domain\Decks\Packages\StudyValidation::validate($record, $inventory);
}
function ygo_data_reader(string $name,string $raw): string {
    return \StationX\Yugioh\Infrastructure\Editorial\DeckPackageReaderHtml::render($name, $raw);
}
/** Atomic pointer replacement under one global lock; immutable old revisions remain available. */
function ygo_data_publish(string $app,int $id,array $record,array $files,string $expected): array {
    $root=$app.'/assets/editorial';$registry=$root.'/strategy-content-state.json';
    $lock=fopen($root.'/.deck-data.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('No se pudo bloquear la publicación.');
    try{
        $state=ygo_data_read($registry);$old=$state['checked_studies'][(string)$id]??null;
        if(hash('sha256',ygo_data_json($old))!==$expected)throw new DomainException('El deck cambió desde la previsualización. Vuelve a seleccionar el ZIP.');
        $version='deck-data-'.$id.'-'.gmdate('YmdHis').'-'.bin2hex(random_bytes(4));$dir=$root.'/'.$version;
        if(!mkdir($dir,0755,true))throw new RuntimeException('No se pudo crear la versión del deck.');
        $prefix='./assets/editorial/'.$version.'/';$map=[];
        foreach($files as $name=>$bytes)if(preg_match('/\.(png|jpe?g|webp|gif)$/i',$name)){
            $info=@getimagesizefromstring($bytes);if(!$info||!in_array($info['mime'],['image/png','image/jpeg','image/webp','image/gif'],true))throw new InvalidArgumentException('Imagen inválida: '.$name);
            $ext=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp','image/gif'=>'gif'][$info['mime']];$dest=hash('sha256',$bytes).'.'.$ext;
            if(file_put_contents($dir.'/'.$dest,$bytes)!==strlen($bytes))throw new RuntimeException('No se pudo escribir la imagen.');$map[$name]=$prefix.$dest;
        }
        array_walk_recursive($record,static function(&$v)use($map){if(is_string($v)&&isset($map[$v]))$v=$map[$v];});
        // The technical Markdown is archived in the immutable source ZIP. Never publish a second HTML guide with audit details.
        unset($record['folder_delivery']['reader']);
        $record['folder_delivery']['source_file']='ZIP importado desde la ficha del deck';
        $record['folder_delivery']['imported_at']=gmdate('c');
        $json=ygo_data_json($record);if(file_put_contents($dir.'/'.$id.'.json',$json)!==strlen($json))throw new RuntimeException('No se pudo escribir el contenido.');
        // Keep the exact submitted package privately within a ZIP (HTML/SVG never served directly).
        $zip=new ZipArchive();if($zip->open($dir.'/source.zip',ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('No se pudo guardar el original.');
        foreach($files as $name=>$bytes)$zip->addFromString($name,$bytes);if(!$zip->close())throw new RuntimeException('No se pudo cerrar el original.');
        $entry=['path'=>$prefix.$id.'.json','cards'=>count($record['cards']),'routes'=>count($record['routes']),'sha256'=>hash('sha256',$json),'presentation'=>'curated-v62','source_zip'=>$prefix.'source.zip'];
        $state['checked_studies'][(string)$id]=$entry;$state['version']=$version;
        file_put_contents($dir.'/previous-entry.json',ygo_data_json($old));
        $temp=$registry.'.'.bin2hex(random_bytes(4)).'.tmp';$encoded=ygo_data_json($state);
        if(file_put_contents($temp,$encoded)!==strlen($encoded)||!rename($temp,$registry))throw new RuntimeException('No se pudo activar la versión; el contenido anterior sigue disponible.');
        return $entry;
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
