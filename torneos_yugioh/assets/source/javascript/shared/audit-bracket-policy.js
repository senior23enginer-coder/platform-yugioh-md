/**
 * Pure audit and bracket projections; browser adapters supply data.
 */
// @runtime-start
(function (root) {
  "use strict";

  function auditHidden(filters, row, normalize) {
    const query = normalize(filters.query);
    const kind = normalize(filters.kind);
    const entity = normalize(filters.entity);
    const action = normalize(filters.action);
    const text = normalize(row.text);
    const rowEntity = normalize(row.entity);
    const rowAction = normalize(row.action);
    return Boolean(
      (query && !text.includes(query)) ||
        (kind && !text.includes(kind)) ||
        (entity && rowEntity !== entity) ||
        (action && rowAction !== action),
    );
  }

  function pairRows(names) {
    const rows = [];
    for (let i = 0; i < names.length; i += 2)
      rows.push([names[i], names[i + 1] || "Pasa directo"]);
    return rows;
  }

  function worldGroups(pool) {
    return Array.from({ length: 6 }, (_, group) =>
      pool.slice(group * 4, group * 4 + 4),
    );
  }

  root.StationXAuditBracketPolicy = { auditHidden, pairRows, worldGroups };
})(typeof window !== "undefined" ? window : globalThis);
