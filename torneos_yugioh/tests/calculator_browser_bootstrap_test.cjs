'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const app=path.resolve(__dirname,'..');
const footer=fs.readFileSync(path.join(app,'includes/presentation/layout/footer.php'),'utf8');
const order=[...footer.matchAll(/<script src="\.\/assets\/modules\/play-calculator\/([\w-]+)\.js\?v=[^"]+" defer><\/script>/g)]
  .map(match=>match[1]);
const required={
  engine:[],
  'hand-practice':['engine'],
  storage:[],
  operations:[],
  guards:[],
  resources:[],
  transitions:[],
  timing:[],
  events:[],
  restrictions:[],
  integrity:[],
  diagnostics:[],
  'chain-policy':[],
  rules:['engine','operations','guards','resources','transitions','timing','events','restrictions','integrity'],
  planner:['engine','rules','restrictions','integrity','diagnostics'],
  battle:['engine','planner'],
  scenario:['engine','planner','rules','battle'],
  'verified-rules':[],
  contract:['rules'],
  study:['engine','verified-rules','contract'],
  chain:['engine','rules','chain-policy','integrity','diagnostics'],
  ui:['engine','storage','planner','study','scenario','chain'],
};
for(const [module,deps] of Object.entries(required)){
 assert.equal(order.filter(name=>name===module).length,1,'Missing or duplicated browser calculator module '+module);
 for(const dep of deps)assert(order.indexOf(dep)<order.indexOf(module),
   'Browser module '+module+' loads before required '+dep);
}
const context=vm.createContext({console});
for(const mod of order.filter(name=>name!=='ui'&&name!=='card-menu'&&name!=='storage'&&name!=='hand-practice'&&name!=='workspace')){
 const source=fs.readFileSync(path.join(app,'assets/modules/play-calculator',mod+'.js'),'utf8');
 vm.runInContext(source,context,{filename:mod+'.js',timeout:2500});
}
assert.equal(typeof context.YgoPlayRules?.actions,'function','Browser must expose rules engine');
assert.equal(typeof context.YgoPlayPlanner?.solve,'function','Browser must expose planning engine');
assert.equal(typeof context.YgoScenarioAnalysis?.solve,'function','Browser must expose scenario analysis');
assert.equal(typeof context.YgoCalculatorStudy?.bind,'function','Browser must bind imported deck studies');
assert.equal(typeof context.YgoPlayChain?.resolve,'function','Browser must expose chain analysis');
assert(context.YgoCalculatorRestrictions.names.includes('machineSynchroOnly'),
  'Browser must load updated Machine Synchro restriction');
console.log('Calculator browser bootstraps all rule, planner, chain and scenario dependencies in order.');
