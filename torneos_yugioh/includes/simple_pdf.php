<?php
declare(strict_types=1);

function ygo_pdf_text(string $value): string
{
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $ascii = $ascii === false ? $value : $ascii;
    return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $ascii);
}

function ygo_pdf_lines(array $rows, int $width = 118): array
{
    $lines = [];
    foreach ($rows as $row) {
        $line = implode(' | ', array_map(static fn($value): string => trim((string) $value), $row));
        foreach (explode("\n", wordwrap($line, $width, "\n", true)) as $wrapped) {
            $lines[] = ygo_pdf_text($wrapped);
        }
        $lines[] = '';
    }
    return $lines;
}

function ygo_output_simple_pdf(string $title, array $rows, string $filename): never
{
    $pageLines = array_chunk(array_merge([ygo_pdf_text($title), 'Station X Tulua - ' . date('Y-m-d H:i:s'), ''], ygo_pdf_lines($rows)), 48);
    $objects = [];
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $pageCount = count($pageLines);
    $kids = [];
    for ($index = 0; $index < $pageCount; $index++) {
        $kids[] = (3 + ($index * 2)) . ' 0 R';
    }
    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $pageCount . ' >>';
    foreach ($pageLines as $index => $lines) {
        $pageObject = 3 + ($index * 2);
        $streamObject = $pageObject + 1;
        $commands = ["BT", "/F1 9 Tf", "38 806 Td", "11 TL"];
        foreach ($lines as $line) {
            $commands[] = '(' . $line . ') Tj';
            $commands[] = 'T*';
        }
        $commands[] = 'ET';
        $stream = implode("\n", $commands);
        $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 ' . (3 + ($pageCount * 2)) . ' 0 R >> >> /Contents ' . $streamObject . ' 0 R >>';
        $objects[$streamObject] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
    }
    $fontObject = 3 + ($pageCount * 2);
    $objects[$fontObject] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
    ksort($objects);
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $number => $object) {
        $offsets[$number] = strlen($pdf);
        $pdf .= $number . " 0 obj\n" . $object . "\nendobj\n";
    }
    $xref = strlen($pdf);
    $maxObject = max(array_keys($objects));
    $pdf .= "xref\n0 " . ($maxObject + 1) . "\n0000000000 65535 f \n";
    for ($number = 1; $number <= $maxObject; $number++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$number] ?? 0);
    }
    $pdf .= "trailer\n<< /Size " . ($maxObject + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-z0-9._-]/i', '-', $filename) . '"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}
