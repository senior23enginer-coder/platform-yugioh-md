const fs = require('fs');
const path = require('path');
const assert = require('assert');

const root = path.resolve(__dirname, '..');
const php = fs.readFileSync(path.join(root, 'index.php'), 'utf8');
const css = fs.readFileSync(path.join(root, 'assets', 'app.css'), 'utf8');

assert(php.includes("$progressRow['ready'] = $progressRow['played'] >= $displayQualifierRequiredDuels;"), 'El cumplimiento debe depender de combates finalizados.');
assert(php.includes("$progressRow['remaining'] = max(0, $displayQualifierRequiredDuels - $progressRow['played']);"), 'Debe calcular cuantos combates faltan.');
assert(php.includes('<th>DIF</th><th>Combates</th>'), 'Ranking debe tener una sola columna de progreso.');
assert(!php.includes('<th>Programados</th><th>Finalizados</th><th>Rivales</th>'), 'No deben repetirse columnas de avance.');
assert(php.includes('qualifier-combat-progress'), 'Falta el progreso N/total en ranking.');
assert(php.includes('<th>Equipo / Deck</th><th>Cumplimiento de combates</th>'), 'Finalizar evento debe mostrar solo identidad y cumplimiento.');
assert(php.includes('qualifier-completion-badge'), 'Falta el indicador verde/amarillo de cumplimiento.');
assert(css.includes('.qualifier-completion-table tbody tr.is-complete'), 'Falta resaltar completos en verde.');
assert(css.includes('.qualifier-completion-table tbody tr.is-pending'), 'Falta resaltar pendientes en amarillo.');

console.log('OK qualifier completion UI');
