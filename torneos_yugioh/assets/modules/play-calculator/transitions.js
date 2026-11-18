/* Shared destination policies for a rule action; card rulings stay in rules.js. */
(function (root, factory) {
  if (typeof module === "object" && module.exports) module.exports = factory();
  else root.YgoCalculatorTransitions = factory();
})(typeof globalThis !== "undefined" ? globalThis : this, function () {
  "use strict";
  const ownerZones = Object.freeze(["graveyard", "banished", "deck", "extra"]);
  function assertDestination(to, options = {}, flags = {}) {
    if (typeof to !== "string" || !to) throw Error("Destino incorrecto");
    if ((options.blockedZones || []).includes(to)) throw Error("Zona bloqueada");
    if (flags.graveyardBlocked && to === "graveyard")
      throw Error("Destino de Cementerio pendiente por bloqueo");
    if (flags.banishBlocked && to === "banished")
      throw Error("Destierro impedido para ambos jugadores");
    return to;
  }
  function destinationController(token, to) {
    if (!token || !["self", "opponent"].includes(token.owner) ||
      !["self", "opponent"].includes(token.controller)) throw Error("Copia sin controlador");
    return ownerZones.includes(to) ? token.owner : token.controller;
  }
  function assertVacant(state, side, zone, excludeUid) {
    if (!state || !Array.isArray(state.tokens) || !["self", "opponent"].includes(side))
      throw Error("Estado de zona incorrecto");
    if (state.tokens.some(t => t.controller === side && t.zone === zone && t.uid !== excludeUid))
      throw Error("Zona ocupada");
    return true;
  }
  return Object.freeze({ version: 1, ownerZones, assertDestination, destinationController, assertVacant });
});
