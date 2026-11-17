const assert=require('assert/strict'),fs=require('fs'),os=require('os'),path=require('path');
const {quotas}=require('../assets/event-admission.js');
const cases=JSON.parse(fs.readFileSync(path.join(os.tmpdir(),'ygo-admission-cases.json'),'utf8'));
for(const c of cases){if(c.valid)assert.deepEqual(quotas(c.rows,c.capacity),c.quotas);else assert.throws(()=>quotas(c.rows,c.capacity));}
console.log('PASS '+cases.length+' PHP/JS admission cases.');
