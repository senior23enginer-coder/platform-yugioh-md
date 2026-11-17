# Canonical Yu-Gi-Oh repository

The GitHub repository intentionally keeps `torneos_yugioh/context/gpt/` empty except for `.gitkeep`. Never commit research deliveries or prompts there. Local imports may populate that ignored folder temporarily. Preserve existing deployed editorial runtime assets; do not delete them when cleaning source deliveries. Historical source-dependent tests are opt-in via `RUN_EDITORIAL_SOURCE_TESTS=1` and require the external inputs.

This repository is the source of truth for Station X's Yu-Gi-Oh platform. Save all platform code, tests, editorial inputs and maintained context here. Do not continue editing the old sibling `xstation/torneos_yugioh` copy.

- Read `README.md` and `torneos_yugioh/context/README.md` before changing a module.
- For deck updates, read `torneos_yugioh/context/editorial-sync-state.json`. The complete 11-deck delivery replaces the previous active content of those IDs. Keep other decks unchanged.
- Keep card quantities, Main/Extra/Side/reserve and Skills separate. Preserve verification states; do not invent legal certification or sporting results.
- Never commit connection credentials, private configuration, raw database backups, session tokens or generated caches.
- Run the relevant local checks. Production UI tests and database integration tests are separate; do not run mutation tests against production.
- A Git push is not a production deployment. Publish only when the user authorizes deployment.
- Preserve unrelated work and native transaction/history/audit boundaries. Do not create temporary public PHP maintenance scripts.
- Put new frontend features in `torneos_yugioh/assets/modules/<feature>/`; keep pure engines separate from UI and browser storage. Read `assets/modules/README.md`. New server views belong under `includes/<domain>/`. Keep editorial originals and generated data out of implementation folders and formatters.

## Documented combo changes

- When changing a combo from a prompt delivery, update the corresponding deck/route source under `torneos_yugioh/context/gpt/01_INFORMACION_A_IMPORTAR/` as part of the same task, together with its generated data and visual/editor representation. Preserve stable deck and route IDs and version the previous content in Git/native history.
- Use the specific A09/A10 deck or supplementary guide and its matching structured route in `GUIAS_Y_DECISIONES_V16.json`; update all affected representations rather than only changing the renderer. Shared prompt instructions under `02_PROMPTS_MAESTRO_Y_CODEX/` change when the shared requirement changes.
- Before reimporting, reconcile saved native editor revisions with their documented source so an older prompt cannot silently replace a player's edits. Report any source/platform difference that remains. Saving a native combo is not evidence that the local prompt files were updated; verify source writes separately before claiming synchronization.

- Native documented saves now version their source prompt transactionally. Before reimporting, synchronize and read `context/gpt/01_INFORMACION_A_IMPORTAR/revisiones_editor/index.json`; its graph revisions override historical route text. Run `tools/combo_source_sync.py` and verify status before asserting local source freshness.
