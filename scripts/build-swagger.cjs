const fs = require("node:fs");
const path = require("node:path");
const root = path.resolve(__dirname, "..");
const source = path.dirname(require.resolve("swagger-ui-dist/package.json"));
const target = path.join(root, "torneos_yugioh/api-docs/vendor");
const check = process.argv.includes("--check");
if (!check) fs.mkdirSync(target, { recursive: true });
for (const name of [
  "swagger-ui-bundle.js",
  "swagger-ui.css",
  "LICENSE",
  "NOTICE",
]) {
  const input = fs.readFileSync(path.join(source, name));
  const output = path.join(target, name);
  if (
    check &&
    (!fs.existsSync(output) || !fs.readFileSync(output).equals(input))
  ) {
    throw new Error("Stale Swagger resource: " + name);
  }
  if (!check) fs.writeFileSync(output, input);
}
console.log(
  check
    ? "Swagger resources match the pinned package."
    : "Copied pinned Swagger UI resources.",
);
