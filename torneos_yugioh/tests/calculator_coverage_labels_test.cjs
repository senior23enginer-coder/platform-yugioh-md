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
const deck = JSON.parse(fs.readFileSync(
  path.resolve(__dirname, "../assets/editorial/20261117-folder-delivery-v40/671.json"), "utf8"));
assert.equal(variants.length, deck.calculator.rules.length,
  "Runtime variants and portable deck count must stay synchronized");
const distinctIds = new Set(variants.map(r => r.id));
assert.equal(distinctIds.size, new Set(deck.calculator.rules.map(r => r.id)).size,
  "Stable effect IDs must not drift between runtime and canonical package");
const covered = new Set(deck.calculator.rules.map(r => r.actor?.cid).filter(Number.isInteger));
const identities = new Set(deck.cards.map(card => card.cid));
assert(covered.size <= identities.size, "Actor count cannot exceed Memento inventory identities");
console.log("Calculator coverage summary: "+distinctIds.size+" distinct effects, "+
  variants.length+" execution variants, "+covered.size+"/"+identities.size+
  " actor identities; counts alone never certify A09 routes.");
