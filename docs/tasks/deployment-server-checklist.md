# Deployment Server Checklist

Date: 2026-06-25

Owner: api-agent/test-agent

## Purpose

Use this checklist for staging or production host rehearsal after the local deployment readiness script passes. It is a non-destructive checklist: inspect and record results first, then make server changes only after the target host, vhost path, rollback path, and downtime window are confirmed.

## Local Precheck

Run from the project root:

```powershell
.\scripts\deployment-readiness.ps1
.\scripts\deployment-readiness.ps1 -CheckErrorLogPolicy
.\scripts\deployment-readiness.ps1 -CheckOpcachePolicy
.\scripts\deployment-readiness.ps1 -CheckSchedulerPolicy
.\scripts\deployment-readiness.ps1 -CheckCachePolicy
.\scripts\deployment-readiness.ps1 -CheckCookiePolicy
.\scripts\deployment-readiness.ps1 -CheckUrlPolicy
.\scripts\deployment-readiness.ps1 -CheckStoragePolicy
.\scripts\deployment-readiness.ps1 -CheckProviderPolicy
.\scripts\deployment-readiness.ps1 -CheckEnvTemplatePolicy
.\scripts\deployment-readiness.ps1 -CheckRuntimePermissionPolicy
.\scripts\deployment-readiness.ps1 -CheckWebServerPolicy
.\scripts\deployment-readiness.ps1 -CheckWebServerPolicy -CheckNginxSyntax -CheckPhpFpmSyntax
.\scripts\deployment-readiness.ps1 -CheckDatabaseSchema
.\scripts\deployment-readiness.ps1 -CheckArtifactPolicy
.\scripts\deployment-readiness.ps1 -CheckFrontendBuildPolicy
.\scripts\deployment-readiness.ps1 -CheckComposerPolicy
.\scripts\deployment-readiness.ps1 -CheckReleasePackagePolicy
.\scripts\deployment-readiness.ps1 -CheckBackupTools
.\scripts\deployment-readiness.ps1 -PublicBaseUrl https://oa.example.com
.\scripts\deployment-readiness.ps1 -CheckSecurityHeadersPolicy -PublicBaseUrl https://oa.example.com
.\scripts\deployment-readiness.ps1 -CheckCorsPolicy -PublicBaseUrl https://api.example.com -CorsProbeOrigin https://oa.example.com
.\scripts\deployment-readiness.ps1 -MinUploadMaxFilesize 16M -MinPostMaxSize 32M
```

On Linux staging/production hosts, run:

```bash
bash scripts/deployment-readiness.sh
bash scripts/deployment-readiness.sh --check-error-log-policy
bash scripts/deployment-readiness.sh --check-opcache-policy
bash scripts/deployment-readiness.sh --check-scheduler-policy
bash scripts/deployment-readiness.sh --check-cache-policy
bash scripts/deployment-readiness.sh --check-cookie-policy
bash scripts/deployment-readiness.sh --check-url-policy
bash scripts/deployment-readiness.sh --check-storage-policy
bash scripts/deployment-readiness.sh --check-provider-policy
bash scripts/deployment-readiness.sh --check-env-template-policy
bash scripts/deployment-readiness.sh --check-runtime-permission-policy
bash scripts/deployment-readiness.sh --check-web-server-policy
bash scripts/deployment-readiness.sh --check-web-server-policy --check-nginx-syntax --check-php-fpm-syntax
bash scripts/deployment-readiness.sh --check-database-schema
bash scripts/deployment-readiness.sh --check-artifact-policy
bash scripts/deployment-readiness.sh --check-frontend-build-policy
bash scripts/deployment-readiness.sh --check-composer-policy
bash scripts/deployment-readiness.sh --check-release-package-policy
bash scripts/deployment-readiness.sh --check-backup-tools
bash scripts/deployment-readiness.sh --public-base-url https://oa.example.com
bash scripts/deployment-readiness.sh --check-security-headers-policy --public-base-url https://oa.example.com
bash scripts/deployment-readiness.sh --check-cors-policy --public-base-url https://api.example.com --cors-probe-origin https://oa.example.com
bash scripts/deployment-readiness.sh --min-upload-max-filesize 16M --min-post-max-size 32M
```

To create missing local writable directories before another local check:

```powershell
.\scripts\deployment-readiness.ps1 -CreateMissingWritableDirs
```

Linux equivalent:

```bash
bash scripts/deployment-readiness.sh --create-missing-writable-dirs
```

For staging gate behavior:

```powershell
.\scripts\deployment-readiness.ps1 -Production -Strict
```

Linux equivalent:

```bash
bash scripts/deployment-readiness.sh --production --strict
```

## Server Checklist

| Area | Check | Expected Result |
| --- | --- | --- |
| PHP runtime | `php -v` and `php -m` | PHP `>= 8.0`; required extensions from `scripts/deployment-readiness.ps1` loaded |
| OPcache | Readiness OPcache guard and PHP-FPM runtime config | OPcache extension loaded and enabled for PHP-FPM; memory/file limits reviewed; timestamp validation/reload strategy documented |
| Composer dependency policy | Readiness Composer guard plus release install review | `composer.json`/`composer.lock` parse, required ThinkPHP packages/autoload/scripts are present, production `vendor` is installed with `composer install --no-dev --optimize-autoloader`, and known `require-dev` packages are absent from `vendor` |
| ThinkPHP boot | `php think route:list` | Route list exits `0`; route count is reviewed against local output |
| Environment file | `.env` exists outside source control | Production values set; secrets are not printed in logs or tickets |
| Environment template | Readiness environment template guard plus `.example.env` review | `.example.env` documents required runtime/cache/URL keys, uses release-safe `APP_DEBUG=false` guidance, and contains only placeholders or empty values for secrets |
| Source-control hygiene | Readiness Git secret/artifact guard passes when `.git` is present | `.env` is not tracked; `.env`, `vendor`, `runtime`, and `public/storage` artifacts are ignored |
| Debug mode | Inspect `.env` | `APP_DEBUG=false` for production |
| Error/log policy | Readiness PHP error/log guard | `display_errors` and `display_startup_errors` disabled; `log_errors` enabled; `error_log` destination or PHP-FPM/web-server log path confirmed; `expose_php` disabled |
| Document root | Active vhost points to project `public`; readiness public web-exposure guard passes | Requests cannot browse project root, `.env`, `vendor`, `runtime`, `app`, `config`, `route`, `extend`, `docs`, or `scripts` |
| HTTP exposure | Readiness `-PublicBaseUrl` / `--public-base-url` probe | Sensitive paths such as `/.env`, `/composer.json`, `/vendor/autoload.php`, `/app`, `/config`, `/docs`, and `/scripts` return non-2xx status; response bodies are not printed |
| HTTP security headers | Readiness security-header guard plus staging URL | HSTS on public HTTPS, `X-Content-Type-Options: nosniff`, `X-Frame-Options` or CSP `frame-ancestors`, CSP, `Referrer-Policy`, and `Permissions-Policy` are present/release-safe |
| CORS policy | Readiness CORS guard plus staging API URL and frontend origin | Same-origin `/api` deployment is confirmed or cross-origin preflight returns an explicit allowed origin, `Vary: Origin`, allowed methods, and allowed `Authorization`/`Content-Type` headers; wildcard admin/API origins are not used |
| Web server syntax | Readiness web-server policy guard plus approved server commands | Nginx/PHP-FPM binaries are available or explicitly managed elsewhere; `nginx -t` and `php-fpm -tt` pass without printing full config; reload/restart method is documented separately |
| Rewrite rule | Unknown paths route to `public/index.php` | SPA/API routes reach ThinkPHP instead of static 404s |
| PHP-FPM mapping | `SCRIPT_FILENAME` maps to `$document_root$fastcgi_script_name` or equivalent | PHP executes the intended `public/index.php` |
| Writable paths | `runtime`, `runtime/log`, `runtime/cache`, `runtime/temp`, `runtime/storage`, `runtime/upload`, `public/storage` | Owned or writable by the PHP-FPM/web user; no world-writable broad project root |
| Runtime permissions | Readiness runtime permission guard plus server file mode review | Sensitive files and non-public runtime paths resolve outside `public`; backup path is outside the web root; Unix modes do not allow group/other write, and `.env` is not other-readable |
| Upload limits | Nginx and PHP upload/body limits plus readiness PHP upload guard | `file_uploads` enabled; `upload_max_filesize`, `post_max_size`, `max_file_uploads`, and `memory_limit` cover expected import/upload files; rejects are controlled |
| Logs | Nginx access/error and PHP/ThinkPHP logs | Paths exist, rotate, and can be tailed during smoke |
| Scheduler/queue | Readiness scheduler/queue guard plus `docs/tasks/scheduler-queue-policy.md` | Keep workers disabled until a command, process manager, restart method, logs, retries, rollback, and side effects are documented |
| Cache/Redis | Readiness cache/Redis guard plus `.env` cache keys | `CACHE_DRIVER` is approved; Redis host/port/db/timeout/password policy are reviewed; Redis TCP reachability passes when Redis is selected |
| Cookie/session policy | Readiness cookie/session guard plus ThinkPHP cookie/session config | Secure and HttpOnly cookies enabled for HTTPS production; SameSite policy explicit; session name and expiry reviewed |
| URL/HTTPS policy | Readiness URL policy guard plus `APP_HOST` / public base URL | Production/staging public URLs are absolute HTTPS URLs; localhost HTTP is used only for local smoke |
| File storage policy | Readiness storage guard plus ThinkPHP filesystem and DevFile local root | `local` and `public` disks are configured as local disks; public disk root maps to `public/storage`; public URL/visibility are explicit; DevFile upload root exists and is not under public web root |
| Provider/deferred-send policy | Readiness provider guard plus deferred-provider docs | Real Email/SMS/WebPush/OAuth/cloud upload remain disabled unless there is an explicit provider plan; dynamic upload default engine remains `LOCAL`; provider SDK/package signals are reviewed |
| Database schema | Readiness database schema guard plus SQL reference docs | Database boots through ThinkPHP, `SELECT 1` succeeds, table count is at least the expected baseline, and curated critical tables/columns are present without migrations or row writes |
| Deployment artifact hygiene | Readiness artifact guard plus release packaging review | Release root does not include local-only `.git/.codex`, frontend `node_modules`, or runtime smoke/import/build artifacts such as screenshots, temp SQL, probe PHP, and local dev-server logs |
| Frontend build policy | Readiness frontend build guard plus Vite release output review | Frontend has a production build script, single lockfile, safe `.env.production` API URL shape, disabled production sourcemaps, manifest/compression settings, complete `dist`, no sensitive source/config entries in `dist`, and no temporary Vite/stat files in the frontend root |
| Release package policy | Readiness release package guard against the staged package root | Required backend entries, Composer vendor metadata, public entry files, and frontend `dist` assets/manifest are present; `.env`, source-control metadata, frontend source/dependency files, runtime artifacts, and project source/config entries under `public` are absent |
| Backup | Readiness backup tool guard plus uploaded-file backup path | `mysqldump` and `mysql` or configured equivalents are available; DB `.env` inputs are set; protected backup directory exists and is writable by the backup user; restore check and retention are documented before production writes |

## Nginx Inspection Commands

Run equivalent commands for the actual host and service layout:

```bash
nginx -T
php -v
php -m
php-fpm -tt
mysqldump --version
mysql --version
php -r 'foreach (["opcache.enable","opcache.enable_cli","opcache.validate_timestamps","opcache.revalidate_freq","opcache.memory_consumption","opcache.max_accelerated_files"] as $k) { echo $k,"=",ini_get($k),PHP_EOL; }'
php -r 'foreach (["display_errors","display_startup_errors","log_errors","error_log","expose_php","html_errors"] as $k) { echo $k,"=",ini_get($k),PHP_EOL; }'
php -r 'foreach (["file_uploads","upload_max_filesize","post_max_size","max_file_uploads","memory_limit"] as $k) { echo $k,"=",ini_get($k),PHP_EOL; }'
php -r '$config = require "config/console.php"; $commands = $config["commands"] ?? array(); echo is_array($commands) ? count($commands) : "invalid", PHP_EOL;'
php -r '$host = getenv("REDIS_HOST") ?: "127.0.0.1"; $port = (int)(getenv("REDIS_PORT") ?: 6379); $s = @fsockopen($host, $port, $errno, $errstr, 2); echo $s ? "redis tcp ok\n" : "redis tcp failed\n"; if ($s) fclose($s);'
php -r '$cookie = require "config/cookie.php"; $session = require "config/session.php"; foreach (["secure","httponly","samesite","path"] as $k) { $v = $cookie[$k] ?? ""; echo "cookie.",$k,"=",is_bool($v) ? ($v ? "true" : "false") : $v,PHP_EOL; } echo "session.name=",($session["name"] ?? ""),PHP_EOL,"session.expire=",($session["expire"] ?? ""),PHP_EOL;'
php -r '$url = getenv("APP_HOST") ?: ""; if ($url === "") { echo "APP_HOST empty\n"; exit; } $p = parse_url($url); echo (is_array($p) ? (($p["scheme"] ?? "") . "://" . ($p["host"] ?? "")) : "invalid"), PHP_EOL;'
php -r 'if (!function_exists("app")) { function app() { static $app; if ($app === null) { $app = new class { public function getRootPath() { return getcwd() . DIRECTORY_SEPARATOR; } public function getRuntimePath() { return getcwd() . DIRECTORY_SEPARATOR . "runtime" . DIRECTORY_SEPARATOR; } }; } return $app; } } $fs = require "config/filesystem.php"; foreach (["default" => $fs["default"] ?? "", "local.root" => $fs["disks"]["local"]["root"] ?? "", "public.root" => $fs["disks"]["public"]["root"] ?? "", "public.url" => $fs["disks"]["public"]["url"] ?? "", "public.visibility" => $fs["disks"]["public"]["visibility"] ?? ""] as $k => $v) { echo $k,"=",$v,PHP_EOL; }'
php -r 'require getcwd() . "/vendor/autoload.php"; $app = new think\App(getcwd()); $app->initialize(); $value = think\facade\Db::name("dev_config")->where("CONFIG_KEY", "SNOWY_SYS_DEFAULT_FILE_ENGINE")->where(function ($query): void { $query->whereNull("DELETE_FLAG")->whereOr("DELETE_FLAG", "=", "NOT_DELETE"); })->value("CONFIG_VALUE"); echo "SNOWY_SYS_DEFAULT_FILE_ENGINE=",strtoupper(trim((string)$value)),PHP_EOL;'
php -r 'require getcwd() . "/vendor/autoload.php"; $app = new think\App(getcwd()); $app->initialize(); think\facade\Db::query("SELECT 1"); $tables = think\facade\Db::query("SHOW TABLES"); echo "table_count=",count($tables),PHP_EOL;'
```

The automated Linux readiness script does not print `nginx -T` output. Use optional syntax checks when the host exposes the relevant commands:

```bash
bash scripts/deployment-readiness.sh --check-nginx-syntax --check-php-fpm-syntax
```

If the vhost document root is known, verify it resolves to the project `public` directory:

```powershell
.\scripts\deployment-readiness.ps1 -ExpectedPublicRoot F:\AI\projects\testJava\OA-ThinkPHP\public
.\scripts\project-progress.ps1 -CheckDeploy -ExpectedPublicRoot F:\AI\projects\testJava\OA-ThinkPHP\public -Lean
```

```bash
bash scripts/deployment-readiness.sh --expected-public-root /path/to/OA-ThinkPHP/public
```

If a staging/public URL is known, probe sensitive project paths without printing response bodies:

```powershell
.\scripts\deployment-readiness.ps1 -PublicBaseUrl https://oa.example.com
.\scripts\project-progress.ps1 -CheckDeploy -PublicBaseUrl https://oa.example.com -Lean
```

```bash
bash scripts/deployment-readiness.sh --public-base-url https://oa.example.com
```

If a staging/public URL is known, check response security headers without printing response bodies:

```powershell
.\scripts\deployment-readiness.ps1 -CheckSecurityHeadersPolicy -PublicBaseUrl https://oa.example.com
.\scripts\project-progress.ps1 -CheckDeploy -CheckSecurityHeadersPolicy -PublicBaseUrl https://oa.example.com -Lean
```

```bash
bash scripts/deployment-readiness.sh --check-security-headers-policy --public-base-url https://oa.example.com
```

If the frontend and API are cross-origin, check CORS source policy and preflight headers without printing response bodies:

```powershell
.\scripts\deployment-readiness.ps1 -CheckCorsPolicy -PublicBaseUrl https://api.example.com -CorsProbeOrigin https://oa.example.com
.\scripts\project-progress.ps1 -CheckDeploy -CheckCorsPolicy -PublicBaseUrl https://api.example.com -CorsProbeOrigin https://oa.example.com -Lean
```

```bash
bash scripts/deployment-readiness.sh --check-cors-policy --public-base-url https://api.example.com --cors-probe-origin https://oa.example.com
```

Check backup/restore command readiness without dumping data or printing secrets:

```powershell
.\scripts\deployment-readiness.ps1 -CheckBackupTools
.\scripts\project-progress.ps1 -CheckDeploy -CheckBackupTools -Lean
```

```bash
bash scripts/deployment-readiness.sh --check-backup-tools
```

Check PHP error display/logging policy:

```powershell
.\scripts\deployment-readiness.ps1 -CheckErrorLogPolicy
.\scripts\project-progress.ps1 -CheckDeploy -CheckErrorLogPolicy -Lean
```

```bash
bash scripts/deployment-readiness.sh --check-error-log-policy
```

Check PHP OPcache policy:

```powershell
.\scripts\deployment-readiness.ps1 -CheckOpcachePolicy
.\scripts\project-progress.ps1 -CheckDeploy -CheckOpcachePolicy -Lean
```

```bash
bash scripts/deployment-readiness.sh --check-opcache-policy
```

Check scheduler/queue policy without running jobs:

```powershell
.\scripts\deployment-readiness.ps1 -CheckSchedulerPolicy
.\scripts\project-progress.ps1 -CheckDeploy -CheckSchedulerPolicy -Lean
```

```bash
bash scripts/deployment-readiness.sh --check-scheduler-policy
```

Check cache/Redis policy without writing cache data or printing secrets:

```powershell
.\scripts\deployment-readiness.ps1 -CheckCachePolicy
.\scripts\project-progress.ps1 -CheckDeploy -CheckCachePolicy -Lean
```

```bash
bash scripts/deployment-readiness.sh --check-cache-policy
```

Check cookie/session policy:

```powershell
.\scripts\deployment-readiness.ps1 -CheckCookiePolicy
.\scripts\project-progress.ps1 -CheckDeploy -CheckCookiePolicy -Lean
```

```bash
bash scripts/deployment-readiness.sh --check-cookie-policy
```

Check URL/HTTPS policy:

```powershell
.\scripts\deployment-readiness.ps1 -CheckUrlPolicy
.\scripts\project-progress.ps1 -CheckDeploy -CheckUrlPolicy -Lean
```

```bash
bash scripts/deployment-readiness.sh --check-url-policy
```

Check file storage policy without upload/delete/write probes:

```powershell
.\scripts\deployment-readiness.ps1 -CheckStoragePolicy
.\scripts\project-progress.ps1 -CheckDeploy -CheckStoragePolicy -Lean
```

```bash
bash scripts/deployment-readiness.sh --check-storage-policy
```

Check provider/deferred-send policy without external provider calls:

```powershell
.\scripts\deployment-readiness.ps1 -CheckProviderPolicy
.\scripts\project-progress.ps1 -CheckDeploy -CheckProviderPolicy -Lean
```

```bash
bash scripts/deployment-readiness.sh --check-provider-policy
```

Check environment template coverage without printing values or editing env files:

```powershell
.\scripts\deployment-readiness.ps1 -CheckEnvTemplatePolicy
.\scripts\project-progress.ps1 -CheckDeploy -CheckEnvTemplatePolicy -Lean
```

```bash
bash scripts/deployment-readiness.sh --check-env-template-policy
```

Check runtime permissions and sensitive path scope without changing modes or deleting files:

```powershell
.\scripts\deployment-readiness.ps1 -CheckRuntimePermissionPolicy
.\scripts\project-progress.ps1 -CheckDeploy -CheckRuntimePermissionPolicy -Lean
```

```bash
bash scripts/deployment-readiness.sh --check-runtime-permission-policy
```

Check Nginx/PHP-FPM command availability and syntax without printing config or restarting services:

```powershell
.\scripts\deployment-readiness.ps1 -CheckWebServerPolicy
.\scripts\deployment-readiness.ps1 -CheckWebServerPolicy -CheckNginxSyntax -CheckPhpFpmSyntax
.\scripts\project-progress.ps1 -CheckDeploy -CheckWebServerPolicy -CheckNginxSyntax -CheckPhpFpmSyntax -Lean
```

```bash
bash scripts/deployment-readiness.sh --check-web-server-policy
bash scripts/deployment-readiness.sh --check-web-server-policy --check-nginx-syntax --check-php-fpm-syntax
```

Check database schema readiness without migrations, DDL, or row writes:

```powershell
.\scripts\deployment-readiness.ps1 -CheckDatabaseSchema
.\scripts\project-progress.ps1 -CheckDeploy -CheckDatabaseSchema -Lean
```

```bash
bash scripts/deployment-readiness.sh --check-database-schema
```

Check deployment artifact hygiene without deleting files:

```powershell
.\scripts\deployment-readiness.ps1 -CheckArtifactPolicy
.\scripts\project-progress.ps1 -CheckDeploy -CheckArtifactPolicy -Lean
```

```bash
bash scripts/deployment-readiness.sh --check-artifact-policy
```

Check frontend build policy without installing dependencies or running the build:

```powershell
.\scripts\deployment-readiness.ps1 -CheckFrontendBuildPolicy
.\scripts\project-progress.ps1 -CheckDeploy -CheckFrontendBuildPolicy -Lean
```

```bash
bash scripts/deployment-readiness.sh --check-frontend-build-policy
```

Check Composer dependency/autoload policy without installing or updating dependencies:

```powershell
.\scripts\deployment-readiness.ps1 -CheckComposerPolicy
.\scripts\project-progress.ps1 -CheckDeploy -CheckComposerPolicy -Lean
```

```bash
bash scripts/deployment-readiness.sh --check-composer-policy
```

Check release package include/exclude policy without building, copying, or archiving files:

```powershell
.\scripts\deployment-readiness.ps1 -CheckReleasePackagePolicy -ReleaseRoot C:\path\to\release-root
.\scripts\project-progress.ps1 -CheckDeploy -CheckReleasePackagePolicy -ReleaseRoot C:\path\to\release-root -Lean
```

```bash
bash scripts/deployment-readiness.sh --check-release-package-policy --release-root /path/to/release-root
```

If the server uses a panel-managed vhost, inspect the active generated vhost file before editing any include file. Record the exact vhost path in the deployment ticket.

## Smoke Sequence

1. Run `php think route:list`.
2. Run `.\scripts\deployment-readiness.ps1 -Production -Strict -CheckErrorLogPolicy -CheckOpcachePolicy -CheckSchedulerPolicy -CheckCachePolicy -CheckCookiePolicy -CheckUrlPolicy -CheckStoragePolicy -CheckProviderPolicy -CheckEnvTemplatePolicy -CheckRuntimePermissionPolicy -CheckWebServerPolicy -CheckNginxSyntax -CheckPhpFpmSyntax -CheckSecurityHeadersPolicy -CheckCorsPolicy -CheckDatabaseSchema -CheckArtifactPolicy -CheckFrontendBuildPolicy -CheckComposerPolicy -CheckReleasePackagePolicy -CheckBackupTools -PublicBaseUrl <staging-api-url> -CorsProbeOrigin <staging-frontend-origin>` or `bash scripts/deployment-readiness.sh --production --strict --check-error-log-policy --check-opcache-policy --check-scheduler-policy --check-cache-policy --check-cookie-policy --check-url-policy --check-storage-policy --check-provider-policy --check-env-template-policy --check-runtime-permission-policy --check-web-server-policy --check-nginx-syntax --check-php-fpm-syntax --check-security-headers-policy --check-cors-policy --check-database-schema --check-artifact-policy --check-frontend-build-policy --check-composer-policy --check-release-package-policy --check-backup-tools --public-base-url <staging-api-url> --cors-probe-origin <staging-frontend-origin>` on the staged release.
3. Start or reload PHP-FPM and Nginx using the target host's approved method.
4. Hit `/think` or the configured backend health path.
5. Run authenticated backend smokes against the staging base URL.
6. Run the frontend login/menu smoke against the staging frontend.
7. Tail Nginx, PHP-FPM, and ThinkPHP logs during the smoke.

## Stop Conditions

- `.env` or other secret files are web-readable.
- `.env` is tracked by Git or runtime/upload/dependency artifacts are not ignored.
- `.example.env` is missing required runtime/cache/URL keys, contains real-looking secret values, or defaults `APP_DEBUG` to a release-unsafe value.
- Active vhost document root is not `public`.
- Nginx/PHP-FPM command availability is not confirmed, syntax checks fail, or reload/restart ownership is unknown.
- Readiness public web-exposure guard finds project source/config/dependency entries under `public`.
- Readiness HTTP public-exposure probe returns 2xx for sensitive source/config/dependency/status paths, or a redirect target is not explicitly confirmed safe.
- Public HTTPS response is missing HSTS, `X-Content-Type-Options: nosniff`, frame protection, CSP, `Referrer-Policy`, or `Permissions-Policy`.
- Cross-origin staging/frontend deployment lacks explicit CORS allowlist behavior, uses wildcard admin/API origin, omits `Vary: Origin` for reflected origins, or preflight does not allow required methods and `Authorization`/`Content-Type` headers.
- `php think route:list` fails.
- PHP uploads are disabled, `max_file_uploads` is invalid, or upload/body/memory limits are below the approved import/upload file size.
- PHP error display is enabled, PHP error logging is disabled, `expose_php` is enabled, or the PHP-FPM/web-server error log destination is not confirmed.
- OPcache is unavailable or disabled in the PHP-FPM runtime, or deploy/reload behavior for OPcache is not documented.
- Scheduler/queue worker policy is missing, or job/worker execution is enabled without documented command, process manager, restart, log, retry, rollback, and side-effect boundaries.
- Cache/Redis driver policy is undocumented, Redis host/port are invalid, Redis TCP reachability fails, or Redis password/network protection is not confirmed.
- Cookie secure, HttpOnly, or SameSite policy is not explicitly production-ready.
- Configured staging/production public URLs are missing, invalid, or non-HTTPS outside localhost smoke.
- Filesystem disk roots are missing/mis-mapped, public disk URL/visibility is not explicit, or DevFile local upload root resolves under public web root.
- Provider/deferred-send policy documentation is missing, dynamic upload default engine is not `LOCAL`, provider send wrappers are not controlled-deferred, or provider SDK/package signals appear without an approved provider enablement plan.
- Database connection fails, schema table count is below baseline, or curated critical tables/columns are missing.
- Local-only source metadata, frontend dependency directories, runtime screenshots, import SQL, probe PHP, or local dev-server logs are present in the release root.
- Frontend production build script/env/lockfile/Vite settings are missing or unsafe, `dist` is incomplete, `dist` includes source/config/dependency entries, or Vite timestamp/stat artifacts remain in the frontend root.
- Composer manifest/lock/autoload/vendor metadata are missing or invalid, `composer validate` fails, or known `require-dev` packages are installed in production `vendor`.
- Release package root is the raw source checkout, required runtime entries are missing, `.env`/source-control/frontend source/dependency entries are present, runtime artifacts are included, or project source/config entries appear under `public`.
- PHP-FPM user cannot write required runtime/storage directories.
- Sensitive files or non-public runtime paths resolve under `public`, backup path resolves under the web root, Unix modes allow group/other write on sensitive files, or `.env` is other-readable.
- Production still has `APP_DEBUG=true`.
- Dump/restore commands are unavailable, backup directory is missing/unwritable, or backup/restore path is not confirmed before any production data operation.

## Deferred

- Editing server vhosts.
- Choosing final domain/DNS/TLS policy.
- Enabling real provider sends, cloud storage, scheduler workers, or queue workers.
- Final online data sync.
