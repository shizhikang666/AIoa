# Deployment Runtime Readiness Plan

Date: 2026-06-25

Owner: api-agent/test-agent

## Goal

Add a repeatable, non-destructive deployment readiness check before the later staging rehearsal. The check should catch obvious runtime/config gaps without touching `.env`, database rows, production files, or online data.

## Implemented

- Added `scripts/deployment-readiness.ps1`.
- The script checks required ThinkPHP entry/config files, `public` web-exposure guardrails, optional HTTP public-exposure/security-header/CORS probes, Git secret/artifact ignore hygiene, optional PHP error/log policy readiness, optional OPcache policy readiness, optional scheduler/queue policy readiness, optional cache/Redis policy readiness, optional cookie/session policy readiness, optional URL/HTTPS policy readiness, optional file storage policy readiness, optional provider/deferred-send policy readiness, optional environment template policy readiness, optional runtime permission/path-scope readiness, optional web-server command/syntax readiness, optional database schema readiness, optional deployment artifact hygiene readiness, optional frontend build policy readiness, optional Composer dependency/autoload policy readiness, optional release package include/exclude policy readiness, optional backup/restore tool readiness, `.env` presence, Composer autoload, PHP and Composer availability, required PHP extension loading, PHP upload/body limits, expected `.env` key presence, runtime/storage writable paths, and `php think route:list` boot.
- Secret values are never printed; `.env` checks only report key presence or safe normalized status such as `APP_DEBUG`, and HTTP probes record only status codes.
- Default mode reports warnings for deployment-hardening items that may be local-only, such as `APP_DEBUG=true`, low PHP upload/body size limits, missing lazily-created runtime directories, optional PHP extensions, or missing Composer binary when `vendor` is already present.
- `-Production` fails if `APP_DEBUG` is not false.
- `-Strict` treats warnings as failures for staging rehearsal gates.
- `-SkipThinkBoot` and `-SkipWritableProbe` allow reduced checks when running on locked-down hosts.
- `-CreateMissingWritableDirs` creates missing writable directory shells before probing them; default mode remains read-only.
- `-CheckErrorLogPolicy` checks PHP `display_errors`, `display_startup_errors`, `log_errors`, `error_log`, `expose_php`, and `html_errors`; `-Production` enables this guard automatically and fails on public error display/header exposure issues.
- `-CheckOpcachePolicy` checks OPcache extension loading and common OPcache settings; `-Production` enables this guard automatically and fails when OPcache is unavailable or disabled.
- `-CheckSchedulerPolicy` checks scheduler/queue policy documentation, ThinkPHP console command registration, app command class signals, known queue/worker dependency signals, and dev-job runtime controls without running jobs; `-Production` enables this guard automatically and fails when worker/job signals are undocumented.
- `-CheckCachePolicy` checks `CACHE_DRIVER`, Redis host/port/db/timeout/password-policy signals, and Redis TCP reachability without writing cache data or printing secrets; `-Production` enables this guard automatically and fails when required Redis settings or reachability are unavailable.
- `-CheckCookiePolicy` checks ThinkPHP cookie/session security settings; `-Production` enables this guard automatically and fails when secure, HttpOnly, or SameSite policy is not production-ready.
- `-CheckUrlPolicy` checks `APP_HOST` and `-PublicBaseUrl` URL format and HTTPS policy; `-Production` enables this guard automatically and fails when configured non-local URLs are not HTTPS.
- `-CheckStoragePolicy` checks ThinkPHP filesystem disks, public disk URL/visibility, and DevFile local upload root/exposure without uploading, deleting, or writing files; `-Production` enables this guard automatically and fails when storage roots or public/private exposure policy are not production-ready.
- `-CheckProviderPolicy` checks provider/deferred-send documentation, guarded source/route signals for Email/SMS/WebPush/OAuth/cloud-upload deferred behavior, known Composer provider SDK package signals, and `SNOWY_SYS_DEFAULT_FILE_ENGINE` without sending messages or contacting external providers; `-Production` enables this guard automatically and fails when provider boundaries are undocumented or dynamic upload is switched away from `LOCAL`.
- `-CheckEnvTemplatePolicy` checks `.example.env` key coverage, non-local `.env` key documentation, `APP_DEBUG` template default, DB port shape, and secret-placeholder policy without printing values or editing env files; `-Production` enables this guard automatically.
- `-CheckRuntimePermissionPolicy` checks sensitive file path scope, runtime writable path scope, backup path placement/existence, and Unix file modes on non-Windows hosts without changing permissions or deleting files; `-Production` enables this guard automatically.
- `-CheckWebServerPolicy` checks Nginx/PHP-FPM command availability without printing configuration; `-CheckNginxSyntax` runs `nginx -t`, `-CheckPhpFpmSyntax` runs `php-fpm -tt`, and `-Production` fails when required command availability cannot be confirmed.
- `-CheckSecurityHeadersPolicy` checks the `PublicBaseUrl` response security headers without printing response bodies; it reports HSTS, `X-Content-Type-Options`, frame protection, CSP, `Referrer-Policy`, and `Permissions-Policy` gaps, and `-Production` enables this guard automatically.
- `-CheckCorsPolicy` checks CORS source signals, global middleware signals, wildcard origin/credential risks, frontend production API prefix shape, and optional `OPTIONS` preflight response headers through `PublicBaseUrl` plus `CorsProbeOrigin` without printing response bodies; `-Production` enables this guard automatically.
- `-CheckDatabaseSchema` checks database boot, `SELECT 1`, table count, curated critical table presence, and curated critical columns with read-only `SHOW TABLES` / `SHOW COLUMNS` queries; `-Production` enables this guard automatically and fails when the expected schema baseline is unavailable.
- `-CheckArtifactPolicy` checks release-sensitive source metadata, frontend dependency folders, and local runtime smoke/import/build artifacts without deleting files; `-Production` enables this guard automatically and fails when these local-only artifacts are present in the release root.
- `-CheckFrontendBuildPolicy` checks the Vite production build script, package lock policy, `.env.production` shape, Vite build settings, `dist` output completeness, `dist` source/config exposure, and frontend temporary build artifacts without running `npm install` or `npm run build`; `-Production` enables this guard automatically.
- `-CheckComposerPolicy` checks `composer.json`/`composer.lock` parseability, required ThinkPHP packages, autoload mappings, post-autoload scripts, vendor/composer metadata, known `require-dev` packages installed in `vendor`, and read-only `composer validate` without running install/update/autoload-dump/vendor-publish commands; `-Production` enables this guard automatically.
- `-CheckReleasePackagePolicy` checks a release root for required backend/frontend runtime entries, frontend `dist` manifest/assets, excluded source-control/secret/frontend-source/dependency entries, runtime artifacts, and public-root source/config exposure without building, archiving, deleting, or copying files; `-Production` enables this guard automatically.
- `-CheckBackupTools` checks database dump/restore command availability, backup DB `.env` inputs, and backup directory writability without dumping data or printing secrets; `-Production` enables this guard automatically.
- `-MysqlDumpBinary`, `-MysqlClientBinary`, and `-BackupDirectory` customize the backup guard.
- `-ReleaseRoot` customizes the release package root inspected by `-CheckReleasePackagePolicy`; default is the current project root for dry-run reporting.
- `-SchedulerPolicyDocument` customizes the scheduler/queue policy document path.
- `-CacheTcpTimeoutSeconds` customizes the cache TCP probe timeout; default is `2` seconds.
- `-ExpectedPublicRoot` verifies that a known vhost/document-root path resolves to this project's `public` directory.
- `-PublicBaseUrl` enables HTTP probes for sensitive project paths such as `/.env`, `/composer.json`, `/vendor/autoload.php`, `/app`, `/config`, `/docs`, and `/scripts`; `-HttpProbeTimeoutSeconds` sets the per-request timeout.
- `-CorsProbeOrigin` sets the frontend origin used by the CORS preflight probe.
- `-MinUploadMaxFilesize` and `-MinPostMaxSize` set the PHP upload/body warning thresholds; defaults are `8M` and `8M`.
- `scripts/project-progress.ps1 -CheckDeploy` accepts `-ExpectedPublicRoot`, `-PublicBaseUrl`, and `-HttpProbeTimeoutSeconds` and passes them through to the readiness script.
- `scripts/project-progress.ps1 -CheckDeploy` also accepts `-CheckErrorLogPolicy` and passes it through to the readiness script.
- `scripts/project-progress.ps1 -CheckDeploy` also accepts `-CheckOpcachePolicy` and passes it through to the readiness script.
- `scripts/project-progress.ps1 -CheckDeploy` also accepts `-CheckSchedulerPolicy` and passes it through to the readiness script.
- `scripts/project-progress.ps1 -CheckDeploy` also accepts `-CheckCachePolicy` and passes it through to the readiness script.
- `scripts/project-progress.ps1 -CheckDeploy` also accepts `-CheckCookiePolicy` and passes it through to the readiness script.
- `scripts/project-progress.ps1 -CheckDeploy` also accepts `-CheckUrlPolicy` and passes it through to the readiness script.
- `scripts/project-progress.ps1 -CheckDeploy` also accepts `-CheckStoragePolicy` and passes it through to the readiness script.
- `scripts/project-progress.ps1 -CheckDeploy` also accepts `-CheckProviderPolicy` and passes it through to the readiness script.
- `scripts/project-progress.ps1 -CheckDeploy` also accepts `-CheckEnvTemplatePolicy` and passes it through to the readiness script.
- `scripts/project-progress.ps1 -CheckDeploy` also accepts `-CheckRuntimePermissionPolicy` and passes it through to the readiness script.
- `scripts/project-progress.ps1 -CheckDeploy` also accepts `-CheckWebServerPolicy`, `-CheckNginxSyntax`, `-CheckPhpFpmSyntax`, `-NginxBinary`, and `-PhpFpmBinary` and passes them through to the readiness script.
- `scripts/project-progress.ps1 -CheckDeploy` also accepts `-CheckSecurityHeadersPolicy` and passes it through to the readiness script.
- `scripts/project-progress.ps1 -CheckDeploy` also accepts `-CheckCorsPolicy` and `-CorsProbeOrigin` and passes them through to the readiness script.
- `scripts/project-progress.ps1 -CheckDeploy` also accepts `-CheckDatabaseSchema` and passes it through to the readiness script.
- `scripts/project-progress.ps1 -CheckDeploy` also accepts `-CheckArtifactPolicy` and passes it through to the readiness script.
- `scripts/project-progress.ps1 -CheckDeploy` also accepts `-CheckFrontendBuildPolicy` and passes it through to the readiness script.
- `scripts/project-progress.ps1 -CheckDeploy` also accepts `-CheckComposerPolicy` and passes it through to the readiness script.
- `scripts/project-progress.ps1 -CheckDeploy` also accepts `-CheckReleasePackagePolicy` and `-ReleaseRoot` and passes them through to the readiness script.
- `scripts/project-progress.ps1 -CheckDeploy` also accepts `-CheckBackupTools`, `-MysqlDumpBinary`, `-MysqlClientBinary`, and `-BackupDirectory` and passes them through to the readiness script.
- `scripts/project-progress.ps1 -CheckDeploy` also accepts `-MinUploadMaxFilesize` and `-MinPostMaxSize` and passes them through to the readiness script.
- Both PowerShell and Linux readiness scripts fail when sensitive project entries such as `.env`, `vendor`, `app`, `config`, `route`, `docs`, or `scripts` are found under `public`.
- When Git metadata is available, both readiness scripts fail if `.env` is tracked and warn if `.env`, `vendor`, `runtime`, or `public/storage` artifact paths are not ignored.
- Added `public/storage/.gitignore` so public-disk upload artifacts are ignored while the checked directory exists.
- `scripts/project-progress.ps1 -CheckDeploy -Lean` can include the readiness snapshot with the existing project progress summary.
- Added `scripts/deployment-readiness.sh` for Linux staging/production hosts.
- Added `docs/tasks/deployment-server-checklist.md` for later Nginx/PHP-FPM host rehearsal.

## Command

```powershell
.\scripts\deployment-readiness.ps1
```

For staging rehearsal:

```powershell
.\scripts\deployment-readiness.ps1 -Production -Strict
```

To create missing local writable directories before rechecking:

```powershell
.\scripts\deployment-readiness.ps1 -CreateMissingWritableDirs
```

To verify a known vhost/document root:

```powershell
.\scripts\deployment-readiness.ps1 -ExpectedPublicRoot F:\AI\projects\testJava\OA-ThinkPHP\public
.\scripts\project-progress.ps1 -CheckDeploy -ExpectedPublicRoot F:\AI\projects\testJava\OA-ThinkPHP\public -Lean
```

To probe a staging/public URL for sensitive file exposure:

```powershell
.\scripts\deployment-readiness.ps1 -PublicBaseUrl https://oa.example.com
.\scripts\project-progress.ps1 -CheckDeploy -PublicBaseUrl https://oa.example.com -Lean
```

To check HTTP security headers without printing response bodies:

```powershell
.\scripts\deployment-readiness.ps1 -CheckSecurityHeadersPolicy -PublicBaseUrl https://oa.example.com
.\scripts\project-progress.ps1 -CheckDeploy -CheckSecurityHeadersPolicy -PublicBaseUrl https://oa.example.com -Lean
```

To check CORS source policy and an optional preflight without printing response bodies:

```powershell
.\scripts\deployment-readiness.ps1 -CheckCorsPolicy -PublicBaseUrl https://api.example.com -CorsProbeOrigin https://oa.example.com
.\scripts\project-progress.ps1 -CheckDeploy -CheckCorsPolicy -PublicBaseUrl https://api.example.com -CorsProbeOrigin https://oa.example.com -Lean
```

To check backup/restore command readiness:

```powershell
.\scripts\deployment-readiness.ps1 -CheckBackupTools
.\scripts\project-progress.ps1 -CheckDeploy -CheckBackupTools -Lean
```

To check PHP error display/logging policy:

```powershell
.\scripts\deployment-readiness.ps1 -CheckErrorLogPolicy
.\scripts\project-progress.ps1 -CheckDeploy -CheckErrorLogPolicy -Lean
```

To check PHP OPcache policy:

```powershell
.\scripts\deployment-readiness.ps1 -CheckOpcachePolicy
.\scripts\project-progress.ps1 -CheckDeploy -CheckOpcachePolicy -Lean
```

To check scheduler/queue policy without running jobs:

```powershell
.\scripts\deployment-readiness.ps1 -CheckSchedulerPolicy
.\scripts\project-progress.ps1 -CheckDeploy -CheckSchedulerPolicy -Lean
```

To check cache/Redis policy without writing cache data:

```powershell
.\scripts\deployment-readiness.ps1 -CheckCachePolicy
.\scripts\project-progress.ps1 -CheckDeploy -CheckCachePolicy -Lean
```

To check cookie/session policy:

```powershell
.\scripts\deployment-readiness.ps1 -CheckCookiePolicy
.\scripts\project-progress.ps1 -CheckDeploy -CheckCookiePolicy -Lean
```

To check URL/HTTPS policy:

```powershell
.\scripts\deployment-readiness.ps1 -CheckUrlPolicy
.\scripts\project-progress.ps1 -CheckDeploy -CheckUrlPolicy -Lean
```

To check file storage policy without writing files:

```powershell
.\scripts\deployment-readiness.ps1 -CheckStoragePolicy
.\scripts\project-progress.ps1 -CheckDeploy -CheckStoragePolicy -Lean
```

To check provider/deferred-send policy without external provider calls:

```powershell
.\scripts\deployment-readiness.ps1 -CheckProviderPolicy
.\scripts\project-progress.ps1 -CheckDeploy -CheckProviderPolicy -Lean
```

To check environment template coverage without printing values:

```powershell
.\scripts\deployment-readiness.ps1 -CheckEnvTemplatePolicy
.\scripts\project-progress.ps1 -CheckDeploy -CheckEnvTemplatePolicy -Lean
```

To check runtime permissions and sensitive path scope without chmod/delete:

```powershell
.\scripts\deployment-readiness.ps1 -CheckRuntimePermissionPolicy
.\scripts\project-progress.ps1 -CheckDeploy -CheckRuntimePermissionPolicy -Lean
```

To check Nginx/PHP-FPM command availability and syntax without printing config:

```powershell
.\scripts\deployment-readiness.ps1 -CheckWebServerPolicy
.\scripts\deployment-readiness.ps1 -CheckWebServerPolicy -CheckNginxSyntax -CheckPhpFpmSyntax
.\scripts\project-progress.ps1 -CheckDeploy -CheckWebServerPolicy -CheckNginxSyntax -CheckPhpFpmSyntax -Lean
```

To check database schema readiness without migrations or data writes:

```powershell
.\scripts\deployment-readiness.ps1 -CheckDatabaseSchema
.\scripts\project-progress.ps1 -CheckDeploy -CheckDatabaseSchema -Lean
```

To check deployment artifact hygiene without deleting files:

```powershell
.\scripts\deployment-readiness.ps1 -CheckArtifactPolicy
.\scripts\project-progress.ps1 -CheckDeploy -CheckArtifactPolicy -Lean
```

To check frontend build policy without running the build:

```powershell
.\scripts\deployment-readiness.ps1 -CheckFrontendBuildPolicy
.\scripts\project-progress.ps1 -CheckDeploy -CheckFrontendBuildPolicy -Lean
```

To check Composer dependency/autoload policy without install/update:

```powershell
.\scripts\deployment-readiness.ps1 -CheckComposerPolicy
.\scripts\project-progress.ps1 -CheckDeploy -CheckComposerPolicy -Lean
```

To check release package include/exclude policy without building or archiving:

```powershell
.\scripts\deployment-readiness.ps1 -CheckReleasePackagePolicy
.\scripts\deployment-readiness.ps1 -CheckReleasePackagePolicy -ReleaseRoot C:\path\to\release-root
.\scripts\project-progress.ps1 -CheckDeploy -CheckReleasePackagePolicy -ReleaseRoot C:\path\to\release-root -Lean
```

To require larger upload/import limits during rehearsal:

```powershell
.\scripts\deployment-readiness.ps1 -MinUploadMaxFilesize 16M -MinPostMaxSize 32M
.\scripts\project-progress.ps1 -CheckDeploy -MinUploadMaxFilesize 16M -MinPostMaxSize 32M -Lean
```

Linux server equivalent:

```bash
bash scripts/deployment-readiness.sh --production --strict
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
bash scripts/deployment-readiness.sh --check-release-package-policy --release-root /path/to/release-root
bash scripts/deployment-readiness.sh --check-backup-tools
bash scripts/deployment-readiness.sh --public-base-url https://oa.example.com
bash scripts/deployment-readiness.sh --check-security-headers-policy --public-base-url https://oa.example.com
bash scripts/deployment-readiness.sh --check-cors-policy --public-base-url https://api.example.com --cors-probe-origin https://oa.example.com
bash scripts/deployment-readiness.sh --min-upload-max-filesize 16M --min-post-max-size 32M
```

## Non-Goals

- No Nginx/PHP-FPM vhost editing, config dump printing, service restart, reload, or process mutation.
- No HTTP response body capture/storage, CORS/header injection, or web-server/frontend/backend config mutation.
- No `.env` value changes or secret disclosure.
- Readiness scripts do not edit `.env` or `.example.env`; template content changes must remain separate explicit cleanup slices.
- No chmod/chown, permission mutation, backup directory creation, or filesystem cleanup.
- No database migrations, imports, schema changes, row writes, production data sync, or backup automation.
- No queue/scheduler execution.
- No upload, delete, artifact cleanup, filesystem mutation, release archive creation, release file copying, frontend dependency install/build execution, Composer install/update/autoload-dump/vendor-publish execution, external provider calls, provider credential validation, or real Email/SMS/WebPush/OAuth enablement.

## Verification

- PowerShell parser checks for `scripts\deployment-readiness.ps1` and `scripts\project-progress.ps1`: passed.
- Git Bash `bash -n scripts/deployment-readiness.sh`: passed.
- `.\scripts\deployment-readiness.ps1 -CreateMissingWritableDirs`: passed with 0 failures and 1 local deployment warning.
- `.\scripts\deployment-readiness.ps1`: passed with 0 failures and 1 local deployment warning.
- Git Bash `scripts/deployment-readiness.sh --skip-think-boot --skip-writable-probe`: passed with 0 failures and 3 expected Windows-host warnings.
- Git Bash `scripts/deployment-readiness.sh`: passed with 0 failures and 3 expected Windows-host warnings.
- `.\scripts\deployment-readiness.ps1 -ExpectedPublicRoot .\public`: passed with 0 failures and 2 local deployment warnings.
- Git Bash `scripts/deployment-readiness.sh --expected-public-root ./public`: passed with 0 failures and 4 expected Windows-host/local warnings.
- Temporary PHP public-root server HTTP probe with `-PublicBaseUrl`: PowerShell and Git Bash readiness passed with all sensitive paths returning `404`.
- `.\scripts\deployment-readiness.ps1 -CheckErrorLogPolicy -SkipThinkBoot`: passed with 0 failures and local PHP error/log policy warnings.
- Git Bash `scripts/deployment-readiness.sh --check-error-log-policy --skip-think-boot`: passed with 0 failures and local PHP error/log policy warnings.
- `.\scripts\deployment-readiness.ps1 -CheckOpcachePolicy -SkipThinkBoot`: passed with 0 failures and local OPcache readiness warnings.
- Git Bash `scripts/deployment-readiness.sh --check-opcache-policy --skip-think-boot`: passed with 0 failures and local OPcache readiness warnings.
- `.\scripts\deployment-readiness.ps1 -CheckSchedulerPolicy -SkipThinkBoot`: passed with 0 failures and 2 local deployment warnings; scheduler/queue policy checks were OK.
- Git Bash `scripts/deployment-readiness.sh --check-scheduler-policy --skip-think-boot`: passed with 0 failures and 4 expected Windows-host/local warnings; scheduler/queue policy checks were OK.
- `.\scripts\deployment-readiness.ps1 -CheckCachePolicy -SkipThinkBoot`: passed with 0 failures and 2 local deployment warnings; local Redis TCP reachability was OK.
- Git Bash `scripts/deployment-readiness.sh --check-cache-policy --skip-think-boot`: passed with 0 failures and 4 expected Windows-host/local warnings; local Redis TCP reachability was OK.
- `.\scripts\deployment-readiness.ps1 -CheckCookiePolicy -SkipThinkBoot`: passed with 0 failures and local cookie/session policy warnings.
- Git Bash `scripts/deployment-readiness.sh --check-cookie-policy --skip-think-boot`: passed with 0 failures and expected Windows-host/local cookie/session policy warnings.
- `.\scripts\deployment-readiness.ps1 -CheckUrlPolicy -SkipThinkBoot`: passed with 0 failures and local URL/HTTPS policy warnings for empty `APP_HOST` and `PublicBaseUrl`.
- Git Bash `scripts/deployment-readiness.sh --check-url-policy --skip-think-boot`: passed with 0 failures and expected Windows-host/local URL/HTTPS policy warnings.
- `.\scripts\deployment-readiness.ps1 -CheckStoragePolicy -SkipThinkBoot`: passed with 0 failures and 2 local deployment warnings; filesystem and DevFile storage policy checks were OK.
- Git Bash `scripts/deployment-readiness.sh --check-storage-policy --skip-think-boot`: passed with 0 failures and 4 expected Windows-host/local warnings; filesystem and DevFile storage policy checks were OK.
- `.\scripts\deployment-readiness.ps1 -CheckProviderPolicy -SkipThinkBoot`: passed with 0 failures and 2 local deployment warnings; provider/deferred-send policy checks were OK and default dynamic file engine remained `LOCAL`.
- Git Bash `scripts/deployment-readiness.sh --check-provider-policy --skip-think-boot`: passed with 0 failures and 4 expected Windows-host/local warnings; provider/deferred-send policy checks were OK and default dynamic file engine remained `LOCAL`.
- `.\scripts\deployment-readiness.ps1 -CheckEnvTemplatePolicy -SkipThinkBoot`: passed with 0 failures and 5 local deployment warnings; environment template policy confirmed `.example.env` parseability and baseline keys, and reported missing deployment/cache/Redis/APP_HOST template keys plus release-unsafe `APP_DEBUG` default guidance.
- Git Bash `scripts/deployment-readiness.sh --check-env-template-policy --skip-think-boot`: passed with 0 failures and 8 expected Windows-host/local warnings; environment template policy confirmed `.example.env` parseability and baseline keys, and reported missing deployment/cache/Redis/APP_HOST template keys plus release-unsafe `APP_DEBUG` default guidance.
- After the environment template cleanup, `.\scripts\deployment-readiness.ps1 -CheckEnvTemplatePolicy -SkipThinkBoot` passed with 0 failures and 2 local warnings, and Git Bash `scripts/deployment-readiness.sh --check-env-template-policy --skip-think-boot` passed with 0 failures and 4 expected Windows-host/local warnings; `.example.env` required key coverage, non-local key coverage, `APP_DEBUG=false`, DB port shape, and secret placeholders are now OK.
- `.\scripts\deployment-readiness.ps1 -CheckRuntimePermissionPolicy -SkipThinkBoot`: passed with 0 failures and 3 local deployment warnings; runtime permission policy confirmed sensitive files and non-public runtime paths stay outside `public`, `public/storage` is the intended public upload/download path, backup path is outside `public`, and Unix mode checks are skipped on Windows.
- Git Bash `scripts/deployment-readiness.sh --check-runtime-permission-policy --skip-think-boot`: passed with 0 failures and 5 expected Windows-host/local warnings; runtime permission policy confirmed the same path-scope checks and skipped Unix mode checks on Git Bash/Windows.
- `.\scripts\deployment-readiness.ps1 -CheckWebServerPolicy -CheckNginxSyntax -CheckPhpFpmSyntax -SkipThinkBoot`: passed with 0 failures and 4 local deployment warnings; local Windows host does not expose `nginx` or `php-fpm`, so syntax checks were not run.
- Git Bash `scripts/deployment-readiness.sh --check-web-server-policy --check-nginx-syntax --check-php-fpm-syntax --skip-think-boot`: passed with 0 failures and 4 expected Windows-host/local warnings; local Git Bash host does not expose `nginx` or `php-fpm`, so syntax checks were not run.
- `.\scripts\deployment-readiness.ps1 -CheckSecurityHeadersPolicy -SkipThinkBoot`: passed with 0 failures and 3 local deployment warnings; the security-header probe reported empty `PublicBaseUrl`.
- Git Bash `scripts/deployment-readiness.sh --check-security-headers-policy --skip-think-boot`: passed with 0 failures and 5 expected Windows-host/local warnings; the security-header probe reported empty `PublicBaseUrl`.
- Temporary PHP public-root server security-header probe with `-CheckSecurityHeadersPolicy -PublicBaseUrl <temporary public server> -HttpProbeTimeoutSeconds 1 -SkipThinkBoot`: PowerShell readiness passed with 0 failures and 7 local warnings; it parsed the entry response headers, skipped HSTS for local HTTP, and reported missing `X-Content-Type-Options`, frame protection, CSP, `Referrer-Policy`, and `Permissions-Policy`.
- Temporary PHP public-root server security-header probe with Git Bash `--check-security-headers-policy --public-base-url <temporary public server> --http-probe-timeout 1 --skip-think-boot`: passed with 0 failures and 9 expected Windows-host/local warnings.
- Local TCP security-header fixture with `nosniff`, `X-Frame-Options`, CSP, `Referrer-Policy`, and `Permissions-Policy`: PowerShell passed with 0 failures and 2 local warnings, and Git Bash passed with 0 failures and 4 expected Windows-host/local warnings.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckSecurityHeadersPolicy -PublicBaseUrl <temporary public server> -HttpProbeTimeoutSeconds 1 -Lean`: passed and confirmed security-header passthrough.
- `.\scripts\deployment-readiness.ps1 -CheckCorsPolicy -SkipThinkBoot`: passed with 0 failures and 5 local deployment warnings; it found one CORS source signal, no global CORS middleware signal, one wildcard origin source match, relative frontend production API prefix, and skipped live preflight without `PublicBaseUrl`.
- Git Bash `scripts/deployment-readiness.sh --check-cors-policy --skip-think-boot`: passed with 0 failures and 7 expected Windows-host/local warnings.
- Local TCP CORS fixture with reflected origin, `Vary: Origin`, allowed `GET`, and allowed `Authorization, Content-Type`: PowerShell readiness passed with 0 failures and 4 local warnings, Git Bash passed with 0 failures and 6 expected Windows-host/local warnings, and `.\scripts\project-progress.ps1 -CheckDeploy -CheckCorsPolicy -PublicBaseUrl <temporary CORS fixture> -CorsProbeOrigin https://oa.example.com -HttpProbeTimeoutSeconds 2 -Lean` passed with 0 failures and 4 local warnings.
- Temporary PHP public-root server CORS preflight with `-CheckCorsPolicy -PublicBaseUrl <temporary public server> -CorsProbeOrigin https://oa.example.com -HttpProbeTimeoutSeconds 1 -SkipThinkBoot`: PowerShell passed with 0 failures and 7 local warnings, and Git Bash passed with 0 failures and 9 expected Windows-host/local warnings; the local server returned `204` but no `Access-Control-Allow-Origin`, allowed methods, or allowed headers.
- `.\scripts\deployment-readiness.ps1 -CheckDatabaseSchema -SkipThinkBoot`: passed with 0 failures and 2 local deployment warnings; database schema readiness confirmed `SELECT 1`, 121 tables, 57 curated required tables, and 38 curated column groups.
- Git Bash `scripts/deployment-readiness.sh --check-database-schema --skip-think-boot`: passed with 0 failures and 4 expected Windows-host/local warnings; database schema readiness confirmed `SELECT 1`, 121 tables, 57 curated required tables, and 38 curated column groups.
- `.\scripts\deployment-readiness.ps1 -CheckArtifactPolicy -SkipThinkBoot`: passed with 0 failures and 5 local deployment warnings; artifact hygiene reported local-only `.git/.codex`, `snowy-admin-web/node_modules`, and 30 runtime smoke/import/build artifact matches.
- Git Bash `scripts/deployment-readiness.sh --check-artifact-policy --skip-think-boot`: passed with 0 failures and 7 expected Windows-host/local warnings; artifact hygiene reported local-only `.git/.codex`, `snowy-admin-web/node_modules`, and 30 runtime smoke/import/build artifact matches.
- `.\scripts\deployment-readiness.ps1 -CheckFrontendBuildPolicy -SkipThinkBoot`: passed with 0 failures and 3 local deployment warnings; frontend build policy confirmed package-lock, production env shape, Vite build settings, and dist output, with 5 frontend temporary build artifacts reported.
- Git Bash `scripts/deployment-readiness.sh --check-frontend-build-policy --skip-think-boot`: passed with 0 failures and 5 expected Windows-host/local warnings; frontend build policy confirmed package-lock, production env shape, Vite build settings, and dist output, with 5 frontend temporary build artifacts reported.
- `.\scripts\deployment-readiness.ps1 -CheckComposerPolicy -SkipThinkBoot`: passed with 0 failures and 3 local deployment warnings; Composer policy confirmed manifest/lock parseability, required ThinkPHP packages, autoload mappings, post-autoload scripts, vendor metadata, and read-only `composer validate`, with local `require-dev` packages reported in `vendor`.
- Git Bash `scripts/deployment-readiness.sh --check-composer-policy --skip-think-boot`: passed with 0 failures and 5 expected Windows-host/local warnings; Composer policy confirmed manifest/lock parseability, required ThinkPHP packages, autoload mappings, post-autoload scripts, vendor metadata, and read-only `composer validate`, with local `require-dev` packages reported in `vendor`.
- `.\scripts\deployment-readiness.ps1 -CheckReleasePackagePolicy -SkipThinkBoot`: passed with 0 failures and 4 local deployment warnings; release package policy confirmed required backend/frontend entries and public-root exposure, and reported that the current source root still contains entries/runtime artifacts that must be excluded from a final release package.
- Git Bash `scripts/deployment-readiness.sh --check-release-package-policy --skip-think-boot`: passed with 0 failures and 6 expected Windows-host/local warnings; release package policy confirmed required backend/frontend entries and public-root exposure, and reported source-root entries that must be excluded from a final release package.
- `.\scripts\deployment-readiness.ps1 -CheckBackupTools -SkipThinkBoot`: passed with 0 failures and local backup readiness warnings.
- Git Bash `scripts/deployment-readiness.sh --check-backup-tools --skip-think-boot`: passed with 0 failures and local backup readiness warnings.
- `.\scripts\project-progress.ps1 -CheckDeploy -Lean`: passed.
- `.\scripts\project-progress.ps1 -CheckDeploy -ExpectedPublicRoot .\public -Lean`: passed with the Git secret/artifact and PHP upload/body limit guards.
- `.\scripts\project-progress.ps1 -CheckDeploy -ExpectedPublicRoot .\public -PublicBaseUrl <temporary public server> -Lean`: passed with the HTTP public-exposure guard.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckErrorLogPolicy -Lean`: passed with local PHP error/log policy warnings.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckOpcachePolicy -Lean`: passed with local OPcache readiness warnings.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckSchedulerPolicy -Lean`: passed with scheduler/queue policy readiness included.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckCachePolicy -Lean`: passed with cache/Redis policy readiness included.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckCookiePolicy -Lean`: passed with cookie/session policy readiness included.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckUrlPolicy -Lean`: passed with URL/HTTPS policy readiness included.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckStoragePolicy -Lean`: passed with file storage policy readiness included.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckProviderPolicy -Lean`: passed with provider/deferred-send policy readiness included.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckEnvTemplatePolicy -Lean`: passed with environment template policy readiness included.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckRuntimePermissionPolicy -Lean`: passed with runtime permission/path-scope policy readiness included.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckWebServerPolicy -CheckNginxSyntax -CheckPhpFpmSyntax -Lean`: passed with web-server command/syntax readiness included.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckDatabaseSchema -Lean`: passed with database schema readiness included.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckArtifactPolicy -Lean`: passed with deployment artifact hygiene readiness included.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckFrontendBuildPolicy -Lean`: passed with frontend build policy readiness included.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckComposerPolicy -Lean`: passed with Composer dependency/autoload policy readiness included.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckReleasePackagePolicy -Lean`: passed with release package include/exclude policy readiness included.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckBackupTools -Lean`: passed with local backup readiness warnings.
- `git diff --check -- scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 public/storage/.gitignore docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/api-gap-map.md docs/tasks/refactor-progress-dashboard.md docs/tasks/problem-optimization-log.md STATUS.md IMPLEMENT.md PLANS.md`: passed with existing LF/CRLF warnings only.
- `git diff --check -- scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/scheduler-queue-policy.md docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with existing LF/CRLF warnings only after the storage policy guard.
- `rg -n "[ \t]+$" scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/scheduler-queue-policy.md docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with no matches after the storage policy guard.
- `git diff --check -- scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/scheduler-queue-policy.md docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with existing LF/CRLF warnings only after the provider policy guard.
- `rg -n "[ \t]+$" scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/scheduler-queue-policy.md docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with no matches after the provider policy guard.
- `git diff --check -- scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/scheduler-queue-policy.md docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with existing LF/CRLF warnings only after the database schema guard.
- `rg -n "[ \t]+$" scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/scheduler-queue-policy.md docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with no matches after the database schema guard.
- `git diff --check -- scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/scheduler-queue-policy.md docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with existing LF/CRLF warnings only after the deployment artifact hygiene guard.
- `rg -n "[ \t]+$" scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/scheduler-queue-policy.md docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with no matches after the deployment artifact hygiene guard.
- `git diff --check -- scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/scheduler-queue-policy.md docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with existing LF/CRLF warnings only after the frontend build policy guard.
- `rg -n "[ \t]+$" scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/scheduler-queue-policy.md docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with no matches after the frontend build policy guard.

Current local warning to resolve before staging strict mode:

- `APP_DEBUG=true`
- Local PHP has `upload_max_filesize=2M`, below the default readiness recommendation of `8M`; raise it for staging if import/upload files need more headroom.
- Local CLI PHP has `display_errors=1`, `display_startup_errors=1`, `expose_php=1`, and empty `error_log`; staging/production should set PHP-FPM/web runtime error display and log destinations explicitly.
- Local CLI PHP does not load OPcache; staging/production PHP-FPM should enable OPcache and document deploy/reload behavior.
- Local ThinkPHP cookie config has `secure=false`, `httponly=false`, empty `samesite`, and default session name `PHPSESSID`; staging/production should set cookie/session policy explicitly.
- Local `.env` does not set `APP_HOST`, and no `PublicBaseUrl` is supplied by default; staging/production should use HTTPS URLs for final gates.
- The temporary/local PHP public-root smoke server does not emit release security headers; staging/production must set HSTS on HTTPS, `X-Content-Type-Options: nosniff`, frame protection, CSP, `Referrer-Policy`, and `Permissions-Policy` at the Nginx, frontend, or backend layer.
- The current app has no global CORS middleware signal and one local download response with `Access-Control-Allow-Origin: *`; same-origin `/api` deployment can avoid browser CORS, but cross-origin staging/production must use an explicit origin allowlist and pass the preflight probe.
- Local workstation does not expose `mysqldump`/`mysql`, and `runtime/backup` is missing; staging/production must provide dump and restore tools plus a protected backup directory outside the web root before production writes.
- Git Bash on this Windows workstation reports missing `nginx` and `php-fpm`; staging/production should either pass those checks or document that the services are managed outside the host.
- Local `vendor` includes `require-dev` packages `symfony/var-dumper` and `topthink/think-trace`; staging/production release should use `composer install --no-dev --optimize-autoloader`.
- Current source root is not a clean release package root: it includes local `.env`/source-control/frontend-source/dependency entries and runtime smoke/build artifacts; build a separate release root and verify it with `-CheckReleasePackagePolicy -ReleaseRoot <path>`.

## Follow-Up

- Fill in the actual server Nginx/PHP-FPM checklist from `docs/tasks/deployment-server-checklist.md` when the target host and vhost path are confirmed.
- Keep scheduler/queue workers disabled until a command, process manager, restart policy, logs, retries, rollback, and side-effect plan are documented.
- Keep final online data sync blocked until backup, direction, downtime, rollback, and user approval are documented.
