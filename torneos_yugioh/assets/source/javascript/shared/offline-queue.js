/** Independent offline queue storage adapter and request ID policy. */
// @runtime-start
(function (root) {
  "use strict";
  function create({ key, readValue, writeValue, now, random, uuid }) {
    function read() {
      try {
        const value = JSON.parse(readValue(key) || "[]");
        if (Array.isArray(value)) return value;
        return value && Array.isArray(value.payload)
          ? [{ id: value.id || `legacy-${now()}`, ...value }]
          : [];
      } catch {
        return [];
      }
    }
    function write(queue) {
      writeValue(key, JSON.stringify(queue));
    }
    function requestId() {
      const id = uuid();
      return id === null
        ? `offline-${now()}-${random().toString(16).slice(2)}`
        : id;
    }
    return Object.freeze({ read, write, requestId });
  }
  const api = Object.freeze({ create });
  root.StationXOfflineQueue = api;
  if (typeof module === "object" && module.exports) module.exports = api;
})(typeof window !== "undefined" ? window : globalThis);
