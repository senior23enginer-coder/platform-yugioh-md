/* Read-only coverage of the active local studies; never connects to a database. */
const fs = require("node:fs"),
  path = require("node:path");
const E = require("../assets/modules/play-calculator/engine.js"),
  Study = require("../assets/modules/play-calculator/study.js"),
  A = require("../assets/modules/play-calculator/scenario.js");
const base = require("../assets/modules/play-calculator/card-facts.json");
const app = path.resolve(__dirname, ".."),
  out = path.resolve(
    process.argv[2] ||
      path.join(app, "../outputs/calculator-scenario-20261118"),
  );
const registry = JSON.parse(
  fs.readFileSync(
    path.join(app, "assets/editorial/strategy-content-state.json"),
  ),
);
const decks = [];
for (const [id, entry] of Object.entries(registry.checked_studies)) {
  const study = JSON.parse(
    fs.readFileSync(path.join(app, entry.path.replace(/^\.\//, ""))),
  );
  const rows = study.cards.flatMap((c) =>
    c.slots.map((slot) => ({
      id: slot.card_id,
      deck_id: Number(id),
      card_name: c.name,
      quantity: slot.quantity,
      section: slot.section,
    })),
  );
  const catalog = E.catalog(
      { id: Number(id), name: study.name, format_codes: [study.format] },
      rows,
    ),
    db = Study.bind(base, [{ catalog, study }]);
  const s = E.create(catalog, null),
    audit = A.audit(s, db).sides[0];
  decks.push({
    id: Number(id),
    name: study.name,
    format: study.format,
    routes: study.routes.length,
    duelIdentities: catalog.cards.length,
    partialActions: audit.modelled,
    structuredRules: new Set(db.rules.map((r) => r.id)).size,
    missing: audit.cards
      .filter((c) => ["missing", "response-pending"].includes(c.scope))
      .map((c) => ({ cid: c.cid, name: c.name })),
    source: entry.path,
  });
}
fs.mkdirSync(out, { recursive: true });
fs.writeFileSync(
  path.join(out, "coverage.json"),
  JSON.stringify(
    {
      scope: "Local active editorial registry; not a live database audit",
      decks,
    },
    null,
    2,
  ),
);
const table = [
  "# Cobertura del calculador por deck",
  "",
  "Inventario editorial local. Contar una acción parcial no acredita todos los efectos de esa carta. Side y Reserva no entran al duelo. No se han certificado cadenas completas. La última columna cuenta cartas, no efectos. También deben completarse los efectos restantes de las cartas con soporte parcial.",
  "",
  "| Deck | Formato | Rutas documentales | Identidades Main/Extra | Alguna acción/respuesta parcial | Reglas declarativas | Cartas sin acción/respuesta implementada |",
  "|---|---|---:|---:|---:|---:|---:|",
  ...decks.map(
    (d) =>
      `| ${d.id} ${d.name} | ${d.format} | ${d.routes} | ${d.duelIdentities} | ${d.partialActions} | ${d.structuredRules} | ${d.missing.length} |`,
  ),
  "",
  ...decks.flatMap((d) => [
    `## ${d.id} ${d.name}`,
    "",
    "Completar efectos estructurados de las cartas sin soporte: " +
      d.missing
        .map(
          (c) =>
            c.name +
            (c.cid ? " (CID " + c.cid + ")" : " (identidad pendiente)"),
        )
        .join("; ") +
      ".",
    "",
  ]),
];
fs.writeFileSync(path.join(out, "COBERTURA.md"), table.join("\n"));
console.log(
  JSON.stringify({
    decks: decks.length,
    routes: decks.reduce((n, d) => n + d.routes, 0),
    report: path.join(out, "COBERTURA.md"),
  }),
);
