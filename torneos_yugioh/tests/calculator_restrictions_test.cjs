const assert = require("node:assert/strict");
const Restrictions = require("../assets/modules/play-calculator/restrictions.js");
const Rules = require("../assets/modules/play-calculator/rules.js");
const Planner = require("../assets/modules/play-calculator/planner.js");
assert.equal(Restrictions.version, 1);
assert.equal(Restrictions.names.length, 10);
assert(Restrictions.names.includes("effectMonsterSummonBlocked"));
assert(Restrictions.names.includes("machineSynchroOnly"));
assert(Object.isFrozen(Restrictions.names));
const state = { turn: 2, _ruleFlags: { specialBlocked: true } };
const turn = Restrictions.apply(state, "noMoreSummons", true, "turn");
assert.equal(state._ruleFlags.noMoreSummons, undefined);
assert.equal(turn._ruleFlags.noMoreSummons, true);
assert.equal(turn._ruleFlagTurns.noMoreSummons, 2);
assert.equal(Restrictions.effective(turn).noMoreSummons, true);
const synchro=Restrictions.apply(state, "machineSynchroOnly", true, "turn");
assert.equal(Restrictions.effective(synchro).machineSynchroOnly,true);
assert.equal(Restrictions.effective({ ...synchro, turn: 3 }).machineSynchroOnly,undefined);
const heatWave=Restrictions.apply(state,"effectMonsterSummonBlocked",true,"next-own-draw");
assert.equal(Restrictions.effective(heatWave).effectMonsterSummonBlocked,true);
assert.equal(Restrictions.effective({...heatWave,turn:3,active:'opponent'}).effectMonsterSummonBlocked,true,
  'Heat Wave persists during the opponent intervening turn');
assert.equal(Restrictions.effective({...heatWave,turn:4,active:'self',phase:'draw'}).effectMonsterSummonBlocked,undefined,
  'Heat Wave expires at the next own Draw Phase');
assert.throws(()=>Restrictions.apply(state,"effectMonsterSummonBlocked",true),/Oleada de Calor/,
  'Heat Wave cannot be implemented as permanent or one-turn generic lock');
const lancea=Restrictions.apply(state,"banishBlocked",true,"turn");
assert.equal(Restrictions.effective(lancea).banishBlocked,true);
assert.equal(Restrictions.effective({...lancea,turn:3}).banishBlocked,undefined,
  "Banish lock must expire after Lancea's turn");
assert.throws(()=>Restrictions.apply(state,"banishBlocked",true),/Duracion/,
  "Lancea's banish restriction must not silently persist beyond one turn");
assert.equal(Restrictions.effective({ ...turn, turn: 3 }).noMoreSummons, undefined);
assert.equal(Restrictions.effective({ ...turn, turn: 3 }).specialBlocked, true);
assert.equal(Restrictions.effective({ ...turn, turn: 3 }, { noMoreSummons: true }).noMoreSummons, undefined,
  "Un bloqueo caducado no se reactiva por opciones heredadas");
assert.equal(Restrictions.effective(state, { specialBlocked: false }).specialBlocked, true);
assert.equal(Restrictions.effective({ turn: 2 }, { noMoreSummons: true }).noMoreSummons, true,
  "Los bloqueos externos expresos se conservan sin bandera persistida");
assert.equal(Restrictions.apply(state, "xyzOnly", true)._xyz, true);
assert.throws(() => Restrictions.apply(state, "unknown", true), /Restriccion/);
assert.throws(() => Restrictions.apply(state, "noExtra", true, "turn"), /Duracion/);
assert.throws(() => Restrictions.apply(state, "noMoreSummons", true), /Duracion/);
const checkpoint = Rules.checkpoint({ ...turn, tokens: [], lp: { self: 8000, opponent: 8000 },
  _used: [], _ruleEvents: [], _ruleUseTurns: {} });
assert.equal(checkpoint._ruleFlagTurns.noMoreSummons, 2);
checkpoint._ruleFlagTurns.noMoreSummons = 1;
assert.equal(turn._ruleFlagTurns.noMoreSummons, 2, "La instantánea no debe mutar el historial");
assert.equal(typeof Planner.solve, "function");
console.log(JSON.stringify({ ok: true, flags: Restrictions.names.length, scoped: true, immutable: true }));
