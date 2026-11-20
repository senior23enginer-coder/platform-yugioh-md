'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const app = path.resolve(__dirname,'..');
const js = fs.readFileSync(path.join(app,'assets/modules/play-calculator/workspace.js'),'utf8');
const css = fs.readFileSync(path.join(app,'assets/modules/play-calculator/workspace.css'),'utf8');
assert.doesNotThrow(()=>new Function(js),'Calculator stage script must parse');
const clauses = [
  "data-calculator-duel-setup",
  "data-calculator-setup-opponent",
  "data-calculator-continue",
  "Continuar al tablero",
  "data-calculator-back-setup",
  "stationx:calculator-deck",
  "calculator_stage",
  "history.pushState(",
  "window.addEventListener('popstate'",
  "history.back()",
  "host.classList.toggle('calculator-stage-board'",
  "board.hidden = next!=='board'",
  "selectRival.dispatchEvent(new Event('change',{bubbles:true}))",
];
for (const clause of clauses) assert(js.includes(clause),'Missing two-stage navigation: '+clause);
assert(js.includes("compatible = decks.filter("),'Opponent must be limited to compatible deck formats');
assert(js.includes("continueButton.disabled = !valid"),'Continue must be disabled without a usable selection');
assert(js.includes("new URL(location.href).searchParams.get('calculator_stage')"),'History restoration must use explicit stage state');
assert(css.includes(".calculator-stage-board .calculator-deck-picker{display:none!important}"),'Setup picker must disappear in duel');
assert(css.includes(".calculator-stage-board [data-calculator-workspace-board] .play-workspace{order:-1"),'Actual duel board must appear before lengthy controls');
assert(css.includes('[data-calculator-workspace-board][hidden]'),'Hidden board must not occupy space during setup');
console.log('Calculator setup → duel navigation and browser-history contracts passed.');
