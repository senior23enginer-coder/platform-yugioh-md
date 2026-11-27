'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../assets/source/javascript/01-bootstrap.js'), 'utf8');
const start = source.indexOf('  function routeTo(route, navigationMode =');
const end = source.indexOf('\n  function setEventFichaUrl(', start);
assert(start !== -1 && end > start, 'History-aware router must be present');
const code = source.slice(start, end);
assert(source.includes("window.addEventListener('popstate', restoreBrowserRoute)"));
assert(source.includes("window.addEventListener('hashchange', restoreBrowserRoute)"));
const entry = 'https://stationxtulua.com/yugioh/?event_id=21#dashboard';
let current = new URL(entry);
const stack = [entry], changes = [], events = [];
let position = 0;
const history = {
  state: { persisted: true },
  pushState(state, title, url) {
    stack.splice(position + 1);
    stack.push(new URL(url, current).href);
    position++;
    current = new URL(stack[position]);
    changes.push('push');
  },
  replaceState(state, title, url) {
    stack[position] = new URL(url, current).href;
    current = new URL(stack[position]);
    changes.push('replace');
  }
};
const buttons = ['dashboard','deck','estadisticas'].map(route => ({
  dataset: { route },
  classList: { toggle() {} },
  closest() { return null; }
}));
const screens = buttons.map(button => ({ dataset: { screen: button.dataset.route }, hidden: false }));
const store = {};
const ctx = {
  URL,
  screens, routeButtons: buttons, history,
  location: { get hash() { return current.hash; } },
  window: {
    get location() { return { href: current.href }; },
    scrollTo() {},
    dispatchEvent(e) { events.push(e.detail.target); },
    YgoStatisticsWorkspace: { open() {} }
  },
  document: { body: { classList: { remove() {} } } },
  localStorage: { setItem(k,v) { store[k] = v; } },
  CustomEvent: class { constructor(type, options) { this.detail = options.detail; } },
  activeEventTypeShortcut: ''
};
vm.createContext(ctx);
vm.runInContext(code + '\nthis.navigate = routeTo;', ctx);
ctx.navigate('deck');
assert.equal(current.hash, '#deck');
assert.equal(position, 1);
assert.equal(changes.at(-1), 'push');
assert.equal(current.search, '?event_id=21', 'Entity query parameters must survive');
ctx.navigate('estadisticas');
assert.equal(position, 2);
position--; current = new URL(stack[position]);
ctx.navigate(current.hash.slice(1), 'restore');
assert.equal(position, 1, 'Back restoration cannot push another entry');
assert.equal(screens.find(s => s.dataset.screen === 'deck').hidden, false);
position++; current = new URL(stack[position]);
ctx.navigate(current.hash.slice(1), 'restore');
assert.equal(position, 2, 'Forward restoration cannot push another entry');
assert.equal(screens.find(s => s.dataset.screen === 'estadisticas').hidden, false);
ctx.navigate('dashboard', 'replace');
assert.equal(position, 2, 'Bootstrapping must replace rather than push');
assert.equal(current.hash, '#dashboard');
ctx.navigate('dashboard');
assert.equal(position, 2, 'Selecting the current route must not duplicate history');
assert.equal(store.dml_screen, 'dashboard');
assert(events.includes('deck') && events.includes('estadisticas'));
console.log('Browser navigation: Back, Forward, direct-route and query preservation verified.');

const { create: createQueue } = require('../assets/source/javascript/shared/offline-queue.js');
let raw = null, uuid = null;
const queue = createQueue({
  key: 'queue',
  readValue: key => { assert.equal(key, 'queue'); return raw; },
  writeValue: (key, value) => { assert.equal(key, 'queue'); raw = value; },
  now: () => 123,
  random: () => 0.5,
  uuid: () => uuid
});
assert.deepEqual(queue.read(), []);
raw = 'invalid';
assert.deepEqual(queue.read(), []);
raw = '{"payload":[["match_id","4"]]}';
assert.deepEqual(queue.read(), [{id:'legacy-123',payload:[['match_id','4']]}]);
queue.write([{id:'saved',payload:[]}]);
assert.deepEqual(queue.read(), [{id:'saved',payload:[]}]);
assert.equal(queue.requestId(), 'offline-123-8');
uuid = 'uuid-from-adapter';
assert.equal(queue.requestId(), uuid);
console.log('Offline queue: invalid JSON, legacy migration, persistence and request IDs verified.');

const calendarPolicy = require('../assets/source/javascript/shared/calendar-policy.js');
const day = '2026-11-26';
assert.equal(calendarPolicy.matchTimingStatus(null, day, {}).key, 'done');
assert.equal(calendarPolicy.matchTimingStatus({estado:'FINALIZADA', competition_can_execute:false}, day, {}).key, 'done');
assert.equal(calendarPolicy.matchTimingStatus({competition_can_execute:'0'}, day, {}).key, 'competition_blocked');
assert.equal(calendarPolicy.matchTimingStatus({estado:'RUNNING', scheduled_on:'2026-11-25'}, day, {}).canCapture, false);
assert.equal(calendarPolicy.matchTimingStatus({estado:'RUNNING', scheduled_on:'2026-11-25'}, day, {allow_overdue_results:'yes'}).canCapture, true);
assert.equal(calendarPolicy.matchTimingStatus({scheduled_on:day}, day, {}).canCapture, true);
assert.equal(calendarPolicy.matchTimingStatus({scheduled_on:'2026-11-27'}, day, {}).canCapture, false);
assert.equal(calendarPolicy.matchTimingStatus({scheduled_on:'2026-11-27'}, day, {allow_early_results:'on'}).label, 'Adelantable');
assert.equal(calendarPolicy.matchTimingStatus({}, day, {}).key, 'unscheduled');
assert.equal(calendarPolicy.isPlayableDate(day, {valid_weekdays:'4'}), true);
assert.equal(calendarPolicy.isPlayableDate(day, {valid_weekdays:'1', special_days:day}), false);
assert.equal(calendarPolicy.isPlayableDate(day, {valid_weekdays:'1', special_days:day, allow_special_days:1}), true);
assert.equal(calendarPolicy.isPlayableDate('invalid', {}), false);
console.log('Calendar timing policy: priority, date permission and special weekdays verified.');

const fixturePolicy = require('../assets/source/javascript/shared/fixture-policy.js');
const originalFixture = JSON.parse(fs.readFileSync(path.join(__dirname, 'fixtures/browser-fixture-policy-originals.json'), 'utf8'));
const ruleData = {gameRules:[
  {id:1, scope:'general', priority_order:1},
  {id:2, scope:'all_matches', priority_order:5},
  {id:3, scope:'all_matches', priority_order:2},
  {id:4, scope:'friendly', priority_order:1},
  {id:5, scope:'match', active:0},
], competitions:[{id:7,game_rule_id:1}], divisions:{A:{decks:[[1,'A'],[2,'B'],[3,'C']]}}, allDecks:[{name:'A',team:'Team A'}]};
const fixtureMatches = [
  {a:'A',b:'B',result:{state:'FINALIZADA',winner:'A',score:'2-0'},values:['A','A']},
  {a:'B',b:'C',result:{state:'FINALIZADA',winner:'B',score:'0-3'},values:['A','B','A','B','B']},
  {a:'A',b:'C',result:{state:'FINALIZADA',winner:'E',score:'1-1'},values:['A','B']},
  {a:'C',b:'A',result:{state:'EN CURSO',winner:'',score:'0-0'},values:[]},
  {a:'missing',b:'A',result:{state:'FINALIZADA',winner:'A',score:'2-0'},values:['A','A']},
];
const allFixtureMatches = () => fixtureMatches;
const savedFixtureResult = match => match.values;
const evaluateFixture = (values, match) => match.result;
const fixtureContext = {data:ruleData,allMatches:allFixtureMatches,getSavedResult:savedFixtureResult,evaluateDuel:evaluateFixture};
vm.createContext(fixtureContext);
vm.runInContext(originalFixture.ruleSource + '\n' + originalFixture.standingSource + '\nthis.legacyRule = ruleForMatch; this.legacyStandings = computeStandings;', fixtureContext);
for (const match of [null,{}, {game_rule_id:5,competition_id:7}, {game_rule_id:4,competition_id:7}, {phase:'friendly'}, {phase:'league'}, {competition_id:7}]) {
  assert.deepEqual(fixturePolicy.ruleForMatch(match,ruleData),fixtureContext.legacyRule(match));
}
for (const division of ['A','missing']) {
  const actual = fixturePolicy.computeStandings(division,ruleData,allFixtureMatches,savedFixtureResult,evaluateFixture);
  assert.deepEqual(actual,JSON.parse(JSON.stringify(fixtureContext.legacyStandings(division))));
}
console.log('Fixture policy: native rule precedence and standings characterization verified.');

const timerPolicy = require('../assets/source/javascript/shared/duel-timer-policy.js');
assert.equal(timerPolicy.storageKey(null,2),'dml_duel_timer_v2_match_2');
assert.equal(timerPolicy.storageKey(9,1),'dml_duel_timer_v2_9_1');
assert.equal(timerPolicy.elapsed({baseElapsed:10,running:false},()=>{throw Error('clock must not be read')}),10);
assert.equal(timerPolicy.elapsed({baseElapsed:4,running:true,startedAt:1000},()=>4500),7);
assert.equal(timerPolicy.elapsed({baseElapsed:1199,running:true,startedAt:1000},()=>4000),1200);
let persistedTimer;
const timerState = {running:true,baseElapsed:4};
timerPolicy.persist(timerState,()=> 'timer',true,()=> 1234,(key,value)=>{persistedTimer=[key,JSON.parse(value)]});
assert.deepEqual(persistedTimer,['timer',{running:true,baseElapsed:4,updatedAt:1234,liveSynced:true}]);
timerPolicy.persist(null,()=>{throw Error('no key read')},false,()=>{throw Error('no clock read')},()=>{throw Error('no write')});
const lpPolicy = require('../assets/source/javascript/shared/lp-state-policy.js');
const defaultLp = {a:8000,b:8000,startA:8000,startB:8000,timer:0,turn:1,coin:'',dice:'',history:[],running:false};
const restored = lpPolicy.restore(defaultLp,()=>JSON.stringify({a:-2,b:'bad',timer:-4,turn:0,running:true,history:Array(15).fill('move')}));
assert.equal(restored.a,0); assert.equal(restored.b,8000); assert.equal(restored.timer,0);
assert.equal(restored.turn,1); assert.equal(restored.running,false); assert.equal(restored.history.length,12);
assert.deepEqual(lpPolicy.restore(defaultLp,()=>'{bad'),defaultLp);
assert.equal(lpPolicy.resultKey(5,0),'dml_result_lp_5_1');
let persistedLp;
lpPolicy.persist({...defaultLp,running:true},'lp',(key,value)=>{persistedLp=[key,JSON.parse(value)]});
assert.deepEqual(persistedLp,['lp',defaultLp]);
const transport = require('../assets/source/javascript/shared/browser-transport.js');
(async () => {
  const requests = [];
  const response = {ok:true,status:200,json:async()=>({ok:true,revision:7})};
  const fetchResponse = async (url, options)=>{requests.push([url,options]);return response;};
  const forms = [];
  const createFormData=()=>{const form=new Map();forms.push(form);return form;};
  const result = await transport.liveDuel({fetchResponse,createFormData,getUrl:()=>'/duel',matchId:9,revision:6,operation:'sync',payload:{a:1}});
  assert.equal(result.response,response); assert.deepEqual(result.data,{ok:true,revision:7});
  assert.deepEqual([...forms[0]],[['match_id',9],['actor','judge'],['operation','sync'],['revision','6'],['payload','{"a":1}']]);
  assert.equal(requests[0][1].method,'POST');
  await transport.liveDuel({fetchResponse,createFormData,getUrl:()=>'/duel',matchId:9,revision:0,operation:'',payload:{}});
  assert.equal(forms.length,1); assert.equal(requests[1][1].method,undefined);
  await transport.submitOffline(fetchResponse,'/form',new URLSearchParams({action:'save'}));
  assert.equal(requests[2][1].body,'action=save'); assert.equal(requests[2][1].redirect,'follow');
  await transport.pollLive(fetchResponse);
  assert.deepEqual(requests[3],['./?api=live',{cache:'no-store',credentials:'same-origin'}]);
  console.log('Duel timer, LP state and HTTP ports: isolated contracts verified.');
})().catch(error=>{console.error(error);process.exitCode=1;});

const calendarProjection = require('../assets/source/javascript/shared/calendar-projection.js');
const calendarOriginal = JSON.parse(fs.readFileSync(path.join(__dirname,'fixtures/browser-calendar-projection-originals.json'),'utf8'));
const calendarNorm = value => String(value || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').trim();
const calendarStatus = value => calendarNorm(value).replace(/[^a-z0-9]+/g,'_').replace(/^_+|_+$/g,'');
const calendarMatches = [
  {id:1,competition_id:7,scheduled_on:'2026-11-27',starts_at:'19:00:00',mesa:1,estado:'PENDIENTE',phase:'league',deck_a_id:10,deck_b_id:20,a:'Deck A',b:'Deck B'},
  {id:2,competition_id:7,scheduled_on:'2026-11-28',estado:'EN CURSO',phase:'league',deck_a_id:20,deck_b_id:30,a:'Deck B',b:'Deck C'},
  {id:3,competition_id:7,estado:'PENDIENTE',phase:'league',deck_a_id:30,deck_b_id:10,a:'Deck C',b:'Deck A'},
  {id:4,competition_id:8,scheduled_on:'2026-11-26',estado:'PENDIENTE',phase:'league',a:'Deck A',b:'Deck C'},
];
const calendarData = {
  competitions:[{id:7,active:1,status:'active',type:'league',event_ruleset:'tcg_advanced'},{id:8,active:1,status:'pending_event',type:'cup'},{id:9,active:0,status:'active'},{id:10,active:1,status:'draft'}],
  allDecks:[{id:10,team_id:100,name:'Deck A'},{id:20,team_id:200,name:'Deck B'},{id:30,team_id:100,name:'Deck C'}],
  fixtures:{A:[{ronda:1,matches:calendarMatches}]}, divisions:{A:{accent:'#123456'}},
  events:[{id:1,competition_id:7,event_date:'2026-11-27',title:'Evento',event_type:'league'},{id:2,competition_id:7,event_date:'2026-11-27',title:'Jornada 1',notes:'combates',event_type:'league'}],
  ffaMatches:[],
};
const calendarContext = {
  data:calendarData, normalize:calendarNorm, normalizeStatusKey:calendarStatus,
  allMatchList:()=>calendarMatches, alertableMatchList:()=>calendarMatches,
  serverToday:'2026-11-27', addDays:()=> '2026-11-28', isPlayableDate:date=>date!=='2026-11-26',
  styleForEvent:(type,accent)=>({label:type,accent:accent||'#123456'}),
  fixtureRoundsFor:division=>calendarData.fixtures[division],
  matchVersusLabel:match=>match.a+' vs '+match.b,
  calendarCompletion:matches=>matches.length?'incomplete':'empty',
  calendarFormatStyle:()=>({key:'tcg',accent:'#2f80ed'}),
  matchSideDecks:(match,side)=>[match[side]],
  calendarType:{value:'league'},calendarDivision:{value:''},calendarSearch:{value:'deck'},
  calendarProfileScope:{type:'team',id:100,label:'Team A'}
};
vm.createContext(calendarContext);
vm.runInContext(Object.values(calendarOriginal).join('\n')+'\nthis.originalCalendarEvents=calendarEvents;this.originalFilter=filteredCalendarEvents;this.originalAlerts=operationalAlertBuckets;this.originalScope=calendarMatchBelongsToScope;this.originalActive=activeCalendarCompetitions;',calendarContext);
const plainCalendar = value=>JSON.parse(JSON.stringify(value));
const activeCalendar = ()=>calendarProjection.activeCalendarCompetitions(calendarData,calendarNorm);
const projectedCalendar = ()=>calendarProjection.calendarEvents(calendarData,{...calendarContext,activeCalendarCompetitions:activeCalendar});
const scopedCalendar = (match,scope)=>calendarProjection.calendarMatchBelongsToScope(match,scope,calendarData,calendarContext.matchSideDecks,calendarNorm);
assert.deepEqual(activeCalendar(),plainCalendar(calendarContext.originalActive()));
assert.deepEqual(projectedCalendar(),plainCalendar(calendarContext.originalCalendarEvents()));
assert.deepEqual(calendarProjection.operationalAlertBuckets(calendarContext.alertableMatchList,calendarContext.addDays,calendarContext.serverToday,calendarStatus,calendarContext.isPlayableDate),plainCalendar(calendarContext.originalAlerts()));
for(const scope of [{type:'deck',id:10,label:'Deck A'},{type:'team',id:100,label:'Team A'},null]){
  for(const match of calendarMatches) assert.equal(scopedCalendar(match,scope),calendarContext.originalScope(match,scope));
}
assert.deepEqual(calendarProjection.filteredCalendarEvents({calendarEvents:projectedCalendar,type:'league',division:'',search:'deck',normalize:calendarNorm,calendarProfileScope:calendarContext.calendarProfileScope,calendarMatchBelongsToScope:scopedCalendar}),plainCalendar(calendarContext.originalFilter()));
console.log('Calendar projection: original events, alerts, visibility, filters and scopes verified.');
