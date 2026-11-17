(function (root) {
  "use strict";
  const prefix = "stationx:playcalc:v1:";
  function read(key) {
    try {
      return JSON.parse(localStorage.getItem(prefix + key) || "null");
    } catch (_) {
      return null;
    }
  }
  function write(key, value) {
    try {
      localStorage.setItem(prefix + key, JSON.stringify(value));
      return true;
    } catch (_) {
      return false;
    }
  }
  root.YgoPlayStorage = {
    deck: (id) => read("deck:" + id),
    catalogs: () => read("catalogs") || [],
    saveDeck: (deck) => {
      const ok = write("deck:" + deck.id, deck);
      if (ok) {
        const ids = read("catalogs") || [];
        if (!ids.includes(deck.id)) write("catalogs", [...ids, deck.id]);
      }
      return ok;
    },
    self: () => read("self"),
    saveSelf: (id) => write("self", id),
    routes: (id) => read("routes:" + id),
    saveRoutes: (id, routes) => write("routes:" + id, routes),
  };
})(window);
