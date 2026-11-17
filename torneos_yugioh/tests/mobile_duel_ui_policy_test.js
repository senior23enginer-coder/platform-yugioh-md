const fs = require('fs');

const app = fs.readFileSync('assets/app.js', 'utf8');
const css = fs.readFileSync('assets/app.css', 'utf8');
const page = fs.readFileSync('index.php', 'utf8');

const checks = [
  ['brick limitado a los dos primeros turnos', app.includes('completedTurns < 2')],
  ['brick limitado al lado activo', app.includes("activeSide !== 'A'") && app.includes("activeSide !== 'B'")],
  ['resumen posterior de brick', page.includes('data-result-brick-summary') && app.includes('brickSummarySides')],
  ['procedencia automatica en vivo', app.includes("origin.value = 'captured'")],
  ['procedencia oculta solo en movil', css.includes('[data-result-capture-modal] [data-result-origin-field]')],
  ['boton movil Terminales', page.includes('data-result-terminals-open') && page.includes('>Terminales</h3>')],
  ['modal con terminal A y B', page.includes('Terminal A') && page.includes('Terminal B')],
  ['modal movil controlada por clase', app.includes("terminalsSheet.classList.toggle('is-open', open)")],
  ['datos de participantes ocultos al cerrar en movil', css.includes('.match-closed .result-participant-grid')],
  ['reloj inactivo oculto en movil', css.includes('.result-duel-timer.is-disabled') && css.includes('display: none !important')],
  ['resultado movil abierto desde boton', page.includes('data-result-outcome-open') && page.includes('Resultado y cierre')],
  ['modal de resultado controlada por duelo', app.includes('setOutcomeOpen') && app.includes("sheet.classList.toggle('is-open', selected)")],
  ['historial movil abierto desde boton', page.includes('data-result-history-open') && page.includes('data-result-history-sheet')],
  ['modal de historial conserva acciones Ver', page.includes('data-result-capture-duel-list') && app.includes('setHistoryOpen')],
];

const failed = checks.filter(([, ok]) => !ok).map(([name]) => name);
if (failed.length) {
  throw new Error(`Faltan politicas de UI movil: ${failed.join(', ')}`);
}

console.log(JSON.stringify({ ok: true, checks: checks.map(([name]) => name) }, null, 2));
