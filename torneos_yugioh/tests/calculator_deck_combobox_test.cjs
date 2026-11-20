'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname,'..');
const source = p => fs.readFileSync(path.join(root,p),'utf8');
const workspace = source('assets/modules/play-calculator/workspace.js');
const css = source('assets/modules/play-calculator/workspace.css');
const engine = source('assets/modules/play-calculator/engine.js');
const ui = source('assets/modules/play-calculator/ui.js');
assert.doesNotThrow(()=>new Function(workspace),'Searchable deck picker must parse');
assert.doesNotThrow(()=>new Function(ui),'Calculator UI must parse');
for(const expected of [
  'role="combobox"', 'aria-controls="calculator-deck-options"',
  'role="listbox"', 'data-calculator-combobox-toggle',
  'data-calculator-options', 'input.addEventListener(\'input\'',
  'input.addEventListener(\'keydown\'', 'event.key === \'ArrowDown\'',
  'event.key === \'Enter\'', 'chooseDeck(d.id)',
  'row.disabled = !enabled', 'row.setAttribute(\'aria-disabled\'', 
  "const decks = [...(data.allDecks || [])]", '.filter(isTcgOrOcg)',
  "String(card.section || \"main\").toLowerCase() === \"main\"",
  "renderOptions(true,true)", "select.dispatchEvent(new Event('change'))"
]) assert(workspace.includes(expected), 'Missing searchable TCG/OCG behavior: '+expected);
assert(!workspace.includes('norm(d.name + " " + (d.team || ""))'), 'Search must never match teams');
assert(workspace.includes('row.disabled = !hasMain(d.id)'), 'Unavailable opponents must be disabled');
assert(workspace.includes('continueButton.disabled = !valid'), 'Cannot continue without loaded decks');
assert(css.includes('.calculator-combobox-option.is-unavailable'), 'Unloaded decks need distinctive styling');
assert(css.includes('background:#05080c!important'), 'Disabled deck rows must be black');
assert(engine.includes('"ocg_advanced"') && engine.includes('"ocg"'), 'OCG formats must be recognized by engine');
assert(ui.includes('"ocg_advanced"') && ui.includes('"ocg"'), 'OCG formats must be recognized by UI');
console.log('Searchable TCG/OCG deck selector and disabled-list contracts verified.');
