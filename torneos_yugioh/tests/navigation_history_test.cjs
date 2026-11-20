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
