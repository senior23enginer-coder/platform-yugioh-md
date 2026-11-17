<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/simple_pdf.php';

$reflection = new ReflectionFunction('ygo_pdf_lines');
$lines = $reflection->invoke([['Deck', 'Equipo', 'Resultado'], ['Kewl Tune', 'Neon Pulse Syndicate', 'Victoria']]);
if (count($lines) < 4 || !str_contains(implode(' ', $lines), 'Kewl Tune')) {
    fwrite(STDERR, "El generador no produjo lineas de reporte.\n");
    exit(1);
}
if (ygo_pdf_text('(TCG) \\ prueba') !== '\\(TCG\\) \\\\ prueba') {
    fwrite(STDERR, "El escape PDF no es valido.\n");
    exit(1);
}
echo "OK: PDF lines and escaping\n";
