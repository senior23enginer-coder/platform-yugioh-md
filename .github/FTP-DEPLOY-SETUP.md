# Controlled FTPS deployment — explicit manual authorization

Merging a PR into `base` **never deploys automatically**. PRs have their own
checks; a release is deployed only when an operator runs
**Actions → Deploy Station X (manual approval) → Run workflow** on `base`.

- `mode: dry-run` (default): runs tests and verifies FTPS without uploading.
- `mode: deploy`: requires typing exactly `PUBLICAR` in `confirmation`.
  Otherwise the workflow fails before connecting to FTPS.
- Every run checks the application source, calculator contract, deck-template
  PHP importer and the full `npm test` suite before transferring files.
- Configure the GitHub `production` Environment with reviewers or branch
  restrictions for an additional independent control when available.

## Release and visible production version

Each successful manually authorized production upload generates a unique version tag
`v0.1.<GitHub run number>` and a `release.json` with its commit SHA and build
timestamp. The application footer reads this manifest from the production
filesystem and shows **Versión desplegada**. The manifest is transferred in a
separate final FTPS step, after application files have been uploaded, so a
failed application transfer cannot advertise the new version. GitHub Release
registration follows manifest publication. A failed Release registration
requires operator reconciliation; a successful FTP transfer cannot be rolled
back automatically. `dry-run` executions never publish a manifest or
create a release.

## GitHub configuration required before the first deployment

In repository **Settings → Secrets and variables → Actions**, create:

| Secret | Value |
| --- | --- |
| `FTP_HOST` | Hosting FTP hostname or IP **without** `ftp://` |
| `FTP_USERNAME` | Hosting FTP username |
| `FTP_PASSWORD` | A newly rotated FTP password |
| `FTP_SERVER_DIR` | Verified FTP-visible remote application path ending in `/yugioh/` |

Do **not** guess `FTP_SERVER_DIR` from the public URL. Depending on the FTP
account's root it may be `public_html/yugioh/` or a longer `domains/.../public_html/yugioh/`
path. Inspect the provider's FTP file manager and the existing application
before enabling deployment. Use a GitHub `production` environment with required
reviewers and protected secrets where the hosting plan supports it.

Port 21 uses **explicit FTPS** (`protocol: ftps`) for encrypted transfer.
Confirm FTPS is enabled for this account. Plain FTP is not configured because
it transmits credentials and file contents without transport encryption.

The workflow does not run on pushes or PR merges and does not use
`dangerous-clean-slate`. Only a manual `deploy` run with `PUBLICAR` uploads.
The FTP action can synchronize
previously tracked deleted files within its target: back up the remote
application before the first run. The production database and private
server-side configuration must remain on the server, not in Git.

This is a deployment mechanism, not proof that production works. After first
publication verify HTTP responses, asset loading, PHP logs, persistence, and
desktop/mobile behavior. The workflow has not connected to the hosting server
until its secrets are configured and a qualifying PR is merged.
