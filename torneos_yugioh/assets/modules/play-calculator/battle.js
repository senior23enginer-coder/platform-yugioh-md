/* Conditional battle arithmetic. No card effects or chain legality is inferred. */
(function (root, factory) {
  if (typeof module === "object" && module.exports)
    module.exports = factory(require("./engine.js"), require("./planner.js"), require("./restrictions.js"));
  else root.YgoBattleStudy = factory(root.YgoPlayEngine, root.YgoPlayPlanner, root.YgoCalculatorRestrictions);
})(typeof globalThis !== "undefined" ? globalThis : this, function (E, P, Restrictions) {
  "use strict";
  function inspect(s, db) {
    E.validate(s);
    const result = {
      available: false,
      attacks: [],
      directDamageCeiling: null,
      possibleLethalArithmetic: false,
      assumptions: [
        "Cálculo hipotético de ATK/DEF impresos, sin efectos, modificadores, protecciones ni respuestas.",
        "No certifica declaraciones legales de ataque, Battle Step, Damage Step ni OTK.",
      ],
      reason: "",
    };
    if (s.active !== "self" || s.phase !== "battle") {
      result.reason = "Selecciona tu Battle Phase para estudiar los enfrentamientos.";
      return result;
    }
    if (s.turn === 1 && s.goesFirst) {
      result.reason = "El jugador que comienza no puede entrar a Battle Phase en su primer turno.";
      return result;
    }
    if (s.format === "rush_duel") {
      result.reason = "Las particularidades de combate Rush aún no están verificadas por este módulo.";
      return result;
    }
    const facts = (t) => P.profile(E.definition(s, t.key, t.owner), db);
    const monsters = (side) => s.tokens.filter((t) =>
      t.controller === side && E.isMonster(t.zone));
    // A Goblin Summoned by Grand Breakout cannot attack this turn.
    // The lock expires by turn and is removed when the monster leaves the field.
    const attackers = monsters("self").filter((t) => t.position === "attack" &&
      t.attackLockedTurn !== s.turn);
    const locked=monsters("self").filter(t=>t.attackLockedTurn===s.turn);
    if(locked.length)result.assumptions.push(
      locked.length+" monstruo(s) con restricción de ataque de Gran Fuga omitidos este turno.");
    const defenders = monsters("opponent");
    const directAttackBlocked = !!Restrictions.effective(s).directAttackBlocked;
    result.available = true;
    if (!attackers.length) result.reason = monsters("self").some(t=>t.attackLockedTurn===s.turn)
      ? "El monstruo Invocado por Gran Fuga Moto Goblin no puede atacar este turno."
      : "No hay monstruos propios boca arriba en ataque.";
    for (const attacker of attackers) {
      const af = facts(attacker);
      if (!af || !Number.isInteger(af.atk) || af.atk < 0) {
        result.attacks.push({ attacker: attacker.uid, status: "unknown", reason: "ATK del atacante no acreditado." });
        continue;
      }
      if (!defenders.length) {
        if (directAttackBlocked) {
          result.attacks.push({ attacker: attacker.uid, attackerName: af.name,
            target: null, status: "blocked", reason:
              "S:P Pequeña Caballera impide ataques directos este turno." });
          continue;
        }
        result.attacks.push({ attacker: attacker.uid, attackerName: af.name, target: null,
          status: "conditional", damage: af.atk, reason: "Ataque directo hipotético si ningún efecto lo impide." });
        continue;
      }
      for (const target of defenders) {
        const df = facts(target), defense = target.position !== "attack";
        const value = defense ? df?.defense : df?.atk;
        if (target.position === "set" || !Number.isInteger(value) || value < 0) {
          result.attacks.push({ attacker: attacker.uid, attackerName: af.name, target: target.uid,
            status: "unknown", reason: "Estadística o identidad defensiva desconocida; no se revela una carta boca abajo." });
          continue;
        }
        const diff = af.atk - value;
        result.attacks.push({ attacker: attacker.uid, attackerName: af.name,
          target: target.uid, targetName: df.name, status: "conditional",
          damage: defense ? Math.max(0, diff) : Math.abs(diff),
          damagedSide: defense ? (diff > 0 ? "opponent" : "none") :
            diff > 0 ? "opponent" : diff < 0 ? "self" : "none",
          outcome: diff > 0 ? "target-destroyed" : diff < 0 ?
            (defense ? "no-destruction" : "attacker-destroyed") :
            (defense ? "no-destruction" : "both-destroyed"),
          reason: "Resultado aritmético condicional sin efectos de carta." });
      }
    }
    if (!defenders.length && attackers.length &&
        result.attacks.length === attackers.length &&
        result.attacks.every((a) => a.status === "conditional")) {
      result.directDamageCeiling = result.attacks.reduce((n, a) => n + a.damage, 0);
      result.possibleLethalArithmetic = result.directDamageCeiling >= s.lp.opponent;
    }
    return result;
  }
  return { inspect };
});
