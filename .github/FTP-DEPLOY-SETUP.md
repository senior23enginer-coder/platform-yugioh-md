# FTPS deployment after tested merge

A PR **merged into `base`** automatically starts the deployment workflow.
There is no separate `APPROVED` review requirement, because repository owners
may merge their own PRs. Deployment still fails closed if a test fails.

- After merge: install dependencies, run `npm test`, calculator contract tests
  and PHP 8.3 TXT-import tests, then publish by FTPS.
- Manual **Actions → Deploy Station X after tested merge → Run workflow**
  on `base` is **dry-run only**, even with secrets configured: no upload.
- Any test or FTPS failure stops the release. The application manifest is
  uploaded only after the application transfer succeeds.
- For additional governance, configure branch protection and required checks
  on `base` in GitHub Settings; a workflow alone does not prevent merging a
  PR whose check failed.

## Release and visible production version

Each successful automated production upload generates a unique version tag
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

The workflow does not run on ordinary pushes and does not use
`dangerous-clean-slate`. A merged PR triggers production deployment only after
passing the workflow's tests. Manual runs remain dry-run.
The FTP action can synchronize
previously tracked deleted files within its target: back up the remote
application before the first run. The production database and private
server-side configuration must remain on the server, not in Git.

This is a deployment mechanism, not proof that production works. After first
publication verify HTTP responses, asset loading, PHP logs, persistence, and
desktop/mobile behavior. The workflow has not connected to the hosting server
until its secrets are configured and a qualifying PR is merged.
