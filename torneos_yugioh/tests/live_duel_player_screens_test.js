const fs = require('fs');
const assert = require('assert');

const php = fs.readFileSync('index.php', 'utf8');
const app = fs.readFileSync('assets/app.js', 'utf8');
const player = fs.readFileSync('assets/player-duel.js', 'utf8');
const css = fs.readFileSync('assets/player-duel.css', 'utf8');

assert(php.includes('CREATE TABLE IF NOT EXISTS sx_ygo_live_duel_state'), 'Falta la tabla de sesión en vivo.');
assert(php.includes("(string) ($_GET['api'] ?? '') === 'duel_session'"), 'Falta el API de sesión en vivo.');
assert(php.includes("in_array($duelPlayerSide, ['A', 'B'], true)"), 'Las pantallas A/B no están declaradas.');
assert(php.includes('data-result-player-link="A"') && php.includes('data-result-player-link="B"'), 'La consola del juez no expone ambos enlaces móviles.');
assert(php.includes("$operation === 'end_turn'"), 'Falta el guardado transaccional del turno.');
assert(php.includes("$expectedRevision !== (int) $live['revision']"), 'Falta el bloqueo de concurrencia por revisión.');
assert(php.includes("$actor !== (string) $state['active_side']"), 'Un lado podría terminar el turno rival.');
assert(php.includes("$hasRecordedDrawReroll") && php.includes("$isHistoricalUnrecordedReroll"), 'Falta validar nuevos dados después de un empate.');
assert(app.includes("previousWinner === 'E' ? 'draw_reroll'"), 'El frontend no aplica la regla especial de empate.');
assert(app.includes('data-result-duel-reroll'), 'Falta el control para repetir dados.');
assert(app.includes('scheduleJudgeLiveSync'), 'La consola del juez no sincroniza sus cambios.');
assert(player.includes("mutate('end_turn'"), 'La pantalla del jugador no permite terminar su turno.');
assert(player.includes("mutate('lp_change'"), 'La pantalla del jugador no sincroniza la calculadora LP.');
assert(css.includes('@media (orientation: landscape)'), 'Falta optimización móvil horizontal.');

assert(php.includes("$history[0]['turn']") && php.includes("$history[0]['side']"), 'El backend no recupera el lado inicial desde el historial.');
assert(app.includes('inferStartsPlayer'), 'El frontend no recupera el lado inicial desde el historial.');

console.log('live_duel_player_screens_test: ok');
