# FTP deployment after tested merge

A PR **merged into `base`** automatically starts the deployment workflow.
There is no separate `APPROVED` review requirement, because repository owners
may merge their own PRs. Deployment still fails closed if a test fails.

- After merge: install dependencies, run `npm test`, calculator contract tests
  and PHP 8.3 TXT-import tests, then publish by FTP.
- No manual workflow trigger is configured: only merging a PR into `base` starts deployment.
- Any test or FTP failure stops the release. The application manifest is
  uploaded only after the application transfer succeeds.
- For additional governance, configure branch protection and required checks
  on `base` in GitHub Settings; a workflow alone does not prevent merging a
  PR whose check failed.

## Release and visible production version

Each successful automated production upload generates a unique version tag
`v0.1.<GitHub run number>` and a `release.json` with its commit SHA and build
timestamp. The application footer reads this manifest from the production
filesystem and shows **Versión desplegada**. The manifest is transferred in a
separate final FTP step, after application files have been uploaded, so a
failed application transfer cannot advertise the new version. GitHub Release
registration follows manifest publication. A failed Release registration
requires operator reconciliation; a successful FTP transfer cannot be rolled
back automatically.

## FTP passive connection preflight

Before uploading, the workflow performs a read-only FTP directory listing using
passive mode. This opens a separate data socket and fails early
with a clear message when the host rejects the PASV connection. It does **not**
write to production. The deployment action uses a 120-second FTP operation
timeout.

The observed initial failure after PR #6 was `ECONNRESET (data socket)`
while creating `assets/`, before any file bytes were uploaded. The file
count/size (approximately 3,327 files, 1.04 GB) does not explain a reset at
the first directory operation. If the preflight fails, hosting support must
check passive FTP data ports and firewalls for GitHub
Actions runner IPs. The owner explicitly selected plain FTP on port 21. This transmits credentials and file contents without transport encryption; rotate the FTP password after sharing it and limit account access to the application directory.

The workflow excludes `assets/uploads/**` because uploads are managed on the
hosting server; source-controlled placeholder images/logos are not used to
overwrite production user uploads. Other shipped assets remain included to
avoid breaking existing deck/strategy/image references. The first successful
sync is still large; monitor hosting quota and transfer limits.

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
before enabling deployment. Use a GitHub `production` environment without required deployment reviewers to avoid a manual deployment pause. Enforce PR reviews and required checks using branch protection on `base` instead.

Port 21 uses **plain FTP** (`protocol: ftp`), as explicitly requested by the repository owner. There is **no TLS encryption** on this transport: login credentials and uploaded files can be intercepted. Use a dedicated restricted FTP account and rotate its password regularly. Prefer FTPS if the hosting configuration is corrected in the future.

The workflow does not run on ordinary pushes and does not use
`dangerous-clean-slate`. A merged PR triggers production deployment only after
passing the workflow's tests.
The FTP action can synchronize
previously tracked deleted files within its target: back up the remote
application before the first run. The production database and private
server-side configuration must remain on the server, not in Git.

This is a deployment mechanism, not proof that production works. After first
publication verify HTTP responses, asset loading, PHP logs, persistence, and
desktop/mobile behavior. The workflow has not connected to the hosting server
until its secrets are configured and a qualifying PR is merged.
