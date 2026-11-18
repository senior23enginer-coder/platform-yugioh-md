const fs = require('node:fs');
const path = require('node:path');
const prettier = require('prettier');
const root = path.resolve(__dirname, '..');
const check = process.argv.includes('--check');

// Whitelist migrated, pure PHP only. Mixed templates, generated bundles and editorial bytes are excluded.
const php = 'torneos_yugioh/';
const paths = [
  php + 'src/Domain',
  php + 'src/Application',
  php + 'src/Infrastructure/Composition',
  php + 'src/Infrastructure/Http/DeckWorlds',
  php + 'src/Infrastructure/Persistence/DeckWorlds',
  php + 'src/Infrastructure/Time',
  php + 'src/Infrastructure/Transactions',
  php + 'includes/decks/attributes.php',
  php + 'includes/decks/attributes-http.php',
  php + 'assets/modules/deck-attributes',
  php + 'tests/deck_worlds_application_test.php',
  php + 'tests/deck_worlds_persistence_test.php',
  php + 'tests/source_architecture_test.php',
];
function files(relative) {
  const file = path.join(root, relative);
  return fs.statSync(file).isDirectory()
    ? fs.readdirSync(file).flatMap(name => files(path.join(relative, name)))
    : /\.(php|js|css)$/.test(file) ? [file] : [];
}
(async () => {
  const selected = paths.flatMap(files);
  let failures = 0;
  for (const file of selected) {
    const options = { ...(await prettier.resolveConfig(file)), filepath: file };
    const original = fs.readFileSync(file, 'utf8');
    if (check) {
      if (!(await prettier.check(original, options))) {
        failures++;
        console.error('Unformatted source: ' + path.relative(root, file));
      }
    } else {
      fs.writeFileSync(file, await prettier.format(original, options));
    }
  }
  if (failures) process.exitCode = 1;
  else console.log(`${check ? 'Verified' : 'Formatted'} ${selected.length} migrated architecture sources.`);
})().catch(error => { console.error(error.message); process.exitCode = 1; });
