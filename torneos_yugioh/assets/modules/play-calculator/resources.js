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
  // Resolve physical cards by their chosen UID. No CID or deck name is assumed.
  function requireTokens(state, refs, names, eligible, message) {
    if (!state || !Array.isArray(state.tokens) || !refs ||
      !Array.isArray(names) || names.length === 0 ||
      names.some(name => typeof name !== "string" || !Number.isInteger(refs[name])))
      throw Error(message || "Recursos físicos no identificados");
    const found = names.map(name => state.tokens.find(t => t.uid === refs[name]));
    if (new Set(found.map(t => t?.uid)).size !== names.length ||
      found.some(t => !t || !eligible(t)))
      throw Error(message || "Recursos físicos no disponibles");
    return found;
  }
  return Object.freeze({ version: 1, MAX_LP, applyLP, requireTokens });
});
