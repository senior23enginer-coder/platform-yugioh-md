const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const app = fs.readFileSync(path.join(root, 'assets', 'app.js'), 'utf8');
const css = fs.readFileSync(path.join(root, 'assets', 'app.css'), 'utf8');

const requirements = [
  [app.includes("format.includes('tcg')") && app.includes("accent: '#2f80ed'"), 'TCG debe usar azul.'],
  [app.includes("format.includes('rush')") && app.includes("accent: '#ffd45f'"), 'Rush debe usar amarillo.'],
  [app.includes("format.includes('goat')") && app.includes("accent: '#ff8a00'"), 'GOAT debe usar naranja.'],
  [app.includes("const completionClass = completion === 'empty' ? '' : ` date-${completion}`"), 'El estado agregado debe aplicarse al cuadro del dia.'],
  [css.includes('.calendar-day.date-incomplete') && css.includes('.calendar-day.date-complete'), 'Deben existir estilos azul y verde para el dia.'],
  [!css.includes('.calendar-event.is-complete {\n  --accent: #22c97a;'), 'Una capsula finalizada no debe volverse verde.'],
  [app.includes("'pending event', 'pending_event'"), 'Los eventos futuros programados deben permanecer visibles.'],
  [app.includes("'pending configuration', 'pending_configuration'"), 'Las fechas pendientes de configuracion deben permanecer visibles.'],
  [app.includes("if (!events.length) return 'empty'"), 'Un evento pendiente sin cruces debe marcar el dia como incompleto.'],
  [!app.includes(".filter((event) => !event.competitionId || (event.matches || []).length > 0)"), 'No se deben ocultar eventos pendientes por no tener cruces.'],
];

for (const [condition, message] of requirements) {
  if (!condition) throw new Error(message);
}

console.log('calendar format colors: OK');
