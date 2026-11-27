<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Decks;

use InvalidArgumentException;

final class DeckTemplate
{
    public static function parse(string $text): array
    {
        if (strlen($text) > 131072 || str_contains($text, "\0") || !preg_match('//u', $text)) {
            throw new InvalidArgumentException('TXT demasiado grande o sin codificacion UTF-8 valida.');
        }
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;
        $lines = preg_split('/\R/u', $text) ?: [];
        $header = false;
        $section = '';
        $metadata = [];
        $cards = [];
        $sections = ['MAIN' => 'main', 'EXTRA' => 'extra', 'SIDE' => 'side', 'RESERVA' => 'tech'];
        foreach ($lines as $number => $raw) {
            $line = trim($raw);
            if ($line === '' || str_starts_with($line, '#') && $header) continue;
            if (!$header) {
                if ($line !== '# STATIONX-DECK-TEMPLATE/1') throw new InvalidArgumentException('Cabecera de plantilla no valida.');
                $header = true;
                continue;
            }
            if (preg_match('/^\[([A-Z]+)\]$/', $line, $match)) {
                $section = $match[1];
                if ($section !== 'DECK' && !isset($sections[$section])) throw new InvalidArgumentException('Seccion desconocida: ' . $section);
                continue;
            }
            if ($section === 'DECK') {
                if (!preg_match('/^([a-z_]+)=(.*)$/u', $line, $match) || !in_array($match[1], ['nombre','arquetipo','estilo','notas'], true) || isset($metadata[$match[1]])) {
                    throw new InvalidArgumentException('Campo de deck invalido en linea ' . ($number + 1));
                }
                $metadata[$match[1]] = trim($match[2]);
            } elseif (isset($sections[$section])) {
                if (!preg_match('/^([1-3]);([^;\r\n]{1,160})$/u', $line, $match)) {
                    throw new InvalidArgumentException('Carta invalida en linea ' . ($number + 1) . '. Usa cantidad;nombre.');
                }
                $name = trim($match[2]);
                if ($name === '') throw new InvalidArgumentException('Nombre de carta vacio.');
                $cards[] = ['section' => $sections[$section], 'quantity' => (int) $match[1], 'name' => $name];
            } else {
                throw new InvalidArgumentException('Contenido fuera de una seccion en linea ' . ($number + 1));
            }
        }
        if (!$header || empty($metadata['nombre']) || mb_strlen($metadata['nombre']) > 160 || count($cards) < 1 || count($cards) > 150) {
            throw new InvalidArgumentException('La plantilla requiere nombre y entre 1 y 150 lineas de cartas.');
        }
        foreach (['arquetipo', 'estilo', 'notas'] as $key) {
            if (mb_strlen($metadata[$key] ?? '') > ($key === 'notas' ? 2000 : 120)) throw new InvalidArgumentException('Campo demasiado largo: ' . $key);
        }
        return ['name' => $metadata['nombre'], 'archetype' => $metadata['arquetipo'] ?? '',
            'style' => $metadata['estilo'] ?? '', 'notes' => $metadata['notas'] ?? '', 'cards' => $cards];
    }
}
