const assert = require('assert/strict');
const fs = require('fs');
const path = require('path');
const {evaluateSequence} = require('../assets/event-models.js');
const data = JSON.parse(fs.readFileSync(process.argv[2] || path.join(require('os').tmpdir(),'ygo-model-sequences.json'),'utf8'));
for(const c of data.cases) {
 const js=evaluateSequence(c.values,c.best_of,c.custom?data.conditions:[]);
 assert.deepEqual({status:js.state,winner:js.winner||null,score:js.score,validation:js.validation},{status:c.result.status,winner:c.result.winner,score:c.result.score,validation:c.result.validation},JSON.stringify(c.values));
}
console.log(`PASS PHP/JavaScript parity: ${data.cases.length} cases, 364 sequences x BO1/BO3/BO5 x standard/CUSTOM_XP.`);
