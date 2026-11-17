(function (root, factory) {
  if (typeof module === "object" && module.exports) module.exports = factory();
  else root.YgoComboModel = factory();
})(typeof window === "undefined" ? globalThis : window, function () {
  "use strict";
  const clone = (value) => JSON.parse(JSON.stringify(value));
  const uid = () =>
    globalThis.crypto?.randomUUID?.() ||
    `combo-${Date.now()}-${Math.random().toString(36).slice(2)}`;
  const norm = (value) =>
    String(value || "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase();
  function create(deckId) {
    return {
      schema_version: 1,
      id: uid(),
      deck_id: Number(deckId),
      kind: "combo",
      title: "",
      objective: "",
      conditions: "",
      turn_order: "any",
      nodes: [],
      edges: [],
    };
  }
  function ordered(doc) {
    const degree = new Map(doc.nodes.map((n) => [n.id, 0]));
    for (const e of doc.edges) {
      if (!degree.has(e.from) || !degree.has(e.to))
        throw Error("La conexión apunta a un paso que no existe.");
      degree.set(e.to, degree.get(e.to) + 1);
    }
    const queue = doc.nodes.filter((n) => !degree.get(n.id)),
      result = [];
    const sort = () =>
      queue.sort((a, b) => a.y - b.y || a.x - b.x || a.id.localeCompare(b.id));
    sort();
    while (queue.length) {
      const n = queue.shift();
      result.push(n);
      for (const e of doc.edges.filter((e) => e.from === n.id)) {
        degree.set(e.to, degree.get(e.to) - 1);
        if (!degree.get(e.to)) queue.push(doc.nodes.find((n) => n.id === e.to));
      }
      sort();
    }
    if (result.length !== doc.nodes.length)
      throw Error(
        "La conexión crea un ciclo. Añade un paso nuevo para continuar.",
      );
    return result;
  }
  function connect(doc, from, to, label = "") {
    if (from === to) throw Error("Selecciona dos pasos diferentes.");
    if (doc.edges.some((e) => e.from === from && e.to === to))
      throw Error("Los pasos ya están conectados.");
    const next = clone(doc);
    next.edges.push({ from, to, label });
    ordered(next);
    return next;
  }
  function addNode(doc, kind = "step", cardId = null, point = null) {
    if (doc.nodes.length >= 60) throw Error("La guía admite hasta 60 pasos.");
    const next = clone(doc),
      i = next.nodes.length;
    next.nodes.push({
      id: uid(),
      kind,
      title:
        kind === "opening"
          ? "Mano inicial"
          : kind === "result"
            ? "Campo final"
            : kind === "decision"
              ? "Decisión"
              : `Paso ${i + 1}`,
      action: "",
      explanation: "",
      cards: cardId ? [Number(cardId)] : [],
      x: point?.x ?? 40 + (i % 4) * 290,
      y: point?.y ?? 40 + Math.floor(i / 4) * 330,
    });
    return next;
  }
  function addCard(doc, nodeId, cardId, cards) {
    const next = clone(doc),
      node = next.nodes.find((n) => n.id === nodeId),
      card = cards.find((c) => Number(c.id) === Number(cardId));
    if (!node || !card) throw Error("Selecciona una carta del deck y un paso.");
    if (node.cards.length >= 8)
      throw Error("Un paso admite hasta ocho cartas.");
    if (node.kind === "opening" && card.section !== "main")
      throw Error("La mano inicial solo puede contener cartas del Main Deck.");
    if (
      node.cards.filter((id) => id === Number(cardId)).length >=
      Number(card.quantity)
    )
      throw Error("No hay más copias de esa carta en la lista.");
    node.cards.push(Number(cardId));
    return next;
  }
  function removeNode(doc, id) {
    const next = clone(doc);
    next.nodes = next.nodes.filter((n) => n.id !== id);
    next.edges = next.edges.filter((e) => e.from !== id && e.to !== id);
    return next;
  }
  return {
    clone,
    uid,
    norm,
    create,
    ordered,
    connect,
    addNode,
    addCard,
    removeNode,
  };
});
