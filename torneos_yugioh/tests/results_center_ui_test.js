const fs = require('fs');
const path = require('path');
const assert = require('assert');

const root = path.resolve(__dirname, '..');
const php = fs.readFileSync(path.join(root, 'index.php'), 'utf8');
const js = fs.readFileSync(path.join(root, 'assets', 'app.js'), 'utf8');
const css = fs.readFileSync(path.join(root, 'assets', 'app.css'), 'utf8');

assert(php.includes('<th>Programacion</th><th>Enfrentamiento</th><th>Estado</th><th>Marcador</th><th>Accion</th>'), 'El centro debe usar cinco columnas operativas.');
assert(php.includes('class="result-filter-search"'), 'El buscador debe ser el control principal.');
assert(php.includes('data-result-clear-filters'), 'Falta restablecer todos los filtros.');
assert(js.includes('class="result-schedule-cell"'), 'La programacion debe agrupar fecha, hora, fase, ronda y mesa.');
assert(js.includes('class="result-versus-cell"'), 'Ambos participantes deben compartir la columna de enfrentamiento.');
assert(js.includes("state === 'FINALIZADA' ? 'Revisar'"), 'La accion debe reflejar el estado del combate.');
assert(js.includes('colspan="5"'), 'El estado vacio debe respetar la nueva tabla.');
assert(css.includes('.result-versus-cell{display:grid'), 'Falta la distribucion visual del enfrentamiento.');
assert(css.includes('.result-score-value'), 'Falta jerarquia visual para el marcador.');

console.log('OK results center UI');
