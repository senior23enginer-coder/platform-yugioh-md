(function (root, factory) {
  const api = factory();
  if (typeof module === "object" && module.exports) module.exports = api;
  else root.YgoDeckWorldsApi = api;
})(typeof globalThis === "undefined" ? window : globalThis, function () {
  "use strict";

  /**
   * Outgoing browser adapter. It owns transport only; world rules stay in model.js.
   * Injected dependencies make requests testable without DOM, storage or a real server.
   */
  function create({ fetch: send, getSearch, getToken }) {
    if (
      ![send, getSearch, getToken].every((value) => typeof value === "function")
    ) {
      throw Error("Faltan dependencias del cliente de Mundos.");
    }
    function url(deckId) {
      const query = new URLSearchParams({ api: "deck_attributes" });
      const current = new URLSearchParams(getSearch());
      if (deckId) query.set("deck_id", deckId);
      for (const key of ["public_season", "public_live"]) {
        if (current.has(key)) query.set(key, current.get(key));
      }
      return "?" + query;
    }
    async function request({ body, deckId } = {}) {
      const options = body
        ? {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "X-CSRF-Token": getToken(),
            },
            body: JSON.stringify(body),
            cache: "no-store",
          }
        : { cache: "no-store" };
      const response = await send(url(deckId), options);
      const data = await response.json();
      if (!response.ok || !data.ok) {
        throw Error(data.message || "No se pudieron consultar los mundos.");
      }
      return data;
    }
    return { request };
  }
  return { create };
});
