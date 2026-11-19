# Deployment gate: approved PR merged into `base`

The workflow `deploy-ftp-after-approved-pr.yml` runs only for a PR **merged**
into `base` with an active `APPROVED` review decision. An approval on an open PR
does not deploy. It first checks the generic calculator contract, then uploads
the contents of `torneos_yugioh/` (not the repository root).

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

The workflow does not run on every push to `base`, does not deploy before the
merge, and does not use `dangerous-clean-slate`. The FTP action can synchronize
previously tracked deleted files within its target: back up the remote
application before the first run. The production database and private
server-side configuration must remain on the server, not in Git.

This is a deployment mechanism, not proof that production works. After first
publication verify HTTP responses, asset loading, PHP logs, persistence, and
desktop/mobile behavior. The workflow has not connected to the hosting server
until its secrets are configured and a qualifying PR is merged.
