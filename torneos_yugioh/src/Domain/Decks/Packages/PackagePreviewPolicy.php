<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Decks\Packages;

use DomainException;
use InvalidArgumentException;

final class PackagePreviewPolicy
{
    public static function pending(int $id, string $path, string $ticket, int $time, ?array $entry, array $inventory, array $saved): array
    {
        return [
            'id' => $id,
            'path' => $path,
            'ticket' => $ticket,
            'time' => $time,
            'registry' => hash('sha256', PackageFiles::json($entry)),
            'inventory' => $inventory['inventory_sha256'],
            'saved' => hash('sha256', PackageFiles::json($saved)),
        ];
    }

    public static function assertTicket(array $pending, int $id, string $ticket, int $now): void
    {
        if (($pending['id'] ?? 0) !== $id || !hash_equals($pending['ticket'] ?? '', $ticket) || $now - ($pending['time'] ?? 0) > 1800) {
            throw new InvalidArgumentException('La previsualización venció. Selecciona otra vez el ZIP.');
        }
    }

    public static function assertInventory(array $pending, array $inventory, array $saved): void
    {
        if ($pending['inventory'] !== $inventory['inventory_sha256'] || $pending['saved'] !== hash('sha256', PackageFiles::json($saved))) {
            throw new DomainException('Las cartas o una edición guardada cambiaron. Previsualiza de nuevo.');
        }
    }

    public static function structured(array $files): bool
    {
        foreach (array_keys($files) as $name) {
            if (basename($name) === 'stationx-deck.json') {
                return true;
            }
        }
        return false;
    }

    public static function response(string $ticket, array $inventory, ?array $current, array $record, array $saved, bool $hasStructuredPackage): array
    {
        $before=array_column($current['routes']??[],null,'id');$new=0;$changed=0;
        foreach($record['routes'] as $r){if(!isset($before[$r['id']]))$new++;elseif(PackageFiles::json($r)!==PackageFiles::json($before[$r['id']]))$changed++;}
        return ['ticket'=>$ticket,'deck'=>$inventory['deck']['name'],'cards'=>count($record['cards']),'routes'=>count($record['routes']),'new_routes'=>$new,'changed_routes'=>$changed,'removed_routes'=>count(array_diff(array_keys($before),array_column($record['routes'],'id'))),'saved_revisions'=>count($saved),'inventory_changed'=>false,'candidate'=>$record,'native_cards'=>$inventory['cards'],'skills'=>$inventory['skills']??[],
            'compatibility'=>[
                'package'=>$hasStructuredPackage?'station-x-deck-package/1':'legacy-markdown-folder',
                'import_mode'=>$hasStructuredPackage?'estructurado':'documental',
                'warning'=>$hasStructuredPackage?null:'Este ZIP de carpeta GPT no contiene datos/stationx-deck.json. Conserva el Markdown y las rutas estructuradas existentes, pero las novedades A01–A10 no se transfieren íntegramente al editor visual. Exporta Data desde Station X para preparar un paquete estructurado completo.',
                'editorial'=>$record['schema'],
                'visual_routes'=>count($record['routes']),
                'visual_quality'=>StudyValidation::presentationQuality($record),
                'player_guide_status'=>(($record['player_guide']['schema']??null)==='station-x-player-guide/1')?'PERFIL_PEDAGOGICO_VALIDADO':'LEGADO_SIN_GUIA_PEDAGOGICA',
                'strategy_status'=>(($record['player_guide']['strategy']['schema']??null)==='station-x-player-strategy/1')?'ESTRUCTURA_OCHO_LECCIONES':'SIN_ESTRATEGIA_REVISADA',
                'semantic_acceptance'=>false,
                'calculator_schema'=>$record['calculator']['schema']??null,
                'calculator_rules'=>count($record['calculator']['rules']??[]),
                'calculator_status'=>empty($record['calculator']['rules'])?'SIN_REGLAS_DECLARATIVAS':'REQUIERE_VALIDACION_DEL_MOTOR',
            ]];
    }
}
