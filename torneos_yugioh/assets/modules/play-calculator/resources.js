/* Pure, deck-independent resource arithmetic. Never mutates input state. */
(function (root, factory) {
  if (typeof module === "object" && module.exports) module.exports = factory();
  else root.YgoCalculatorResources = factory();
})(typeof globalThis !== "undefined" ? globalThis : this, function () {
  "use strict";
  const MAX_LP = 999999;
  function checkAmount(amount) {
    if (!Number.isInteger(amount) || amount <= 0 || amount > MAX_LP)
      throw Error("Cantidad de LP incorrecta");
  }
  function applyLP(lp, operation) {
    if (!lp || !Number.isInteger(lp.self) || !Number.isInteger(lp.opponent) ||
      lp.self < 0 || lp.opponent < 0 || lp.self > MAX_LP || lp.opponent > MAX_LP)
      throw Error("Estado de LP incorrecto");
    if (!operation || !["payLP", "damage", "recoverLP"].includes(operation.op))
      throw Error("Operacion de LP no implementada");
    checkAmount(operation.amount);
    const side = operation.op === "payLP" ? "self" : operation.side;
    if (!["self", "opponent"].includes(side)) throw Error("Jugador de LP incorrecto");
    const next = { ...lp };
    if (operation.op === "payLP") {
      if (next.self <= operation.amount) throw Error("LP insuficientes");
      next.self -= operation.amount;
    } else if (operation.op === "damage") {
      next[side] = Math.max(0, next[side] - operation.amount);
    } else {
      if (next[side] + operation.amount > MAX_LP)
        throw Error("LP exceden el limite de escenario");
      next[side] += operation.amount;
    }
    return next;
  }
  return Object.freeze({ version: 1, MAX_LP, applyLP });
});
