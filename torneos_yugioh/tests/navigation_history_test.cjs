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
