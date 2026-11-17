const fs = require('fs');
const path = require('path');
const assert = require('assert');

const root = path.resolve(__dirname, '..');
const php = fs.readFileSync(path.join(root, 'index.php'), 'utf8');
const js = fs.readFileSync(path.join(root, 'assets', 'app.js'), 'utf8');
const css = fs.readFileSync(path.join(root, 'assets', 'app.css'), 'utf8');

const tabNames = Array.from(php.matchAll(/data-qualifier-tab="([^"]+)"/g), (match) => match[1]);
assert.deepStrictEqual(tabNames, ['configurar', 'jugar', 'finalizar'], 'El clasificatorio debe exponer solo Ranking, Combates y Finalizar evento.');
assert(!php.includes('data-qualifier-panel="programar"'), 'No debe quedar una vista separada de duelos programados.');
assert(php.includes('<span>Combates</span>'), 'La vista unificada debe llamarse Combates.');
assert(php.includes('data-qualifier-ranking-order'), 'El ranking debe permitir ordenar por posicion.');
assert(php.includes('data-qualifier-match-order'), 'La tabla de combates debe permitir cambiar el orden.');
assert(php.includes("$row['team_logo']"), 'El ranking debe renderizar el logo persistido del equipo.');
assert(php.includes('Finalizar clasificatorio'), 'La vista final debe ejecutar el cierre real del evento.');

assert(js.includes('function sortQualifierRanking()'), 'Debe existir el orden ascendente y descendente del ranking.');
assert(js.includes("const order = qualifierMatchOrder?.value || 'pending_first'"), 'Los pendientes deben mostrarse primero por defecto.');
assert(js.includes("if (order === 'finished_first')"), 'Debe poder mostrar los finalizados primero.');
assert(js.includes('function qualifierMatchSide(match, side)'), 'Los lados del combate deben incluir identidad visual.');

assert(css.includes('grid-template-columns:repeat(3,minmax(0,1fr))'), 'La navegacion debe distribuir tres vistas.');
assert(css.includes('.team-inline-badge.has-image img'), 'Los logos deben tener reglas de imagen estables.');

console.log('OK qualifier workspace consolidation');
