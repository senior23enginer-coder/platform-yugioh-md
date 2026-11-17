const fs = require('fs');
const vm = require('vm');

const source = fs.readFileSync(require('path').join(__dirname, '..', 'assets', 'app.js'), 'utf8').replace(/\r\n/g, '\n');
const start = source.indexOf('  function evaluateBestOfDuel(');
const end = source.indexOf('\n  function evaluateDuel(', start);
if (start < 0 || end < 0) throw new Error('No se encontro el evaluador BO en app.js.');

const context = { window: { YgoEventModels: require('../assets/event-models.js') } };
vm.runInNewContext(`${source.slice(start, end)}\nthis.evaluateBestOfDuel = evaluateBestOfDuel;`, context);

function assertResult(values, bestOf, expected) {
  const actual = context.evaluateBestOfDuel(values, bestOf);
  for (const [key, value] of Object.entries(expected)) {
    if (actual[key] !== value) {
      throw new Error(`${values.join(',')} BO${bestOf}: ${key} esperado ${value}, recibido ${actual[key]}`);
    }
  }
}

assertResult(['E', 'B', 'B', '', ''], 3, { state: 'FINALIZADA', winner: 'B', score: '0-2' });
assertResult(['A', 'E', 'A', '', ''], 3, { state: 'FINALIZADA', winner: 'A', score: '2-0' });
assertResult(['A', 'B', 'E', '', ''], 3, { state: 'EN CURSO', winner: '', score: '1-1' });
assertResult(['B', '', '', '', ''], 1, { state: 'FINALIZADA', winner: 'B', score: '0-1' });
assertResult(['B', 'A', 'B', 'A', ''], 5, { state: 'EN CURSO', winner: '', score: '2-2' });
assertResult(['B', 'A', 'B', 'A', 'B'], 5, { state: 'FINALIZADA', winner: 'B', score: '2-3' });

if (!source.includes('const persistedDuelCount = Array.isArray(match?.duels)')) {
  throw new Error('La UI no esta usando los duelos persistidos para habilitar el siguiente.');
}
if (!source.includes("status.textContent = hasWinner\n          ? (isNext ? 'Listo para guardar' : 'Completado')")) {
  throw new Error('El duelo local finalizado debe permanecer editable hasta guardarse.');
}

console.log('OK: BO3 cierra E,B,B y la UI no adelanta duelos sin persistir.');
