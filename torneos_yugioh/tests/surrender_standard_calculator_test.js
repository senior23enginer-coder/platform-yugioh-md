const fs = require('fs');
const assert = require('assert');

const php = fs.readFileSync('index.php', 'utf8');
const app = fs.readFileSync('assets/app.js', 'utf8');
const player = fs.readFileSync('assets/player-duel.js', 'utf8');
const appCss = fs.readFileSync('assets/app.css', 'utf8');
const playerCss = fs.readFileSync('assets/player-duel.css', 'utf8');

assert(php.includes("$operation === 'surrender'"), 'Falta la operacion sincronizada de rendicion.');
assert(php.includes("$actor !== (string) $state['active_side']"), 'El servidor no restringe la rendicion al turno activo.');
assert(php.includes("return 'resultado_bloqueado_rendicion'"), 'El guardado final no rechaza una rendicion fuera de turno.');
assert(php.includes('Los LP solo pueden cambiar mientras el duelo esta en curso.'), 'Los LP podrian cambiar despues de una rendicion.');
assert(php.includes('data-player-surrender'), 'La pantalla movil no ofrece rendicion al jugador activo.');
assert(player.includes("mutate('surrender'"), 'La pantalla movil no sincroniza la rendicion.');
assert(player.includes("state.active_side !== side"), 'La pantalla movil no comprueba el turno antes de rendirse.');
assert(app.includes('validateSurrenderTurn'), 'La consola del juez no valida el turno de la rendicion.');

assert(php.includes('data-result-standard-calculator'), 'Falta la calculadora estandar de la consola del juez.');
assert(php.includes('data-player-standard-key'), 'Falta la calculadora estandar en las pantallas A/B.');
assert(app.includes('calculateStandardStep'), 'Falta la logica de calculadora estandar del juez.');
assert(player.includes('standardStep'), 'Falta la logica de calculadora estandar del jugador.');
assert(app.includes("operator === 'multiply'") && app.includes("operator === 'divide'"), 'La calculadora del juez no soporta multiplicacion y division.');
assert(player.includes("operator === 'multiply'") && player.includes("operator === 'divide'"), 'La calculadora movil no soporta multiplicacion y division.');
assert(appCss.includes('.result-standard-calculator'), 'Falta el estilo responsive de la calculadora estandar.');
assert(playerCss.includes('.player-standard-keypad'), 'Falta el estilo movil de la calculadora estandar.');

console.log('surrender_standard_calculator_test: ok');
