# Editable frontend sources

Edit the JavaScript feature fragments and complete CSS rule segments listed in `manifest.json`, then run `npm run build`. `npm run build:check` fails if tracked runtime bundles differ from these sources. The generated public URLs remain `assets/app.js` and `assets/app.css`.

The JavaScript fragments deliberately compile into the existing single closure. This preserves initialization order, shared state, event handlers and existing offline behavior; they must not be loaded as independent script tags. The manifest is an explicit composition order, not a claim that the legacy controllers are isolated ES modules.

New independent features and pure engines belong under `assets/modules/<feature>`. Migrate legacy controllers to those modules with characterization tests instead of introducing new shared globals. CSS segments keep complete rules/media blocks and the existing cascade; merging repeated overrides without visual tests can change the application.

Each feature source starts with its responsibility and dependency warning. The build strips that editing header to preserve the runtime bytes during this structural migration. Production does not need Node or the source fragments.
