<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/turns.php';

function assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL . 'Esperado: ' . var_export($expected, true) . PHP_EOL . 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$stored = [
    'turn_count' => 6,
    'turn_history' => array_map(static fn (int $turn): array => [
        'turn' => $turn,
        'side' => $turn % 2 ? 'A' : 'B',
        'ended_at' => "2026-09-22T10:0{$turn}:00.000Z",
        'elapsed_seconds' => $turn * 30,
    ], range(1, 6)),
];

$lowerAttempt = ygo_merge_monotonic_turns($stored, [
    'turn_count' => 5,
    'turn_history' => array_slice($stored['turn_history'], 0, 5),
]);
assert_same(6, $lowerAttempt['detail']['turn_count'], 'Un guardado atrasado no puede bajar de 6 a 5 turnos.');
assert_same(6, count($lowerAttempt['detail']['turn_history']), 'El historial guardado no puede perder entradas.');
assert_same([], $lowerAttempt['appended'], 'Un estado atrasado no debe crear auditoria nueva.');

$appendAttempt = ygo_merge_monotonic_turns($stored, [
    'turn_count' => 7,
    'turn_history' => array_merge($stored['turn_history'], [[
        'turn' => 7,
        'side' => 'A',
        'ended_at' => '2026-09-22T10:07:00.000Z',
        'elapsed_seconds' => 210,
    ]]),
]);
assert_same(7, $appendAttempt['detail']['turn_count'], 'El siguiente turno debe agregarse.');
assert_same(7, count($appendAttempt['detail']['turn_history']), 'El historial debe conservar las seis entradas y agregar la septima.');
assert_same(7, $appendAttempt['appended'][0]['turn'] ?? 0, 'La auditoria debe identificar solo el turno nuevo.');

echo "OK: los turnos son monotonicos y append-only." . PHP_EOL;
