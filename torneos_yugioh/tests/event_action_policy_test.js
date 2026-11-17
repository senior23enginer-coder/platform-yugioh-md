const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const app = fs.readFileSync(path.join(root, 'assets', 'app.js'), 'utf8');
const php = fs.readFileSync(path.join(root, 'index.php'), 'utf8');

const requirements = [
  [app.includes('const validStart = startDateReached &&'), 'Comenzar debe exigir que la fecha haya llegado.'],
  [!app.includes('canStartScheduledLeagueEarly'), 'No debe existir excepcion de inicio anticipado para ligas.'],
  [app.includes("competitionType === 'league' && Number(competition.division_count || 0) > 1"), 'Ascensos y descensos solo se muestran con varias divisiones.'],
  [app.includes("((typeSelect?.value || '') === 'league' && ['season_playoffs', 'playoffs'].includes(executionModel))"), 'Una liga solo muestra siguiente fase si usa playoffs.'],
  [app.includes("programmingButton.dataset.programmingMode = viewOnly ? 'view' : 'edit'"), 'Programacion debe cambiar a consulta cuando ya hay cruces.'],
  [php.includes("if ((int) $divisionCountStmt->fetchColumn() < 2)"), 'El servidor debe bloquear movimientos con menos de dos divisiones.'],
  [php.includes("name=\"competition_division_ids[]\""), 'La creacion y ficha de liga deben permitir asociar varias divisiones.'],
];

for (const [condition, message] of requirements) {
  if (!condition) throw new Error(message);
}

console.log('event action policy: OK');
