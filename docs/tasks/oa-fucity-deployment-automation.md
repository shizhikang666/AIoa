# oa.fucity.cn Deployment Automation

> **Production cutover warning (2026-07-19):** do not use the in-place
> `oa-fucity-deploy.ps1` / `oa-fucity-remote-deploy.sh` path for the formal
> Java-to-PHP migration. It synchronizes into the active tree and invokes a
> schema installer. Use the prepare-only, `releases/current`, compare-and-swap
> workflow in `docs/tasks/oa-fucity-atomic-deployment.md`. The content below is
> retained as historical behavior until the legacy deploy path is retired.

Date: 2026-06-29

This runbook provides a controlled deploy path for `oa.fucity.cn`:

1. Build a clean local release package.
2. Upload the package over SSH/SCP.
3. Back up the server files and database.
4. Replace the live files under `/www/wwwroot/oa.fucity.cn`.
5. Run post-deploy checks.
6. Roll files back automatically when a deploy step fails.

The BaoTa site document root should stay:

```text
/www/wwwroot/oa.fucity.cn/public
```

## Local Build Only

Run from the project root:

```powershell
.\scripts\oa-fucity-build-release.ps1
```

Useful options:

```powershell
.\scripts\oa-fucity-build-release.ps1 -SkipFrontendBuild
.\scripts\oa-fucity-build-release.ps1 -ReleaseId 20260629-approval-1
```

`SkipFrontendBuild`, `SkipComposerInstall`, and `SkipReadiness` now mark the
artifact `diagnostic=true`. The atomic production prepare gate rejects every
diagnostic artifact; these switches are for local diagnosis only.

The script creates:

```text
F:\AI\projects\testJava\release\oa.fucity.cn-<release-id>
F:\AI\projects\testJava\release\oa.fucity.cn-<release-id>.zip
```

The release package intentionally excludes `.env`, `.git`, frontend source,
frontend `node_modules`, and runtime artifacts.

## Deploy After Approval

The deploy command refuses to run unless `-ConfirmDeploy` is supplied.

```powershell
.\scripts\oa-fucity-deploy.ps1 `
  -ServerHost <server-ip-or-host> `
  -ServerUser root `
  -SshKeyPath C:\path\to\id_rsa `
  -ConfirmDeploy
```

Password SSH can omit `-SshKeyPath`:

```powershell
.\scripts\oa-fucity-deploy.ps1 `
  -ServerHost <server-ip-or-host> `
  -ServerUser root `
  -ConfirmDeploy
```

Dry run:

```powershell
.\scripts\oa-fucity-deploy.ps1 `
  -ServerHost <server-ip-or-host> `
  -ServerUser root `
  -DryRun
```

Deploy an existing approved zip:

```powershell
.\scripts\oa-fucity-deploy.ps1 `
  -ServerHost <server-ip-or-host> `
  -ServerUser root `
  -ReleaseZip F:\AI\projects\testJava\release\oa.fucity.cn-20260629-153304.zip `
  -ConfirmDeploy
```

## Server Requirements

The remote script expects these commands on the Aliyun/BaoTa server:

```text
bash
php
mysqldump
tar
gzip
unzip
rsync
```

The server must already have:

```text
/www/wwwroot/oa.fucity.cn/.env
APP_DEBUG=false
```

The `.env` file is never uploaded from local packages. It is copied into the
temporary staging directory only for server-side boot checks.

## What Gets Backed Up

Before replacing files, the remote script writes:

```text
/www/wwwroot/oa.fucity.cn/.deploy/backups/files-<release-id>.tar.gz
/www/wwwroot/oa.fucity.cn/.deploy/backups/db-<release-id>.sql.gz
```

Database rollback is not automatic. The dump is preserved for manual restore,
because automatic database restore can overwrite new production data.

The newest 10 file backups and 10 database backups are kept by default.

## File Rollback

Automatic file rollback happens if a deploy step fails after the file backup is
created.

Manual rollback:

```bash
cd /www/wwwroot/oa.fucity.cn
bash scripts/oa-fucity-remote-deploy.sh \
  --target /www/wwwroot/oa.fucity.cn \
  --rollback-files /www/wwwroot/oa.fucity.cn/.deploy/backups/files-<release-id>.tar.gz
```

The rollback command creates a safety backup of the current failed state before
restoring the selected backup.

## CORS and Nginx

The production frontend currently uses a relative API prefix:

```text
VITE_API_BASEURL=/backend
VITE_API_PREFIX=/backend
```

That should be deployed as same-origin traffic through `https://oa.fucity.cn`.
Same-origin API traffic avoids browser CORS.

If the API is cross-origin, enable the CORS probe:

```powershell
.\scripts\oa-fucity-deploy.ps1 `
  -ServerHost <server-ip-or-host> `
  -ServerUser root `
  -ConfirmDeploy `
  -CheckCors
```

The remote script also writes a small Nginx CORS report after deploy:

```text
/www/wwwroot/oa.fucity.cn/.deploy/nginx-cors-<release-id>.txt
```

Review it for these signals:

```text
oa.fucity.cn
Access-Control-Allow-Origin
proxy_hide_header Access-Control-Allow-Origin
fastcgi_hide_header Access-Control-Allow-Origin
fucity
```

If a sibling-domain whitelist is used, include `oa` in the allowed origin regex.
For example:

```nginx
^https://(oa|jkyl|exam|manage|monitor)\.fucity\.cn$
```

Then test and reload Nginx from BaoTa or shell:

```bash
nginx -t
nginx -s reload
```

Use `-ReloadNginx` only when the deployment also changed Nginx config.

For this project the production frontend calls a relative API prefix:

```text
/backend
```

The deployment script can configure the BaoTa vhost so `oa.fucity.cn` serves
the frontend from:

```text
/www/wwwroot/oa.fucity.cn/snowy-admin-web/dist
```

and routes `/backend/...` to:

```text
/www/wwwroot/oa.fucity.cn/public/index.php
```

Use this only after the production `.env` is present:

```powershell
.\scripts\oa-fucity-deploy.ps1 `
  -ServerHost <server-ip-or-host> `
  -ServerUser root `
  -SshKeyPath C:\Users\Win10\.ssh\oa_fucity_deploy `
  -ReleaseZip F:\AI\projects\testJava\release\oa.fucity.cn-<release-id>.zip `
  -ConfirmDeploy `
  -ConfigureNginx `
  -ReloadNginx
```
