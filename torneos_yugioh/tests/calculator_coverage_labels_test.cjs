const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const Verified = require("../assets/modules/play-calculator/verified-rules.js");

// The UI must not present generated variants as independent card effects
// or imply A09 replay certification from a rule count.
const source = fs.readFileSync(
  path.resolve(__dirname, "../assets/modules/play-calculator/ui.js"), "utf8");
assert(source.includes("activeEffectIds=new Set(activeRuleVariants.map(rule=>rule.id)).size"),
  "The active UI must deduplicate effects by stable rule ID");
assert(source.includes("variantes de ejecución"),
  "The active UI must distinguish variants from effects");
assert(source.includes("Ninguno de estos recuentos certifica una ruta completa"),
  "The active UI must distinguish documented paths from executable proof");
assert(!source.includes("reglas programadas disponibles"),
  "Do not overstate the number of individually executable effects");
const variants = Verified.forDeck(671);
assert.equal(variants.length, 233);
assert.equal(new Set(variants.map(r=>r.id)).size, 42);
console.log("Calculator coverage summary: 42 effect IDs, 233 variants, no route certification.");
