<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/schedule_capacity.php';

$cases = [
    'clasificatorio solo conserva sus dos combates' => [[3, 0, 2, 20], 2],
    'clasificatorio cede un cupo a otro evento' => [[3, 2, 2, 20], 1],
    'clasificatorio no entra cuando tres eventos ocuparon el dia' => [[3, 3, 2, 20], 0],
    'evento de un combate comparte dia con clasificatorio' => [[3, 2, 1, 6], 1],
    'el ultimo combate no duplica cupos' => [[3, 0, 2, 1], 1],
];

foreach ($cases as $label => [$arguments, $expected]) {
    $actual = ygo_schedule_available_quota(...$arguments);
    if ($actual !== $expected) {
        fwrite(STDERR, $label . ': esperado ' . $expected . ', obtenido ' . $actual . PHP_EOL);
        exit(1);
    }
}

echo 'schedule capacity policy: OK' . PHP_EOL;
