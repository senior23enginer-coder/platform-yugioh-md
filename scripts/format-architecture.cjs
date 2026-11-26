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
  php + 'src/Infrastructure/Editorial',
  php + 'src/Infrastructure/Configuration/News',
  php + 'src/Infrastructure/Cache/CardCatalog',
  php + 'src/Infrastructure/Network/CardCatalog',
  php + 'src/Infrastructure/Http/CardCatalog',
  php + 'src/Infrastructure/Network/News',
  php + 'src/Infrastructure/Syndication',
  php + 'src/Infrastructure/Cache/News',
  php + 'src/Infrastructure/Persistence/News',
  php + 'src/Infrastructure/Persistence/Decklists',
  php + 'src/Infrastructure/Http/Decklists',
  php + 'src/Infrastructure/Http/DeckPackages',
  php + 'src/Infrastructure/Http/News',
  php + 'src/Infrastructure/Http/DeckWorlds',
  php + 'src/Infrastructure/Http/FormActions',
  php + 'src/Infrastructure/Persistence/DeckWorlds',
  php + 'src/Infrastructure/Persistence/Combos',
  php + 'src/Infrastructure/Persistence/Calendar',
  php + 'src/Infrastructure/Persistence/Seasons',
  php + 'src/Infrastructure/Persistence/Leagues',
  php + 'src/Infrastructure/Persistence/Settings',
  php + 'src/Infrastructure/Persistence/Teams',
  php + 'src/Infrastructure/Persistence/Decks',
  php + 'src/Infrastructure/Persistence/Competitions',
  php + 'src/Infrastructure/Persistence/Matches',
  php + 'src/Infrastructure/Persistence/Statistics',
  php + 'src/Infrastructure/Persistence/LoadedSeasonRepository.php',
  php + 'src/Infrastructure/Time',
  php + 'src/Infrastructure/Transactions',
  php + 'includes/decks/attributes.php',
  php + 'includes/decks/public-metadata.php',
  php + 'tests/deck_metadata_architecture_test.php',
  php + 'includes/decks/attributes-http.php',
  php + 'assets/modules/deck-attributes',
  php + 'tests/deck_worlds_application_test.php',
  php + 'tests/deck_worlds_persistence_test.php',
  php + 'tests/source_architecture_test.php',
  php + 'tests/domain_extractions_test.php',
  php + 'tests/combo_ports_test.php',
  php + 'tests/form_command_architecture_test.php',
  php + 'includes/schedule_capacity.php',
  php + 'includes/news.php',
  php + 'includes/news_http.php',
  php + 'tests/calendar_architecture_test.php',
  php + 'tests/news_architecture_test.php',
  php + 'tests/card_catalog_architecture_test.php',
  php + 'tests/decklist_architecture_test.php',
  php + 'includes/event_workflows.php',
  php + 'includes/event_qualification.php',
  php + 'includes/event_recovery.php',
  php + 'includes/event_teams.php',
  php + 'includes/free_for_all.php',
  php + 'includes/turns.php',
  php + 'includes/completion_times.php',
  php + 'includes/match_editor.php',
  php + 'includes/decks/combo-creator.php',
  php + 'includes/decks/combo-creator-details.php',
  php + 'includes/decks/combo-creator-http.php',
  php + 'includes/decks/combo-source.php',
  php + 'includes/statistics_v8.php',
  php + 'includes/statistics_multiplayer.php',
  php + 'includes/deck_builder.php',
  php + 'includes/league_configuration.php',
  php + 'includes/event_models.php',
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
      let formatted = original;
      for (let pass = 0; pass < 4; pass++) {
        const next = await prettier.format(formatted, options);
        if (next === formatted) break;
        formatted = next;
        if (pass === 3) throw Error('Formatting did not converge: ' + file);
      }
      fs.writeFileSync(file, formatted);
    }
  }
  if (failures) process.exitCode = 1;
  else console.log(`${check ? 'Verified' : 'Formatted'} ${selected.length} migrated architecture sources.`);
})().catch(error => { console.error(error.message); process.exitCode = 1; });
