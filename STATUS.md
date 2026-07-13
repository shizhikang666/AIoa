锘块敇鍧楁晣閸ф鏅ｉ柛褎顨嗛弲? STATUS.md

## 2026-07-08 - merge-agent/api-agent/test-agent - Online Compatibility Follow-up

### Completed

- Continued the pending `OA-ThinkPHP` integration changes for online/prelaunch compatibility.
- Kept the current `/backend` production API prefix for local file download URLs and added a Vite `/backend` development proxy so direct image/download links work in local dev as well as production.
- Added shared `DownloadResponseHeaders` handling and aligned download response headers for payroll export, user exports, generator ZIP, and local file downloads.
- Updated DB smoke expectations and file-download API docs for `/backend/dev/file/download` and file content-type detection.
- Added workflow-smoke compatibility for duplicated historic variables by reading the latest `act_hi_varinst` row, and aligned procurement smokes with the current controlled purchase-order side effect.
- Allowed workflow-normalized date variables in `PurchaseOrderService::workflowDate()` so `Process_procure` can create workflow purchase orders from normalized start variables.
- Preserved the existing broader fixes already present in the worktree: RBAC data-scope payload normalization, workflow detail/current-task data, process variable display values, workflow procure/project/payment form compatibility, frontend process/user selector guards, and finance/workflow validation hardening.

### Modified Files

- `app/support/DownloadResponseHeaders.php`
- `app/support/FileDownloadUrl.php`
- `app/controller/biz/BizPayrollController.php`
- `app/controller/dev/FileController.php`
- `app/controller/gen/BasicController.php`
- `app/controller/sys/UserController.php`
- `app/service/biz/PurchaseOrderService.php`
- `app/service/dev/FileService.php`
- `scripts/test-agent-db-smoke.ps1`
- `scripts/workflow-read-http-smoke.ps1`
- `scripts/workflow-general-start-http-smoke.ps1`
- `scripts/workflow-payment-approve-http-smoke.ps1`
- `scripts/workflow-payment-out-approve-http-smoke.ps1`
- `scripts/workflow-procure-approve-http-smoke.ps1`
- `scripts/workflow-task-transition-http-smoke.ps1`
- `scripts/sale-project-product-item-standalone-http-smoke.ps1`
- `snowy-admin-web/vite.config.mjs`
- `docs/api/dev-file-readonly-compat.md`
- `docs/api/biz-file-relation-readonly-compat.md`
- `docs/api/biz-customer-readonly.md`
- `docs/tasks/new-conversation-bootstrap.md`

### Test Results

- Full PHP lint over `app` and `route`: passed.
- Focused PHP lint for changed download/controller files: passed.
- `php think route:list`: passed; current route-list output is 595 lines.
- `php think route:list` checks for process/task/download/user-center routes: passed.
- `.\scripts\project-progress.ps1 -Lean`: passed.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 564/564 covered frontend endpoints and zero missing read-like routes.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.
- `.\scripts\test-agent-db-smoke.ps1`: passed against the local runtime bundle.
- `.\scripts\auth-index-read-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82`: passed.
- `.\scripts\business-read-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82`: passed.
- `.\scripts\dev-read-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82`: passed with expected unauthenticated/validation guards.
- `.\scripts\workflow-read-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82`: passed after aligning history-task page assertions with the process-row response shape.
- `.\scripts\workflow-general-start-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82`: passed after controlled procure purchase-order cleanup.
- `.\scripts\workflow-payment-approve-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82`: passed after latest historic-variable ordering.
- `.\scripts\workflow-payment-out-approve-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82`: passed after latest historic-variable ordering.
- `.\scripts\workflow-procure-approve-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82`: passed after controlled purchase-order expectations.
- `.\scripts\workflow-task-transition-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82`: passed after latest historic-variable ordering.
- `.\scripts\project-preflight.ps1`: covered all standard preflight steps through repeated full/resumed runs; final resumed slice passed through workflow process cancel/edit, leave vacation adjustment, and `git diff --check`.
- `.\scripts\web-ready.ps1`: passed after starting ThinkPHP on `127.0.0.1:82` and Vite on `127.0.0.1:83`.
- `.\scripts\browser-upload-provider-guard-smoke.ps1`: passed for the default file/payroll/product/customer/sale-project pages.
- `.\scripts\browser-upload-provider-guard-smoke.ps1 -SkipDefaultTargets -TargetPath /biz/biztask,/biz/biztask/mystarttask,/biz/biztask/processList,/biz/biztask/allprocess`: passed for current workflow menu pages.
- `.\scripts\browser-upload-provider-guard-smoke.ps1 -SkipDefaultTargets -TargetPath /biz/historytask,/biz/copytask`: passed for current history-task and copy-task menu pages.
- `npm run build` in `snowy-admin-web`: passed with existing Vite/Browserslist/CSS warnings only.
- `git diff --check`: passed with existing LF/CRLF warnings only.

### Current Issues

- No blocking issue remains from the DB-backed or authenticated backend HTTP smokes run on the local runtime.
- A stale browser-smoke target `/biz/biztask/historyTask` rendered the frontend 404 page; the current `sys_resource` menu path is `/biz/historytask` with component `biz/biztask/historyTask`, and that actual route passed.

### Next Plan

- Run any remaining manual process-detail/task-form click-through or role data-scope checks needed for release confidence, then package or commit this combined fix set only after explicit approval.

## 2026-07-02 - test-agent - Sales Approval Reject Side-Effect Smoke Matrix

### Completed

- Hardened `scripts/workflow-project-init-approve-http-smoke.ps1` so empty-string assertions are valid.
- Added project-init cancel/reject side-effect checks for empty sale-project `PROCESS_ID`, no `SALE_PROJECT` file relations, and no invoicing rows.
- Re-ran the local HTTP smoke matrix for project init, delivery, play, reissue, and return approval flows.
- Confirmed reject/cancel paths close without creating downstream project files, delivery/inventory rows, finance rows, payment/statement rows, refund rows, or invoice rows outside the one approved baseline path.
- Kept task SSE, gen-config add, and non-`FOLLOW` sale-project product-item mutation in controlled-deferred status pending product approval.

### Modified Files

- `scripts/workflow-project-init-approve-http-smoke.ps1`
- `docs/tasks/test-agent-smoke-runbook.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- PowerShell parse check passed for `scripts/workflow-project-init-approve-http-smoke.ps1`.
- `php think route:list` confirmed the sales workflow start routes and task approve/reject/cancel routes.
- Passed `scripts/workflow-project-init-approve-http-smoke.ps1` against the local runtime bundle.
- Passed `scripts/workflow-project-delivery-approve-http-smoke.ps1` against the local runtime bundle.
- Passed `scripts/workflow-project-play-approve-http-smoke.ps1` against the local runtime bundle.
- Passed `scripts/workflow-project-reissue-approve-http-smoke.ps1` against the local runtime bundle.
- Passed `scripts/workflow-project-return-approve-http-smoke.ps1` against the local runtime bundle.
- `git diff --check` passed for the touched files, with only pre-existing line-ending warnings outside the new documentation slice.

### Current Issues

- None for the sales approval reject/cancel side-effect smoke matrix.

### Next Plan

- Continue with a product-approved workflow/API slice or deployment/runtime hardening; leave controlled-deferred routes closed until explicit approval.

## 2026-06-27 - api-agent/test-agent - HR/Payroll Direct Maintenance

### Completed

- Opened `POST /biz/bizleaveapplication/add` for bounded manual leave-row creation.
- Added overlap validation and current-year `annualLeave` vacation-balance deduction for direct leave add.
- Opened `POST /biz/bizpayroll/add` for bounded manual payroll-row creation.
- Kept workflow/task creation, payroll generation, automatic existing-payroll recalculation, notifications, Java data-change events, Java source edits, schema changes, and `.env` changes out of scope.
- Added `scripts/hr-payroll-direct-maintenance-http-smoke.ps1`.
- Removed the two HR add endpoints from the deferred-wrapper smoke list.

### Modified Files

- `app/controller/biz/BizLeaveApplicationController.php`
- `app/service/biz/BizLeaveApplicationService.php`
- `app/controller/biz/BizPayrollController.php`
- `app/service/biz/BizPayrollService.php`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/hr-payroll-direct-maintenance-http-smoke.ps1`
- `docs/api/biz-leave-application-readonly.md`
- `docs/api/biz-payroll-readonly.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- PHP syntax lint passed for both touched controllers, both touched services, and `route/app.php`.
- PowerShell parse check passed for `scripts/hr-payroll-direct-maintenance-http-smoke.ps1`.
- `php think route:list` lists the two POST routes.
- `scripts/frontend-api-route-gap-smoke.ps1 -FailOnReadMissing` passed with 560/560 covered frontend endpoints.
- No-token HTTP POST to `/biz/bizleaveapplication/add` and `/biz/bizpayroll/add` returned `401`.
- `scripts/hr-payroll-direct-maintenance-http-smoke.ps1` passed after starting the local runtime bundle.
- `scripts/frontend-deferred-write-wrapper-smoke.ps1` passed after removing the two HR add endpoints from the deferred list.
- `git diff --check` for touched files passed.

### Current Issues

- None for this slice after starting the local runtime bundle.

### Next Plan

- Continue with the next product-approved feature block or deployment/runtime hardening item.

## 2026-06-26 - api-agent/test-agent - Environment Template Cleanup

### Completed

- Updated `.example.env` with release-safe deployment template defaults.
- Added documented placeholders for `APP_HOST`, `DB_DRIVER`, cache driver/prefix, and Redis deployment keys.
- Changed the template `APP_DEBUG` default to `false`.
- Kept real `.env` values untouched and did not print or copy secrets.

### Modified Files

- `.example.env`
- `docs/tasks/deployment-runtime-readiness-plan.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `scripts/project-progress.ps1`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `.\scripts\deployment-readiness.ps1 -CheckEnvTemplatePolicy -SkipThinkBoot`: passed with 0 failures and 2 local warnings.
- Git Bash `bash -n scripts/deployment-readiness.sh`: passed.
- Git Bash `scripts/deployment-readiness.sh --check-env-template-policy --skip-think-boot`: passed with 0 failures and 4 expected Windows-host/local warnings.
- `git diff --check -- .example.env`: passed.

### Current Issues

- Local `.env` still has `APP_DEBUG=true`; keep this for local smoke only and set production `.env` to `false`.
- Local PHP upload limit is still `2M`; staging should raise it if imports/uploads require more headroom.

### Next Plan

- Continue deployment/runtime hardening with staging-host evidence: PHP-FPM/Nginx syntax, HTTPS URL/security headers, CORS preflight, backup tools, and clean release-root validation.

## 2026-06-26 - api-agent/test-agent - CORS Policy Readiness

### Completed

- Added optional CORS policy readiness checks to `scripts/deployment-readiness.ps1` and `scripts/deployment-readiness.sh`.
- The guard is enabled by `-CheckCorsPolicy` / `--check-cors-policy`, and automatically runs in production readiness mode.
- Added `-CorsProbeOrigin` / `--cors-probe-origin` for live `OPTIONS` preflight checks against `PublicBaseUrl`.
- The guard checks CORS source signals, global middleware signals, wildcard origin/credential risks, frontend production API prefix shape, and optional preflight response headers.
- The guard does not print response bodies, inject headers, edit app/server/frontend config, reload/restart services, edit `.env`, or write database rows.
- Added CORS policy passthrough to `scripts/project-progress.ps1 -CheckDeploy`.
- Updated `docs/tasks/deployment-runtime-readiness-plan.md`, `docs/tasks/deployment-server-checklist.md`, and `docs/tasks/refactor-progress-dashboard.md`.

### Modified Files

- `scripts/deployment-readiness.ps1`
- `scripts/deployment-readiness.sh`
- `scripts/project-progress.ps1`
- `docs/tasks/deployment-runtime-readiness-plan.md`
- `docs/tasks/deployment-server-checklist.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- PowerShell parser checks for `scripts\deployment-readiness.ps1` and `scripts\project-progress.ps1`: passed.
- Git Bash `bash -n scripts/deployment-readiness.sh`: passed through `C:\Program Files\Git\bin\bash.exe`.
- `.\scripts\deployment-readiness.ps1 -CheckCorsPolicy -SkipThinkBoot`: passed with 0 failures and 5 local deployment warnings.
- Git Bash `scripts/deployment-readiness.sh --check-cors-policy --skip-think-boot`: passed with 0 failures and 7 expected Windows-host/local warnings.
- Local TCP CORS fixture with reflected origin, `Vary: Origin`, allowed `GET`, and allowed `Authorization, Content-Type`: PowerShell passed with 0 failures and 4 local warnings; Git Bash passed with 0 failures and 6 expected Windows-host/local warnings.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckCorsPolicy -PublicBaseUrl <temporary CORS fixture> -CorsProbeOrigin https://oa.example.com -HttpProbeTimeoutSeconds 2 -Lean`: passed; passthrough confirmed.
- Temporary PHP public-root CORS preflight: passed with 0 failures and expected missing CORS response-header warnings.

### Current Issues

- There is no global app-level CORS middleware signal in `app/middleware.php`.
- One local download response still emits `Access-Control-Allow-Origin: *`; production should use same-origin `/api` or an explicit origin allowlist for admin/API traffic.
- The temporary local PHP public-root server does not emit CORS preflight headers; staging cross-origin deployment must verify `Access-Control-Allow-Origin`, `Vary: Origin`, allowed methods, and allowed `Authorization`/`Content-Type` headers.

### Next Plan

- Continue deployment/runtime hardening, or run the CORS guard against the actual staging API URL and frontend origin once the domain layout is confirmed.

## 2026-06-26 - api-agent/test-agent - HTTP Security Headers Policy Readiness

### Completed

- Added optional HTTP security-header readiness checks to `scripts/deployment-readiness.ps1` and `scripts/deployment-readiness.sh`.
- The guard is enabled by `-CheckSecurityHeadersPolicy` / `--check-security-headers-policy`, and automatically runs in production readiness mode.
- The guard checks the `PublicBaseUrl` entry response for HSTS, `X-Content-Type-Options: nosniff`, `X-Frame-Options` or CSP `frame-ancestors`, CSP, `Referrer-Policy`, and `Permissions-Policy`.
- The guard does not print response bodies, edit server/frontend/backend config, inject headers, reload/restart services, edit `.env`, or write database rows.
- Added security-header policy passthrough to `scripts/project-progress.ps1 -CheckDeploy`.
- Updated `docs/tasks/deployment-runtime-readiness-plan.md`, `docs/tasks/deployment-server-checklist.md`, and `docs/tasks/refactor-progress-dashboard.md`.

### Modified Files

- `scripts/deployment-readiness.ps1`
- `scripts/deployment-readiness.sh`
- `scripts/project-progress.ps1`
- `docs/tasks/deployment-runtime-readiness-plan.md`
- `docs/tasks/deployment-server-checklist.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- PowerShell parser checks for `scripts\deployment-readiness.ps1` and `scripts\project-progress.ps1`: passed.
- Git Bash `bash -n scripts/deployment-readiness.sh`: passed through `C:\Program Files\Git\bin\bash.exe`.
- `.\scripts\deployment-readiness.ps1 -CheckSecurityHeadersPolicy -SkipThinkBoot`: passed with 0 failures and 3 local deployment warnings; empty `PublicBaseUrl` was reported.
- Git Bash `scripts/deployment-readiness.sh --check-security-headers-policy --skip-think-boot`: passed with 0 failures and 5 expected Windows-host/local warnings; empty `PublicBaseUrl` was reported.
- Temporary PHP public-root server security-header probe in PowerShell: passed with 0 failures and 7 local warnings; HSTS skipped local HTTP, while missing `X-Content-Type-Options`, frame protection, CSP, `Referrer-Policy`, and `Permissions-Policy` were reported.
- Temporary PHP public-root server security-header probe in Git Bash: passed with 0 failures and 9 expected Windows-host/local warnings.
- Local TCP security-header fixture with release headers: PowerShell passed with 0 failures and 2 local warnings; Git Bash passed with 0 failures and 4 expected Windows-host/local warnings.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckSecurityHeadersPolicy -PublicBaseUrl <temporary public server> -HttpProbeTimeoutSeconds 1 -Lean`: passed; passthrough confirmed.

### Current Issues

- Local/temporary PHP server does not emit release security headers; staging HTTPS must provide HSTS, `nosniff`, frame protection, CSP, `Referrer-Policy`, and `Permissions-Policy`.
- Running the guard without `PublicBaseUrl` only reports that header inspection cannot be performed.

### Next Plan

- Continue deployment/runtime hardening, or run the security-header guard against the actual staging HTTPS URL once the vhost/domain is available.

## 2026-06-26 - api-agent/test-agent - Web Server Syntax Policy Readiness

### Completed

- Added optional Nginx/PHP-FPM command and syntax readiness checks to `scripts/deployment-readiness.ps1`.
- Added Bash `--check-web-server-policy` so command availability can be explicitly requested alongside existing syntax flags.
- Added `-CheckWebServerPolicy`, `-CheckNginxSyntax`, `-CheckPhpFpmSyntax`, `-NginxBinary`, and `-PhpFpmBinary` passthrough to `scripts/project-progress.ps1 -CheckDeploy`.
- The guard can run `nginx -t` and `php-fpm -tt` without printing full config dumps.
- The guard does not edit vhosts, reload/restart services, mutate server processes, edit `.env`, or write database rows.
- Updated `docs/tasks/deployment-runtime-readiness-plan.md`, `docs/tasks/deployment-server-checklist.md`, and `docs/tasks/refactor-progress-dashboard.md`.

### Modified Files

- `scripts/deployment-readiness.ps1`
- `scripts/deployment-readiness.sh`
- `scripts/project-progress.ps1`
- `docs/tasks/deployment-runtime-readiness-plan.md`
- `docs/tasks/deployment-server-checklist.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- PowerShell parser checks for `scripts\deployment-readiness.ps1` and `scripts\project-progress.ps1`: passed.
- Git Bash `bash -n scripts/deployment-readiness.sh`: passed through `C:\Program Files\Git\bin\bash.exe`.
- `.\scripts\deployment-readiness.ps1 -CheckWebServerPolicy -CheckNginxSyntax -CheckPhpFpmSyntax -SkipThinkBoot`: passed with 0 failures and 4 local deployment warnings; local Windows host does not expose `nginx` or `php-fpm`, so syntax checks were not run.
- Git Bash `scripts/deployment-readiness.sh --check-web-server-policy --check-nginx-syntax --check-php-fpm-syntax --skip-think-boot`: passed with 0 failures and 4 expected Windows-host/local warnings; local Git Bash host does not expose `nginx` or `php-fpm`, so syntax checks were not run.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckWebServerPolicy -CheckNginxSyntax -CheckPhpFpmSyntax -Lean`: passed; passthrough confirmed.

### Current Issues

- Local Windows/Git Bash host does not expose `nginx` or `php-fpm`; run the same guard on Linux staging with the actual binary paths to verify syntax.

### Next Plan

- Continue deployment/runtime hardening, or run the web-server policy guard on the target staging host once the vhost/service layout is confirmed.

## 2026-06-26 - api-agent/test-agent - Runtime Permission Policy Readiness

### Completed

- Added an optional runtime permission and path-scope policy guard to `scripts/deployment-readiness.ps1` and `scripts/deployment-readiness.sh`.
- The guard is enabled by `-CheckRuntimePermissionPolicy` / `--check-runtime-permission-policy`, and automatically runs in production readiness mode.
- The guard checks sensitive file path scope, non-public runtime path scope, intended `public/storage` mapping, backup path placement/existence, and Unix mode policy on non-Windows hosts.
- The guard does not run `chmod`, `chown`, cleanup, backup creation, env edits, database writes, or production data operations.
- The Bash guard skips Unix mode checks on Git Bash/Windows to avoid false production-permission signals.
- Added runtime permission policy passthrough to `scripts/project-progress.ps1 -CheckDeploy`.
- Updated `docs/tasks/deployment-runtime-readiness-plan.md`, `docs/tasks/deployment-server-checklist.md`, and `docs/tasks/refactor-progress-dashboard.md`.

### Modified Files

- `scripts/deployment-readiness.ps1`
- `scripts/deployment-readiness.sh`
- `scripts/project-progress.ps1`
- `docs/tasks/deployment-runtime-readiness-plan.md`
- `docs/tasks/deployment-server-checklist.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- PowerShell parser checks for `scripts\deployment-readiness.ps1` and `scripts\project-progress.ps1`: passed.
- Git Bash `bash -n scripts/deployment-readiness.sh`: passed through `C:\Program Files\Git\bin\bash.exe`.
- `.\scripts\deployment-readiness.ps1 -CheckRuntimePermissionPolicy -SkipThinkBoot`: passed with 0 failures and 3 local deployment warnings; sensitive paths and non-public runtime paths were outside `public`, `public/storage` mapped as the public upload/download path, and `runtime/backup` was reported missing.
- Git Bash `scripts/deployment-readiness.sh --check-runtime-permission-policy --skip-think-boot`: passed with 0 failures and 5 expected Windows-host/local warnings; path-scope checks matched PowerShell and Unix mode checks were skipped on Git Bash/Windows.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckRuntimePermissionPolicy -Lean`: passed; passthrough confirmed.

### Current Issues

- `runtime/backup` does not exist yet; staging/production should create and protect a backup directory outside the public web root before production writes.
- Unix file-mode checks are intentionally skipped on this Windows/Git Bash host; run the same guard on Linux staging to verify actual owner/group/other bits.

### Next Plan

- Continue deployment/runtime hardening, or create/protect the backup directory in a separate approved server-prep slice.

## 2026-06-26 - api-agent/test-agent - Environment Template Policy Readiness

### Completed

- Added an optional environment template policy guard to `scripts/deployment-readiness.ps1` and `scripts/deployment-readiness.sh`.
- The guard is enabled by `-CheckEnvTemplatePolicy` / `--check-env-template-policy`, and automatically runs in production readiness mode.
- The guard checks `.example.env` parseability, required runtime/cache/URL key coverage, non-local `.env` key documentation, release-safe `APP_DEBUG` guidance, DB port shape, and secret-placeholder policy.
- The guard does not print env values, edit `.env`, edit `.example.env`, or write database rows.
- The Bash guard uses PHP dotenv parsing for CR-only dotenv files and fixed LF key output for Git Bash compatibility.
- Added environment template policy passthrough to `scripts/project-progress.ps1 -CheckDeploy`.
- Updated `docs/tasks/deployment-runtime-readiness-plan.md`, `docs/tasks/deployment-server-checklist.md`, and `docs/tasks/refactor-progress-dashboard.md`.

### Modified Files

- `scripts/deployment-readiness.ps1`
- `scripts/deployment-readiness.sh`
- `scripts/project-progress.ps1`
- `docs/tasks/deployment-runtime-readiness-plan.md`
- `docs/tasks/deployment-server-checklist.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- PowerShell parser checks for `scripts\deployment-readiness.ps1` and `scripts\project-progress.ps1`: passed.
- Git Bash `bash -n scripts/deployment-readiness.sh`: passed through `C:\Program Files\Git\bin\bash.exe`.
- `.\scripts\deployment-readiness.ps1 -CheckEnvTemplatePolicy -SkipThinkBoot`: passed with 0 failures and 5 local deployment warnings; `.example.env` baseline keys were detected, and missing deployment/cache/Redis/APP_HOST template keys plus release-unsafe `APP_DEBUG` default guidance were reported.
- Git Bash `scripts/deployment-readiness.sh --check-env-template-policy --skip-think-boot`: passed with 0 failures and 8 expected Windows-host/local warnings; `.example.env` baseline keys were detected with PHP dotenv parsing for CR-only files.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckEnvTemplatePolicy -Lean`: passed; passthrough confirmed.

### Current Issues

- `.example.env` does not yet document `DB_DRIVER`, cache/Redis deployment keys, or `APP_HOST`, and its `APP_DEBUG` default is not release-safe.

### Next Plan

- Continue deployment/runtime hardening, or update `.example.env` in a separate approved template-cleanup slice.

## 2026-06-26 - api-agent/test-agent - Release Package Policy Readiness

### Completed

- Added an optional release package include/exclude policy guard to `scripts/deployment-readiness.ps1` and `scripts/deployment-readiness.sh`.
- The guard is enabled by `-CheckReleasePackagePolicy` / `--check-release-package-policy`, and automatically runs in production readiness mode.
- Added `-ReleaseRoot` / `--release-root` so the guard can inspect a clean assembled release directory rather than the source checkout.
- The guard checks required backend runtime entries, Composer vendor metadata, public entry files, frontend `dist` index/assets/manifest, excluded `.env`/source-control/frontend-source/dependency entries, runtime artifacts, and public-root source/config exposure.
- The guard does not build, archive, copy, delete, install dependencies, edit `.env`, or write database rows.
- Added release package policy passthrough to `scripts/project-progress.ps1 -CheckDeploy`.
- Updated `docs/tasks/deployment-runtime-readiness-plan.md`, `docs/tasks/deployment-server-checklist.md`, and `docs/tasks/refactor-progress-dashboard.md`.

### Modified Files

- `scripts/deployment-readiness.ps1`
- `scripts/deployment-readiness.sh`
- `scripts/project-progress.ps1`
- `docs/tasks/deployment-runtime-readiness-plan.md`
- `docs/tasks/deployment-server-checklist.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- PowerShell parser checks for `scripts\deployment-readiness.ps1` and `scripts\project-progress.ps1`: passed.
- Git Bash `bash -n scripts/deployment-readiness.sh`: passed through `C:\Program Files\Git\bin\bash.exe`.
- `.\scripts\deployment-readiness.ps1 -CheckReleasePackagePolicy -SkipThinkBoot`: passed with 0 failures and 4 local deployment warnings; release package policy confirmed required backend/frontend entries and public-root exposure, and reported current source-root entries/runtime artifacts that must be excluded from a final release package.
- Git Bash `scripts/deployment-readiness.sh --check-release-package-policy --skip-think-boot`: passed with 0 failures and 6 expected Windows-host/local warnings; release package policy confirmed required backend/frontend entries and public-root exposure, and reported source-root entries that must be excluded from a final release package.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckReleasePackagePolicy -Lean`: passed; passthrough confirmed.

### Current Issues

- The current source checkout is not a clean release root; it still contains `.env`, source-control metadata, frontend source/dependency files, and runtime smoke/build artifacts.

### Next Plan

- Continue deployment/runtime hardening, or assemble a separate release root and validate it with `-CheckReleasePackagePolicy -ReleaseRoot <path>`.

## 2026-06-26 - api-agent/test-agent - Composer Dependency Policy Readiness

### Completed

- Added an optional Composer dependency/autoload policy guard to `scripts/deployment-readiness.ps1` and `scripts/deployment-readiness.sh`.
- The guard is enabled by `-CheckComposerPolicy` / `--check-composer-policy`, and automatically runs in production readiness mode.
- The guard checks `composer.json`/`composer.lock`, required ThinkPHP packages, autoload mappings, post-autoload scripts, vendor/composer metadata, known `require-dev` package directories, and read-only `composer validate`.
- The guard does not install dependencies, update dependencies, dump autoload, publish vendor assets, clean `vendor`, edit `.env`, or write database rows.
- Added Composer policy passthrough to `scripts/project-progress.ps1 -CheckDeploy`.
- Updated `docs/tasks/deployment-runtime-readiness-plan.md`, `docs/tasks/deployment-server-checklist.md`, and `docs/tasks/refactor-progress-dashboard.md`.

### Modified Files

- `scripts/deployment-readiness.ps1`
- `scripts/deployment-readiness.sh`
- `scripts/project-progress.ps1`
- `docs/tasks/deployment-runtime-readiness-plan.md`
- `docs/tasks/deployment-server-checklist.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- PowerShell parser checks for `scripts\deployment-readiness.ps1` and `scripts\project-progress.ps1`: passed.
- Git Bash `bash -n scripts/deployment-readiness.sh`: passed through `C:\Program Files\Git\bin\bash.exe`.
- `.\scripts\deployment-readiness.ps1 -CheckComposerPolicy -SkipThinkBoot`: passed with 0 failures and 3 local deployment warnings; Composer policy confirmed manifest/lock parseability, required ThinkPHP packages, autoload mappings, post-autoload scripts, vendor metadata, and read-only `composer validate`, with local `require-dev` packages reported in `vendor`.
- Git Bash `scripts/deployment-readiness.sh --check-composer-policy --skip-think-boot`: passed with 0 failures and 5 expected Windows-host/local warnings; Composer policy confirmed manifest/lock parseability, required ThinkPHP packages, autoload mappings, post-autoload scripts, vendor metadata, and read-only `composer validate`, with local `require-dev` packages reported in `vendor`.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckComposerPolicy -Lean`: passed; passthrough confirmed.

### Current Issues

- Local `vendor` contains `symfony/var-dumper` and `topthink/think-trace`; production should install dependencies with `composer install --no-dev --optimize-autoloader`.

### Next Plan

- Continue deployment/runtime hardening, or define the release packaging path that installs production dependencies and excludes dev/vendor artifacts.

## 2026-06-26 - api-agent/test-agent - Frontend Build Policy Readiness

### Completed

- Added an optional frontend build policy guard to `scripts/deployment-readiness.ps1` and `scripts/deployment-readiness.sh`.
- The guard is enabled by `-CheckFrontendBuildPolicy` / `--check-frontend-build-policy`, and automatically runs in production readiness mode.
- The guard checks `snowy-admin-web` production build script, package lock policy, `.env.production` shape, Vite build settings, `dist` output completeness, `dist` source/config exposure, and frontend temporary build artifacts.
- The guard does not install dependencies, run a build, update dependencies, clean artifacts, edit `.env`, or write database rows.
- Added frontend build policy passthrough to `scripts/project-progress.ps1 -CheckDeploy`.
- Updated `docs/tasks/deployment-runtime-readiness-plan.md`, `docs/tasks/deployment-server-checklist.md`, and `docs/tasks/refactor-progress-dashboard.md`.

### Modified Files

- `scripts/deployment-readiness.ps1`
- `scripts/deployment-readiness.sh`
- `scripts/project-progress.ps1`
- `docs/tasks/deployment-runtime-readiness-plan.md`
- `docs/tasks/deployment-server-checklist.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- PowerShell parser checks for `scripts\deployment-readiness.ps1` and `scripts\project-progress.ps1`: passed.
- Git Bash `bash -n scripts/deployment-readiness.sh`: passed through `C:\Program Files\Git\bin\bash.exe`.
- `.\scripts\deployment-readiness.ps1 -CheckFrontendBuildPolicy -SkipThinkBoot`: passed with 0 failures and 3 local deployment warnings; frontend build policy confirmed package-lock, production env shape, Vite build settings, and dist output, with 5 frontend temporary build artifacts reported.
- Git Bash `scripts/deployment-readiness.sh --check-frontend-build-policy --skip-think-boot`: passed with 0 failures and 5 expected Windows-host/local warnings; frontend build policy confirmed package-lock, production env shape, Vite build settings, and dist output, with 5 frontend temporary build artifacts reported.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckFrontendBuildPolicy -Lean`: passed; passthrough confirmed.
- `.\scripts\deployment-readiness.ps1 -ExpectedPublicRoot .\public`: passed with 0 failures and 2 local deployment warnings.
- Git Bash `scripts/deployment-readiness.sh --expected-public-root ./public`: passed with 0 failures and 4 expected Windows-host/local warnings.
- `git diff --check -- scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/scheduler-queue-policy.md docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with existing LF/CRLF warnings only.
- `rg -n "[ \t]+$" scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/scheduler-queue-policy.md docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with no matches.

### Current Issues

- Frontend root still contains `stats.html` and Vite timestamp temp files; this guard only reports them and does not clean them.
- Final release still needs an explicit frontend build/packaging path before staging/production.

### Next Plan

- Continue deployment/runtime hardening, or define the release packaging path that builds frontend assets and excludes temporary frontend artifacts.

## 2026-06-26 - api-agent/test-agent - Deployment Artifact Hygiene Readiness

### Completed

- Added an optional deployment artifact hygiene guard to `scripts/deployment-readiness.ps1` and `scripts/deployment-readiness.sh`.
- The guard is enabled by `-CheckArtifactPolicy` / `--check-artifact-policy`, and automatically runs in production readiness mode.
- The guard checks local-only `.git/.codex`, frontend `node_modules`, and known runtime smoke/import/build artifacts without deleting, moving, archiving, or cleaning files.
- Added deployment artifact hygiene passthrough to `scripts/project-progress.ps1 -CheckDeploy`.
- Updated `docs/tasks/deployment-runtime-readiness-plan.md`, `docs/tasks/deployment-server-checklist.md`, and `docs/tasks/refactor-progress-dashboard.md`.

### Modified Files

- `scripts/deployment-readiness.ps1`
- `scripts/deployment-readiness.sh`
- `scripts/project-progress.ps1`
- `docs/tasks/deployment-runtime-readiness-plan.md`
- `docs/tasks/deployment-server-checklist.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- PowerShell parser checks for `scripts\deployment-readiness.ps1` and `scripts\project-progress.ps1`: passed.
- Git Bash `bash -n scripts/deployment-readiness.sh`: passed through `C:\Program Files\Git\bin\bash.exe`.
- `.\scripts\deployment-readiness.ps1 -CheckArtifactPolicy -SkipThinkBoot`: passed with 0 failures and 5 local deployment warnings; artifact hygiene reported local-only `.git/.codex`, `snowy-admin-web/node_modules`, and 30 runtime artifact matches.
- Git Bash `scripts/deployment-readiness.sh --check-artifact-policy --skip-think-boot`: passed with 0 failures and 7 expected Windows-host/local warnings; artifact hygiene reported local-only `.git/.codex`, `snowy-admin-web/node_modules`, and 30 runtime artifact matches.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckArtifactPolicy -Lean`: passed; passthrough confirmed.
- `.\scripts\deployment-readiness.ps1 -ExpectedPublicRoot .\public`: passed with 0 failures and 2 local deployment warnings.
- Git Bash `scripts/deployment-readiness.sh --expected-public-root ./public`: passed with 0 failures and 4 expected Windows-host/local warnings.
- `git diff --check -- scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/scheduler-queue-policy.md docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with existing LF/CRLF warnings only.
- `rg -n "[ \t]+$" scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/scheduler-queue-policy.md docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with no matches.

### Current Issues

- Local workspace still contains the reported development artifacts; this guard only reports them and does not clean them.
- Production packaging still needs an explicit clean release path before final staging/production rehearsal.

### Next Plan

- Continue deployment/runtime hardening, or define the release packaging/cleanup path once target host and deployment method are confirmed.

## 2026-06-26 - api-agent/test-agent - Database Schema Readiness

### Completed

- Added an optional database schema readiness guard to `scripts/deployment-readiness.ps1` and `scripts/deployment-readiness.sh`.
- The guard is enabled by `-CheckDatabaseSchema` / `--check-database-schema`, and automatically runs in production readiness mode.
- The guard boots ThinkPHP, runs `SELECT 1`, requires the schema to have at least the baseline table volume, verifies 57 curated critical tables, and verifies 38 curated table column groups.
- The guard is read-only: no migrations, DDL, imports, row writes, `.env` edits, secret printing, or data sync.
- Added database schema readiness passthrough to `scripts/project-progress.ps1 -CheckDeploy`.
- Updated `docs/tasks/deployment-runtime-readiness-plan.md`, `docs/tasks/deployment-server-checklist.md`, and `docs/tasks/refactor-progress-dashboard.md`.

### Modified Files

- `scripts/deployment-readiness.ps1`
- `scripts/deployment-readiness.sh`
- `scripts/project-progress.ps1`
- `docs/tasks/deployment-runtime-readiness-plan.md`
- `docs/tasks/deployment-server-checklist.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- PowerShell parser checks for `scripts\deployment-readiness.ps1` and `scripts\project-progress.ps1`: passed.
- Git Bash `bash -n scripts/deployment-readiness.sh`: passed through `C:\Program Files\Git\bin\bash.exe`.
- `.\scripts\deployment-readiness.ps1 -CheckDatabaseSchema -SkipThinkBoot`: passed with 0 failures and 2 local deployment warnings; schema readiness confirmed `SELECT 1`, 121 tables, 57 curated required tables, and 38 curated column groups.
- Git Bash `scripts/deployment-readiness.sh --check-database-schema --skip-think-boot`: passed with 0 failures and 4 expected Windows-host/local warnings; schema readiness confirmed `SELECT 1`, 121 tables, 57 curated required tables, and 38 curated column groups.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckDatabaseSchema -Lean`: passed; passthrough confirmed.
- `.\scripts\deployment-readiness.ps1 -ExpectedPublicRoot .\public`: passed with 0 failures and 2 local deployment warnings.
- Git Bash `scripts/deployment-readiness.sh --expected-public-root ./public`: passed with 0 failures and 4 expected Windows-host/local warnings.
- `git diff --check -- scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/scheduler-queue-policy.md docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with existing LF/CRLF warnings only.
- `rg -n "[ \t]+$" scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/scheduler-queue-policy.md docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with no matches.

### Current Issues

- This slice only verifies the current schema baseline exists; it does not create tables, update migrations, import SQL, or reconcile production data.
- Staging/production still need backup/restore path confirmation before any schema/data operation.

### Next Plan

- Continue deployment/runtime hardening, or run the full production readiness guard on the target host once PHP-FPM/server, Redis, cookie/session, URL, storage, provider, database, and backup settings are confirmed.

## 2026-06-26 - api-agent/test-agent - Provider Deferred Policy Readiness

### Completed

- Added an optional provider/deferred-send policy guard to `scripts/deployment-readiness.ps1` and `scripts/deployment-readiness.sh`.
- The guard is enabled by `-CheckProviderPolicy` / `--check-provider-policy`, and automatically runs in production readiness mode.
- The guard checks provider-deferred documentation, Email/SMS/WebPush/OAuth/cloud-upload deferred source and route signals, known Composer provider SDK package signals, and `SNOWY_SYS_DEFAULT_FILE_ENGINE`.
- Added provider/deferred-send policy passthrough to `scripts/project-progress.ps1 -CheckDeploy`.
- Updated `docs/tasks/deployment-runtime-readiness-plan.md`, `docs/tasks/deployment-server-checklist.md`, and `docs/tasks/refactor-progress-dashboard.md`.

### Modified Files

- `scripts/deployment-readiness.ps1`
- `scripts/deployment-readiness.sh`
- `scripts/project-progress.ps1`
- `docs/tasks/deployment-runtime-readiness-plan.md`
- `docs/tasks/deployment-server-checklist.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- PowerShell parser checks for `scripts\deployment-readiness.ps1` and `scripts\project-progress.ps1`: passed.
- Git Bash `bash -n scripts/deployment-readiness.sh`: passed through `C:\Program Files\Git\bin\bash.exe`.
- `.\scripts\deployment-readiness.ps1 -CheckProviderPolicy -SkipThinkBoot`: passed with 0 failures and 2 local deployment warnings; provider/deferred-send policy checks were OK and default dynamic file engine remained `LOCAL`.
- Git Bash `scripts/deployment-readiness.sh --check-provider-policy --skip-think-boot`: passed with 0 failures and 4 expected Windows-host/local warnings; provider/deferred-send policy checks were OK and default dynamic file engine remained `LOCAL`.
- `.\scripts\deployment-readiness.ps1 -ExpectedPublicRoot .\public`: passed with 0 failures and 2 local deployment warnings.
- Git Bash `scripts/deployment-readiness.sh --expected-public-root ./public`: passed with 0 failures and 4 expected Windows-host/local warnings.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckProviderPolicy -Lean`: passed; passthrough confirmed.
- `git diff --check -- scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/scheduler-queue-policy.md docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with existing LF/CRLF warnings only.
- `rg -n "[ \t]+$" scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/scheduler-queue-policy.md docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with no matches.

### Current Issues

- This slice only verifies that provider-capable surfaces remain controlled-deferred or explicitly documented; it does not send messages, call providers, upload to cloud storage, validate credentials, or enable provider packages.
- Real Email/SMS/WebPush/OAuth/cloud storage enablement still needs a dedicated provider plan with credentials, rate limits, audit records, retries, and safe test-recipient behavior.

### Next Plan

- Continue local deployment/runtime hardening, or run the production readiness guard on the target host once provider, storage, URL, cookie/session, Redis, and PHP-FPM/server settings are confirmed.

## 2026-06-26 - api-agent/test-agent - File Storage Policy Readiness

### Completed

- Added an optional file storage policy guard to `scripts/deployment-readiness.ps1` and `scripts/deployment-readiness.sh`.
- The guard is enabled by `-CheckStoragePolicy` / `--check-storage-policy`, and automatically runs in production readiness mode.
- The guard checks ThinkPHP filesystem default/local/public disk configuration, disk roots, public disk URL/visibility, and DevFile local upload root exposure without uploading, deleting, or writing files.
- Added file storage policy passthrough to `scripts/project-progress.ps1 -CheckDeploy`.
- Updated `docs/tasks/deployment-runtime-readiness-plan.md`, `docs/tasks/deployment-server-checklist.md`, and `docs/tasks/refactor-progress-dashboard.md`.

### Modified Files

- `scripts/deployment-readiness.ps1`
- `scripts/deployment-readiness.sh`
- `scripts/project-progress.ps1`
- `docs/tasks/deployment-runtime-readiness-plan.md`
- `docs/tasks/deployment-server-checklist.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- PowerShell parser checks for `scripts\deployment-readiness.ps1` and `scripts\project-progress.ps1`: passed.
- Git Bash `bash -n scripts/deployment-readiness.sh`: passed through `C:\Program Files\Git\bin\bash.exe`.
- `.\scripts\deployment-readiness.ps1 -CheckStoragePolicy -SkipThinkBoot`: passed with 0 failures and 2 local deployment warnings; filesystem and DevFile storage policy checks were OK.
- Git Bash `scripts/deployment-readiness.sh --check-storage-policy --skip-think-boot`: passed with 0 failures and 4 expected Windows-host/local warnings; filesystem and DevFile storage policy checks were OK.
- `.\scripts\deployment-readiness.ps1 -ExpectedPublicRoot .\public`: passed with 0 failures and 2 local deployment warnings.
- Git Bash `scripts/deployment-readiness.sh --expected-public-root ./public`: passed with 0 failures and 4 expected Windows-host/local warnings.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckStoragePolicy -Lean`: passed; passthrough confirmed.
- `git diff --check -- scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/scheduler-queue-policy.md docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with existing LF/CRLF warnings only.
- `rg -n "[ \t]+$" scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/scheduler-queue-policy.md docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with no matches.

### Current Issues

- This slice only verifies local filesystem storage policy; it does not upload files, delete files, write storage files, edit `config/filesystem.php`, or validate cloud storage/provider credentials.
- Target host PHP-FPM user ownership/writability and any future cloud storage policy still need confirmation on the server.

### Next Plan

- Continue local deployment/runtime hardening, or run the production readiness guard on the target host once storage, URL, cookie/session, Redis, and PHP-FPM/server settings are confirmed.

## 2026-06-26 - api-agent/test-agent - URL/HTTPS Policy Readiness

### Completed

- Added an optional URL/HTTPS policy guard to `scripts/deployment-readiness.ps1` and `scripts/deployment-readiness.sh`.
- The guard is enabled by `-CheckUrlPolicy` / `--check-url-policy`, and automatically runs in production readiness mode.
- The guard checks `APP_HOST` and `PublicBaseUrl` URL format and HTTPS policy without editing `.env` or server config.
- Localhost HTTP is allowed for local smoke; production readiness fails when configured non-local URLs are not HTTPS.
- Added URL/HTTPS policy passthrough to `scripts/project-progress.ps1 -CheckDeploy`.
- Updated `docs/tasks/deployment-runtime-readiness-plan.md`, `docs/tasks/deployment-server-checklist.md`, and `docs/tasks/refactor-progress-dashboard.md`.

### Modified Files

- `scripts/deployment-readiness.ps1`
- `scripts/deployment-readiness.sh`
- `scripts/project-progress.ps1`
- `docs/tasks/deployment-runtime-readiness-plan.md`
- `docs/tasks/deployment-server-checklist.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- PowerShell parser checks for `scripts\deployment-readiness.ps1` and `scripts\project-progress.ps1`: passed.
- Git Bash `bash -n scripts/deployment-readiness.sh`: passed through `C:\Program Files\Git\bin\bash.exe`.
- `.\scripts\deployment-readiness.ps1 -ExpectedPublicRoot .\public`: passed with 0 failures and 2 local deployment warnings.
- Git Bash `scripts/deployment-readiness.sh --expected-public-root ./public`: passed with 0 failures and 4 expected Windows-host/local warnings.
- `.\scripts\deployment-readiness.ps1 -CheckUrlPolicy -SkipThinkBoot`: passed with 0 failures and local URL/HTTPS policy warnings for empty `APP_HOST` and `PublicBaseUrl`.
- Git Bash `scripts/deployment-readiness.sh --check-url-policy --skip-think-boot`: passed with 0 failures and expected Windows-host/local URL/HTTPS policy warnings.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckUrlPolicy -Lean`: passed; passthrough confirmed.
- `git diff --check -- scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/scheduler-queue-policy.md docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with existing LF/CRLF warnings only.
- `rg -n "[ \t]+$" scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/scheduler-queue-policy.md docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with no matches.

### Current Issues

- Local `.env` does not set `APP_HOST`, and no `PublicBaseUrl` is supplied by default; staging/production should use HTTPS URLs for final readiness gates.
- Final domain, CORS, DNS, TLS certificates, and vhost edits remain deferred until the target host is confirmed.

### Next Plan

- Continue local deployment/runtime hardening, or run the production readiness guard on the target host once URL, cookie/session, Redis, and PHP-FPM/server settings are confirmed.

## 2026-06-26 - api-agent/test-agent - Cookie/Session Policy Readiness

### Completed

- Added an optional cookie/session policy guard to `scripts/deployment-readiness.ps1` and `scripts/deployment-readiness.sh`.
- The guard is enabled by `-CheckCookiePolicy` / `--check-cookie-policy`, and automatically runs in production readiness mode.
- The guard checks ThinkPHP cookie secure, HttpOnly, SameSite, path, session name, session type, and session expiry without editing config files.
- Added cookie/session policy passthrough to `scripts/project-progress.ps1 -CheckDeploy`.
- Updated `docs/tasks/deployment-runtime-readiness-plan.md`, `docs/tasks/deployment-server-checklist.md`, and `docs/tasks/refactor-progress-dashboard.md`.

### Modified Files

- `scripts/deployment-readiness.ps1`
- `scripts/deployment-readiness.sh`
- `scripts/project-progress.ps1`
- `docs/tasks/deployment-runtime-readiness-plan.md`
- `docs/tasks/deployment-server-checklist.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- PowerShell parser checks for `scripts\deployment-readiness.ps1` and `scripts\project-progress.ps1`: passed.
- Git Bash `bash -n scripts/deployment-readiness.sh`: passed through `C:\Program Files\Git\bin\bash.exe`.
- `.\scripts\deployment-readiness.ps1 -ExpectedPublicRoot .\public`: passed with 0 failures and 2 local deployment warnings.
- Git Bash `scripts/deployment-readiness.sh --expected-public-root ./public`: passed with 0 failures and 4 expected Windows-host/local warnings.
- `.\scripts\deployment-readiness.ps1 -CheckCookiePolicy -SkipThinkBoot`: passed with 0 failures and expected local cookie/session policy warnings.
- Git Bash `scripts/deployment-readiness.sh --check-cookie-policy --skip-think-boot`: passed with 0 failures and expected Windows-host/local cookie/session policy warnings.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckCookiePolicy -Lean`: passed; passthrough confirmed.
- `git diff --check -- scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/scheduler-queue-policy.md docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with existing LF/CRLF warnings only.
- `rg -n "[ \t]+$" scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/scheduler-queue-policy.md docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with no matches.

### Current Issues

- Local ThinkPHP cookie config still has `secure=false`, `httponly=false`, empty `samesite`, and default session name `PHPSESSID`; staging/production must set cookie/session policy explicitly.
- This slice only adds readiness checks; it does not edit cookie/session config or decide final cross-site cookie/domain behavior.

### Next Plan

- Continue local deployment/runtime hardening, or run the production readiness guard on the target host once cookie/session, Redis, and PHP-FPM/server settings are confirmed.

## 2026-06-26 - api-agent/test-agent - Cache/Redis Policy Readiness

### Completed

- Added an optional cache/Redis policy guard to `scripts/deployment-readiness.ps1` and `scripts/deployment-readiness.sh`.
- The guard is enabled by `-CheckCachePolicy` / `--check-cache-policy`, and automatically runs in production readiness mode.
- The guard checks `CACHE_DRIVER`, Redis host, port, database, timeout, password-policy signals, and TCP reachability without writing cache data or printing secrets.
- Added cache/Redis policy passthrough to `scripts/project-progress.ps1 -CheckDeploy`.
- Updated `docs/tasks/deployment-runtime-readiness-plan.md`, `docs/tasks/deployment-server-checklist.md`, and `docs/tasks/refactor-progress-dashboard.md`.

### Modified Files

- `scripts/deployment-readiness.ps1`
- `scripts/deployment-readiness.sh`
- `scripts/project-progress.ps1`
- `docs/tasks/deployment-runtime-readiness-plan.md`
- `docs/tasks/deployment-server-checklist.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- PowerShell parser checks for `scripts\deployment-readiness.ps1` and `scripts\project-progress.ps1`: passed.
- Git Bash `bash -n scripts/deployment-readiness.sh`: passed through `C:\Program Files\Git\bin\bash.exe`.
- `.\scripts\deployment-readiness.ps1 -ExpectedPublicRoot .\public`: passed with 0 failures and 2 local deployment warnings.
- Git Bash `scripts/deployment-readiness.sh --expected-public-root ./public`: passed with 0 failures and 4 expected Windows-host/local warnings.
- `.\scripts\deployment-readiness.ps1 -CheckCachePolicy -SkipThinkBoot`: passed with 0 failures and 2 local deployment warnings; local Redis TCP reachability passed.
- Git Bash `scripts/deployment-readiness.sh --check-cache-policy --skip-think-boot`: passed with 0 failures and 4 expected Windows-host/local warnings; local Redis TCP reachability passed.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckCachePolicy -Lean`: passed; passthrough confirmed.
- `git diff --check -- scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/scheduler-queue-policy.md docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with existing LF/CRLF warnings only.
- `rg -n "[ \t]+$" scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/scheduler-queue-policy.md docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with no matches.

### Current Issues

- This slice only verifies cache driver policy and Redis TCP reachability; it does not authenticate to Redis, write cache keys, flush data, or change Redis/server configuration.
- Redis production network/auth policy still needs to be confirmed on the target host.

### Next Plan

- Continue local deployment/runtime hardening, or run the production readiness guard on the target host once Redis and PHP-FPM/server settings are confirmed.

## 2026-06-26 - api-agent/test-agent - Scheduler/Queue Policy Readiness

### Completed

- Added an optional scheduler/queue policy guard to `scripts/deployment-readiness.ps1` and `scripts/deployment-readiness.sh`.
- The guard is enabled by `-CheckSchedulerPolicy` / `--check-scheduler-policy`, and automatically runs in production readiness mode.
- The guard checks `docs/tasks/scheduler-queue-policy.md`, ThinkPHP console command registration, app command class signals, known queue/worker dependency signals, and dev-job runtime controls without executing jobs.
- Added scheduler/queue policy passthrough to `scripts/project-progress.ps1 -CheckDeploy`.
- Added `docs/tasks/scheduler-queue-policy.md` documenting the current disabled-worker policy and prerequisites before enabling any worker/job runtime.
- Updated `docs/tasks/deployment-runtime-readiness-plan.md`, `docs/tasks/deployment-server-checklist.md`, and `docs/tasks/refactor-progress-dashboard.md`.

### Modified Files

- `scripts/deployment-readiness.ps1`
- `scripts/deployment-readiness.sh`
- `scripts/project-progress.ps1`
- `docs/tasks/scheduler-queue-policy.md`
- `docs/tasks/deployment-runtime-readiness-plan.md`
- `docs/tasks/deployment-server-checklist.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- PowerShell parser checks for `scripts\deployment-readiness.ps1` and `scripts\project-progress.ps1`: passed.
- Git Bash `bash -n scripts/deployment-readiness.sh`: passed through `C:\Program Files\Git\bin\bash.exe`.
- `.\scripts\deployment-readiness.ps1 -ExpectedPublicRoot .\public`: passed with 0 failures and 2 local deployment warnings.
- Git Bash `scripts/deployment-readiness.sh --expected-public-root ./public`: passed with 0 failures and 4 expected Windows-host/local warnings.
- `.\scripts\deployment-readiness.ps1 -CheckSchedulerPolicy -SkipThinkBoot`: passed with 0 failures and 2 local deployment warnings.
- Git Bash `scripts/deployment-readiness.sh --check-scheduler-policy --skip-think-boot`: passed with 0 failures and 4 expected Windows-host/local warnings.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckSchedulerPolicy -Lean`: passed; passthrough confirmed.
- `git diff --check -- scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/scheduler-queue-policy.md docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with existing LF/CRLF warnings only.
- `rg -n "[ \t]+$" scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/scheduler-queue-policy.md docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with no matches.

### Current Issues

- Scheduler/queue workers remain intentionally disabled. The project has dev-job compatibility controls, but readiness checks only document and inspect them; they do not execute jobs.
- Any future worker enablement still needs an explicit command, process manager, restart policy, log path, retry/failure behavior, rollback plan, side-effect boundary, backup readiness, and user approval.

### Next Plan

- Continue local deployment/runtime hardening, or run the production readiness guard on the target host once scheduler/queue policy and PHP-FPM/server settings are confirmed.

## 2026-06-26 - api-agent/test-agent - PHP OPcache Policy Readiness

### Completed

- Added an optional PHP OPcache policy guard to `scripts/deployment-readiness.ps1` and `scripts/deployment-readiness.sh`.
- The guard is enabled by `-CheckOpcachePolicy` / `--check-opcache-policy`, and automatically runs in production readiness mode.
- The guard reads OPcache extension loading and common OPcache ini values without editing PHP config.
- Readiness fails in production mode when OPcache is unavailable or disabled.
- OPcache tuning/deploy-reload strategy checks remain warnings so they can be confirmed per host.
- Added OPcache policy passthrough to `scripts/project-progress.ps1 -CheckDeploy`.
- Updated `docs/tasks/deployment-runtime-readiness-plan.md`, `docs/tasks/deployment-server-checklist.md`, and `docs/tasks/refactor-progress-dashboard.md`.

### Modified Files

- `scripts/deployment-readiness.ps1`
- `scripts/deployment-readiness.sh`
- `scripts/project-progress.ps1`
- `docs/tasks/deployment-runtime-readiness-plan.md`
- `docs/tasks/deployment-server-checklist.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- PowerShell parser checks for `scripts\deployment-readiness.ps1` and `scripts\project-progress.ps1`: passed.
- Git Bash `bash -n scripts/deployment-readiness.sh`: passed.
- `.\scripts\deployment-readiness.ps1 -ExpectedPublicRoot .\public`: passed with 0 failures and 2 local deployment warnings.
- Git Bash `scripts/deployment-readiness.sh --expected-public-root ./public`: passed with 0 failures and 4 expected Windows-host/local warnings.
- `.\scripts\deployment-readiness.ps1 -CheckOpcachePolicy -SkipThinkBoot`: passed with 0 failures and expected local OPcache warnings.
- Git Bash `scripts/deployment-readiness.sh --check-opcache-policy --skip-think-boot`: passed with 0 failures and expected local OPcache warnings.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckOpcachePolicy -Lean`: passed; passthrough confirmed.
- `git diff --check -- scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with existing LF/CRLF warnings only.
- `rg -n "[ \t]+$" scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with no matches.

### Current Issues

- Local CLI PHP does not load OPcache; staging/production PHP-FPM should enable OPcache and document deploy/reload behavior.
- PHP-FPM/php.ini edits, OPcache reset/warmup automation, deploy reload hooks, server package/service changes, production data operations, and final online data sync remain deferred until a target host and user approval are confirmed.

### Next Plan

- Continue local deployment/runtime hardening, or run the production readiness guard on the target host once PHP-FPM OPcache policy is confirmed.

## 2026-06-26 - api-agent/test-agent - PHP Error Log Policy Readiness

### Completed

- Added an optional PHP error display/logging policy guard to `scripts/deployment-readiness.ps1` and `scripts/deployment-readiness.sh`.
- The guard is enabled by `-CheckErrorLogPolicy` / `--check-error-log-policy`, and automatically runs in production readiness mode.
- The guard reads `display_errors`, `display_startup_errors`, `log_errors`, `error_log`, `expose_php`, and `html_errors` without editing PHP config.
- Readiness fails in production mode when public error display/header exposure settings are enabled, or when PHP error logging is disabled.
- Empty `error_log` remains a warning so PHP-FPM/web-server log routing can be documented when handled outside CLI PHP.
- Added error/log policy passthrough to `scripts/project-progress.ps1 -CheckDeploy`.
- Updated `docs/tasks/deployment-runtime-readiness-plan.md`, `docs/tasks/deployment-server-checklist.md`, and `docs/tasks/refactor-progress-dashboard.md`.

### Modified Files

- `scripts/deployment-readiness.ps1`
- `scripts/deployment-readiness.sh`
- `scripts/project-progress.ps1`
- `docs/tasks/deployment-runtime-readiness-plan.md`
- `docs/tasks/deployment-server-checklist.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- PowerShell parser checks for `scripts\deployment-readiness.ps1` and `scripts\project-progress.ps1`: passed.
- Git Bash `bash -n scripts/deployment-readiness.sh`: passed.
- `.\scripts\deployment-readiness.ps1 -ExpectedPublicRoot .\public`: passed with 0 failures and 2 local deployment warnings.
- Git Bash `scripts/deployment-readiness.sh --expected-public-root ./public`: passed with 0 failures and 4 expected Windows-host/local warnings.
- `.\scripts\deployment-readiness.ps1 -CheckErrorLogPolicy -SkipThinkBoot`: passed with 0 failures and expected local PHP error/log policy warnings.
- Git Bash `scripts/deployment-readiness.sh --check-error-log-policy --skip-think-boot`: passed with 0 failures and expected local PHP error/log policy warnings.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckErrorLogPolicy -Lean`: passed; passthrough confirmed.
- `.\scripts\deployment-readiness.ps1 -Production -SkipThinkBoot`: expected local production gate failure, including PHP error display, `APP_DEBUG`, and backup readiness failures.
- Git Bash `scripts/deployment-readiness.sh --production --skip-think-boot`: expected local production gate failure, including PHP error display, `APP_DEBUG`, and backup readiness failures.
- `git diff --check -- scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with existing LF/CRLF warnings only.
- `rg -n "[ \t]+$" scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with no matches.

### Current Issues

- Local CLI PHP reports `display_errors=1`, `display_startup_errors=1`, `expose_php=1`, and empty `error_log`; staging/production should set PHP-FPM/web runtime error display and log routing explicitly.
- PHP-FPM/php.ini edits, Nginx/PHP-FPM log path inspection, server package/service changes, production `.env` changes, production data operations, and final online data sync remain deferred until a target host and user approval are confirmed.

### Next Plan

- Continue local deployment/runtime hardening, or run the production readiness guard on the target host once PHP-FPM and web-server log policy are confirmed.

## 2026-06-26 - api-agent/test-agent - Backup Tool Readiness

### Completed

- Added an optional backup/restore tool guard to `scripts/deployment-readiness.ps1` and `scripts/deployment-readiness.sh`.
- The guard is enabled by `-CheckBackupTools` / `--check-backup-tools`, and automatically runs in production readiness mode.
- The guard checks for `mysqldump` and `mysql` or configured equivalents without dumping data.
- The guard verifies backup-related DB `.env` keys are present without printing values.
- The guard checks whether the configured backup directory exists and is writable by the current user.
- Added backup guard passthrough to `scripts/project-progress.ps1 -CheckDeploy`.
- Updated `docs/tasks/deployment-runtime-readiness-plan.md`, `docs/tasks/deployment-server-checklist.md`, and `docs/tasks/refactor-progress-dashboard.md`.

### Modified Files

- `scripts/deployment-readiness.ps1`
- `scripts/deployment-readiness.sh`
- `scripts/project-progress.ps1`
- `docs/tasks/deployment-runtime-readiness-plan.md`
- `docs/tasks/deployment-server-checklist.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- PowerShell parser checks for `scripts\deployment-readiness.ps1` and `scripts\project-progress.ps1`: passed.
- Git Bash `bash -n scripts/deployment-readiness.sh`: passed.
- `.\scripts\deployment-readiness.ps1 -ExpectedPublicRoot .\public`: passed with 0 failures and 2 local deployment warnings.
- Git Bash `scripts/deployment-readiness.sh --expected-public-root ./public`: passed with 0 failures and 4 expected Windows-host/local warnings.
- `.\scripts\deployment-readiness.ps1 -CheckBackupTools -SkipThinkBoot`: passed with 0 failures and expected local backup readiness warnings.
- Git Bash `scripts/deployment-readiness.sh --check-backup-tools --skip-think-boot`: passed with 0 failures and expected local backup readiness warnings.
- `.\scripts\project-progress.ps1 -CheckDeploy -CheckBackupTools -Lean`: passed; passthrough confirmed.
- `git diff --check -- scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with existing LF/CRLF warnings only.
- `rg -n "[ \t]+$" scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with no matches.

### Current Issues

- Local workstation does not expose `mysqldump` or `mysql`, so the explicit backup readiness guard reports those as warnings outside production mode.
- `runtime/backup` is missing locally; create/protect the real backup directory on staging/production before production writes.
- Actual dumps, restore drills, retention automation, uploaded-file backup automation, package installation, production data operations, and final online data sync remain deferred until target host and user approval are confirmed.

### Next Plan

- Continue local deployment/runtime hardening, or run the backup guard on the target host after dump/restore tools and backup directory policy are confirmed.

## 2026-06-26 - api-agent/test-agent - HTTP Public Exposure Readiness

### Completed

- Added an optional HTTP public-exposure probe to `scripts/deployment-readiness.ps1` and `scripts/deployment-readiness.sh`.
- The probe is enabled only when `-PublicBaseUrl` or `--public-base-url` is supplied.
- The probe checks sensitive paths such as `/.env`, `/composer.json`, `/vendor/autoload.php`, `/app`, `/config`, `/docs`, and `/scripts`.
- The probe records only status codes and never prints response bodies or secret values.
- Readiness now fails if a sensitive path returns 2xx and warns on redirects or probe failures.
- Added HTTP probe passthrough to `scripts/project-progress.ps1 -CheckDeploy`.
- Updated `docs/tasks/deployment-runtime-readiness-plan.md`, `docs/tasks/deployment-server-checklist.md`, and `docs/tasks/refactor-progress-dashboard.md`.

### Modified Files

- `scripts/deployment-readiness.ps1`
- `scripts/deployment-readiness.sh`
- `scripts/project-progress.ps1`
- `docs/tasks/deployment-runtime-readiness-plan.md`
- `docs/tasks/deployment-server-checklist.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- PowerShell parser checks for `scripts\deployment-readiness.ps1` and `scripts\project-progress.ps1`: passed.
- Git Bash `bash -n scripts/deployment-readiness.sh`: passed.
- `.\scripts\deployment-readiness.ps1 -ExpectedPublicRoot .\public`: passed with 0 failures and 2 local deployment warnings.
- Git Bash `scripts/deployment-readiness.sh --expected-public-root ./public`: passed with 0 failures and 4 expected Windows-host/local warnings.
- Temporary PHP public-root server HTTP probe with `.\scripts\deployment-readiness.ps1 -ExpectedPublicRoot .\public -PublicBaseUrl <temporary-url> -SkipThinkBoot`: passed with 0 failures; sensitive paths returned `404`.
- Temporary PHP public-root server HTTP probe with Git Bash `scripts/deployment-readiness.sh --expected-public-root ./public --public-base-url <temporary-url> --skip-think-boot`: passed with 0 failures; sensitive paths returned `404`.
- `.\scripts\project-progress.ps1 -CheckDeploy -ExpectedPublicRoot .\public -PublicBaseUrl <temporary-url> -Lean`: passed; passthrough confirmed.
- `git diff --check -- scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with existing LF/CRLF warnings only.
- `rg -n "[ \t]+$" scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with no matches.

### Current Issues

- This guard has not been run against a real staging/production domain yet.
- Redirect targets are intentionally not followed; any 3xx probe result must be manually verified before release.
- Nginx/PHP-FPM vhost edits, live server reloads, backup automation, production data operations, and final online data sync remain deferred until a target host and user approval are confirmed.

### Next Plan

- Continue local deployment/runtime hardening, or run the HTTP public-exposure guard against the real staging URL once the target host and domain are confirmed.

## 2026-06-26 - api-agent/test-agent - PHP Upload Limit Readiness

### Completed

- Added a PHP upload/body limit guard to `scripts/deployment-readiness.ps1` and `scripts/deployment-readiness.sh`.
- The guard reads `file_uploads`, `upload_max_filesize`, `post_max_size`, `max_file_uploads`, and `memory_limit` without exposing secrets.
- Readiness now fails if PHP uploads are disabled or `max_file_uploads` is invalid.
- Readiness now warns when upload/body limits are below configurable thresholds, when `post_max_size` is not larger than `upload_max_filesize`, or when `memory_limit` is lower than `post_max_size`.
- Added upload/body threshold passthrough to `scripts/project-progress.ps1 -CheckDeploy`.
- Updated `docs/tasks/deployment-runtime-readiness-plan.md`, `docs/tasks/deployment-server-checklist.md`, and `docs/tasks/refactor-progress-dashboard.md`.

### Modified Files

- `scripts/deployment-readiness.ps1`
- `scripts/deployment-readiness.sh`
- `scripts/project-progress.ps1`
- `docs/tasks/deployment-runtime-readiness-plan.md`
- `docs/tasks/deployment-server-checklist.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- PowerShell parser checks for `scripts\deployment-readiness.ps1` and `scripts\project-progress.ps1`: passed.
- Git Bash `bash -n scripts/deployment-readiness.sh`: passed.
- `.\scripts\deployment-readiness.ps1 -ExpectedPublicRoot .\public`: passed with 0 failures and 2 local deployment warnings.
- Git Bash `scripts/deployment-readiness.sh --expected-public-root ./public`: passed with 0 failures and 4 expected Windows-host/local warnings.
- `.\scripts\project-progress.ps1 -CheckDeploy -ExpectedPublicRoot .\public -Lean`: passed.
- `.\scripts\project-progress.ps1 -CheckDeploy -ExpectedPublicRoot .\public -MinUploadMaxFilesize 2M -MinPostMaxSize 8M -Lean`: passed; threshold passthrough confirmed.
- `git diff --check -- scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with existing LF/CRLF warnings only.
- `rg -n "[ \t]+$" scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with no matches.

### Current Issues

- Local PHP reports `upload_max_filesize=2M`, below the default readiness recommendation of `8M`.
- Local `.env` still has `APP_DEBUG=true`, acceptable for local smoke only.
- Nginx `client_max_body_size`, PHP-FPM pool/php.ini edits, live server reloads, backup automation, production data operations, and final online data sync remain deferred until a target host and user approval are confirmed.

### Next Plan

- Continue deployment/runtime hardening that can be verified locally, or fill in the real Nginx/PHP-FPM host checklist once the target host and vhost path are confirmed.

## 2026-06-26 - api-agent/test-agent - Git Secret Ignore Readiness

### Completed

- Added a Git secret/artifact guard to `scripts/deployment-readiness.ps1` and `scripts/deployment-readiness.sh`.
- When Git metadata is available, readiness now fails if `.env` is tracked.
- Readiness warns if `.env`, `vendor/autoload.php`, `runtime/test.tmp`, or `public/storage/test.tmp` are not ignored.
- Updated `docs/tasks/deployment-runtime-readiness-plan.md` and `docs/tasks/deployment-server-checklist.md` with the source-control hygiene guard.

### Modified Files

- `scripts/deployment-readiness.ps1`
- `scripts/deployment-readiness.sh`
- `docs/tasks/deployment-runtime-readiness-plan.md`
- `docs/tasks/deployment-server-checklist.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- PowerShell parser checks for `scripts\deployment-readiness.ps1` and `scripts\project-progress.ps1`: passed.
- Git Bash `bash -n scripts/deployment-readiness.sh`: passed.
- `.\scripts\deployment-readiness.ps1 -ExpectedPublicRoot .\public`: passed with 0 failures and 1 local deployment warning.
- Git Bash `scripts/deployment-readiness.sh --expected-public-root ./public`: passed with 0 failures and 3 expected Windows-host warnings.
- `.\scripts\project-progress.ps1 -CheckDeploy -ExpectedPublicRoot .\public -Lean`: passed.
- `git diff --check -- scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with existing LF/CRLF warnings only.
- `rg -n "[ \t]+$" scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with no matches.

### Current Issues

- This guard checks current Git index/ignore behavior only; it does not scrub historical secrets or rotate credentials.
- Real Nginx/PHP-FPM vhost inspection, server reloads, queue/scheduler execution, cloud/provider credentials, backup automation, production data operations, and final online data sync remain deferred until a target host and user approval are confirmed.

### Next Plan

- Continue deployment/runtime hardening that can be verified locally, or move to the next complete feature block only when backed by Java-public behavior, active visible frontend workflow, or explicit product request.

## 2026-06-26 - api-agent/test-agent - Public Web Exposure Readiness

### Completed

- Added a public web-exposure guard to `scripts/deployment-readiness.ps1` and `scripts/deployment-readiness.sh`.
- Readiness now fails if sensitive project source/config/dependency entries such as `.env`, `vendor`, `app`, `config`, `route`, `docs`, or `scripts` are present under `public`.
- Updated `docs/tasks/deployment-runtime-readiness-plan.md`, `docs/tasks/deployment-server-checklist.md`, and `docs/tasks/refactor-progress-dashboard.md` with the guard.

### Modified Files

- `scripts/deployment-readiness.ps1`
- `scripts/deployment-readiness.sh`
- `docs/tasks/deployment-runtime-readiness-plan.md`
- `docs/tasks/deployment-server-checklist.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- PowerShell parser checks for `scripts\deployment-readiness.ps1` and `scripts\project-progress.ps1`: passed.
- Git Bash `bash -n scripts/deployment-readiness.sh`: passed.
- `.\scripts\deployment-readiness.ps1 -ExpectedPublicRoot .\public`: passed with 0 failures and 1 local deployment warning.
- Git Bash `scripts/deployment-readiness.sh --expected-public-root ./public`: passed with 0 failures and 3 expected Windows-host warnings.
- `.\scripts\project-progress.ps1 -CheckDeploy -ExpectedPublicRoot .\public -Lean`: passed.
- `git diff --check -- scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with existing LF/CRLF warnings only.
- `rg -n "[ \t]+$" scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with no matches.

### Current Issues

- This is a local filesystem guard only; live host web-readability still depends on the target vhost and must be inspected during staging rehearsal.
- Real Nginx/PHP-FPM vhost inspection, server reloads, queue/scheduler execution, cloud/provider credentials, backup automation, production data operations, and final online data sync remain deferred until a target host and user approval are confirmed.

### Next Plan

- Continue deployment/runtime hardening that can be verified locally, or move to the next complete feature block only when backed by Java-public behavior, active visible frontend workflow, or explicit product request.

## 2026-06-26 - api-agent/test-agent - PowerShell Public Root Readiness

### Completed

- Added `-ExpectedPublicRoot` to `scripts/deployment-readiness.ps1` so Windows/PowerShell deployment rehearsals can verify a known document-root path resolves to the project `public` directory.
- Added `-ExpectedPublicRoot` passthrough to `scripts/project-progress.ps1 -CheckDeploy`.
- Updated `docs/tasks/deployment-runtime-readiness-plan.md`, `docs/tasks/deployment-server-checklist.md`, and `docs/tasks/refactor-progress-dashboard.md` with the PowerShell public-root verification command.

### Modified Files

- `scripts/deployment-readiness.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/deployment-runtime-readiness-plan.md`
- `docs/tasks/deployment-server-checklist.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- PowerShell parser checks for `scripts\deployment-readiness.ps1` and `scripts\project-progress.ps1`: passed.
- `.\scripts\deployment-readiness.ps1`: passed with 0 failures and 1 local deployment warning.
- `.\scripts\deployment-readiness.ps1 -ExpectedPublicRoot .\public`: passed with 0 failures and 1 local deployment warning.
- `.\scripts\project-progress.ps1 -CheckDeploy -ExpectedPublicRoot .\public -Lean`: passed.
- `git diff --check -- scripts/deployment-readiness.ps1 scripts/project-progress.ps1 docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/refactor-progress-dashboard.md PLANS.md IMPLEMENT.md STATUS.md`: passed with existing LF/CRLF warnings only.

### Current Issues

- Real Nginx/PHP-FPM vhost inspection, server reloads, queue/scheduler execution, cloud/provider credentials, backup automation, production data operations, and final online data sync remain deferred until a target host and user approval are confirmed.

### Next Plan

- Continue deployment/runtime hardening that can be verified locally, or move to the next complete feature block only when backed by Java-public behavior, active visible frontend workflow, or explicit product request.

## 2026-06-25 - api-agent/test-agent - Deployment Runtime Readiness

### Completed

- Added `scripts/deployment-readiness.ps1` as a deployment/runtime preflight that does not edit `.env`, config, database rows, runtime services, or production data.
- The script checks required ThinkPHP files, `.env` presence without printing secret values, Composer autoload, PHP/Composer availability, key PHP extensions, expected environment keys, runtime/storage writable paths, and `php think route:list` boot.
- Added production/staging switches: `-Production`, `-Strict`, `-SkipThinkBoot`, and `-SkipWritableProbe`.
- Added `-CreateMissingWritableDirs` so missing writable directory shells can be created explicitly before rechecking.
- Added `docs/tasks/deployment-runtime-readiness-plan.md`.
- Added `docs/tasks/deployment-server-checklist.md` for later Nginx/PHP-FPM host rehearsal.
- Added `scripts/deployment-readiness.sh` as a Linux staging/production counterpart to the PowerShell readiness script.
- Wired the check into `scripts/project-progress.ps1 -CheckDeploy` and the fast-command list.

### Modified Files

- `scripts/deployment-readiness.ps1`
- `scripts/deployment-readiness.sh`
- `scripts/project-progress.ps1`
- `public/storage/.gitignore`
- `docs/tasks/deployment-runtime-readiness-plan.md`
- `docs/tasks/deployment-server-checklist.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/problem-optimization-log.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- PowerShell parser checks for `scripts\deployment-readiness.ps1` and `scripts\project-progress.ps1`: passed.
- Git Bash `bash -n scripts/deployment-readiness.sh`: passed.
- `.\scripts\deployment-readiness.ps1 -CreateMissingWritableDirs`: passed with 0 failures and 1 local deployment warning.
- `.\scripts\deployment-readiness.ps1`: passed with 0 failures and 1 local deployment warning.
- Git Bash `scripts/deployment-readiness.sh --skip-think-boot --skip-writable-probe`: passed with 0 failures and 3 expected Windows-host warnings.
- Git Bash `scripts/deployment-readiness.sh`: passed with 0 failures and 3 expected Windows-host warnings.
- `.\scripts\project-progress.ps1 -CheckDeploy -Lean`: passed.
- `git diff --check -- scripts/deployment-readiness.ps1 scripts/deployment-readiness.sh scripts/project-progress.ps1 public/storage/.gitignore docs/tasks/deployment-runtime-readiness-plan.md docs/tasks/deployment-server-checklist.md docs/tasks/api-gap-map.md docs/tasks/refactor-progress-dashboard.md docs/tasks/problem-optimization-log.md STATUS.md IMPLEMENT.md PLANS.md`: passed with existing LF/CRLF warnings only.

### Current Issues

- Nginx/PHP-FPM vhost configuration, queue/scheduler execution, cloud/provider credential validation, production backup automation, production data operations, and final online data sync remain deferred.
- Current local deployment warning is expected before staging hardening: `APP_DEBUG=true` must stay a production/staging configuration decision.
- Git Bash on this Windows workstation also warns that `nginx` and `php-fpm` are not installed locally; on staging/production those should pass or be explicitly documented as managed outside the host.

### Next Plan

- Continue only with Java-public/active-frontend feature blocks or the next deployment rehearsal checklist. Keep `.env` production values, real server vhost edits, backups, and production data sync blocked until the target host and user approval are confirmed.

## 2026-06-25 - api-agent/test-agent - Remaining Deferred Route Parity Audit

### Completed

- Audited the next candidate deferred routes after return-order reverse stock/finance correction.
- Confirmed no-account return auto-refund is not present in the Java reference flow; positive-amount returns remain unsettled until a `ReturnAndRefund` expenditure is created.
- Confirmed payment/expenditure add-delete, collection-receipt CRUD, debit-note CRUD, purchase-order add-delete, inventory delete, task SSE, gen-config add, and non-`FOLLOW` sale-project product-item mutation are not safe Java-public feature targets without product approval. Superseding note: the user explicitly opened finance/purchase/inventory direct CRUD on 2026-06-26, and payment/expenditure add-delete, collection/debit direct CRUD, purchase-order direct add-delete, and inventory zero-count delete are now implemented as bounded product behavior; task SSE, gen-config add, and non-`FOLLOW` product-item mutation still need separate approval/planning.
- Added `docs/tasks/remaining-deferred-route-parity-audit.md` and updated next-plan language to avoid repeatedly selecting false candidates.

### Modified Files

- `docs/tasks/remaining-deferred-route-parity-audit.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- Documentation-only change; no runtime test required.

### Current Issues

- Several controlled-deferred wrappers remain intentionally routed only to protect copied frontend branches from 404s.
- Real provider sends, generator direct project writes, task SSE, broad notifications, Java data-change events, production deployment, and final data sync remain out of scope.

### Next Plan

- Pick the next block from Java-public or actively visible frontend behavior, or move to deployment/runtime hardening. Do not open copied frontend wrapper branches unless Java exposes the route or the user explicitly requests a new product behavior.

## 2026-06-25 - api-agent/test-agent - Return Order Reverse Stock/Finance Correction

### Completed

- Direct `POST /biz/returnorder/add` now rejects submitted `PROCESS_ID` values that already exist in `act_hi_procinst`.
- Direct `POST /biz/returnorder/edit` now protects workflow-owned rows, validates the new payload first, reverses active `ReturnAndRefund` expenditure/statement/account effects, reverses active return IN delivery/inventory effects, updates the order/items, rebuilds return IN delivery/inventory rows, and recalculates affected sale-project totals in one transaction.
- Direct `POST /biz/returnorder/delete` now protects workflow-owned rows, validates the full batch first, reverses active refund finance and return IN inventory effects, logically deletes orders/items, and recalculates sale-project totals.
- Extended `scripts/return-order-write-http-smoke.ps1` to verify invalid-edit rollback, settled-return edit with refund/account and stock reverse correction, edited return re-refund, delete reverse correction, account restoration, inventory restoration, soft-deleted side-effect rows, and project-total restoration.
- Added `docs/tasks/return-order-reverse-stock-finance-plan.md`.

### Modified Files

- `app/service/biz/ReturnOrderService.php`
- `scripts/return-order-write-http-smoke.ps1`
- `docs/tasks/return-order-reverse-stock-finance-plan.md`
- `docs/tasks/return-order-inventory-refund-plan.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/api-gap-map.md`
- `docs/api/biz-return-order-readonly-compat.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\service\biz\ReturnOrderService.php`: passed.
- PowerShell parser check for `scripts\return-order-write-http-smoke.ps1`: passed.
- `.\scripts\return-order-write-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82`: passed.

### Current Issues

- No-account return auto-refund was reviewed after this slice and is not a Java-parity target unless the user requests it as new product behavior.
- Notifications, Java data-change events, file cleanup, Java source changes, database schema changes, `.env`, production data operations, and Git push remain out of scope.

### Next Plan

- Continue with a Java-public or actively visible frontend behavior block, or move to deployment/runtime hardening.

## 2026-06-25 - api-agent/test-agent - Sale Project Reissue Order Direct Edit/Delete

### Completed

- Added protected `POST /biz/saleprojectreissueorder/edit` and `/delete`.
- Direct add now also rejects submitted `PROCESS_ID` values that already exist in `act_hi_procinst`, preventing direct writes from occupying workflow-owned process ids before approval.
- Direct edit now validates active reissue-order access, project ownership, tenant/write scope, duplicate active `PROCESS_ID`, workflow-owned process guards, and mutable linked reissue product items before replacing order master fields and `productList`.
- Direct delete validates the full selected batch first, rejects workflow-owned rows, rejects delivered/referenced/non-reissue product items, logically deletes the reissue order, linked product items, and child relations, then recalculates sale-project totals/payment/project state in one transaction.
- Extended `scripts/sale-project-reissue-order-add-http-smoke.ps1` to cover no-token, validation, workflow-process add guard, add success, duplicate add guard, edit success, mixed-delete rollback, delete success, list readback before/after delete, soft-delete flags, project-total restoration, and unchanged delivery/inventory/invoice/finance/workflow counts.
- Added `docs/tasks/sale-project-reissue-order-edit-delete-plan.md`.

### Modified Files

- `route/app.php`
- `app/controller/biz/SaleProjectReissueOrderController.php`
- `app/service/biz/SaleProjectService.php`
- `scripts/sale-project-reissue-order-add-http-smoke.ps1`
- `scripts/project-preflight.ps1`
- `docs/tasks/sale-project-reissue-order-edit-delete-plan.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/api-gap-map.md`
- `docs/api/biz-saleproject-billing-readonly.md`
- `docs/api/biz-saleproject-readonly.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/test-agent-smoke-runbook.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\biz\SaleProjectReissueOrderController.php`: passed.
- `php -l app\service\biz\SaleProjectService.php`: passed.
- `php -l route\app.php`: passed.
- PowerShell parser checks for `scripts\sale-project-reissue-order-add-http-smoke.ps1`, `scripts\project-preflight.ps1`, and `scripts\project-progress.ps1`: passed.
- `php think route:list | Select-String -Pattern "saleprojectreissueorder/(add|edit|delete)"`: passed.
- `php think route:list` concrete route count: 587.
- `.\scripts\sale-project-reissue-order-add-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82`: passed.
- `.\scripts\workflow-project-reissue-approve-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82`: passed.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 560/560 frontend endpoints covered and 587 route paths.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed.
- `.\scripts\project-progress.ps1 -Lean`: passed.
- `git diff --check`: passed with LF/CRLF warnings only.

### Current Issues

- Delivery invoice rows, invoice item rows, inventory rows, delivery records, settlement statements, payment records, expenditure records, workflow runtime mutation, notifications, file cleanup, Java data-change events, Java source changes, database schema changes, `.env`, production data operations, and Git push remain out of scope.

### Next Plan

- Continue with no-account return auto-refund or the next finance write group.

## 2026-06-25 - api-agent/test-agent - Sale Project Invoice Direct Edit/Delete

### Completed

- Added protected `POST /biz/saleprojectinvoice/edit` and `/delete`.
- Direct edit now validates active invoice access, project ownership, tenant/write scope, duplicate active `PROCESS_ID`, logistics fields, and delivery-record-backed process guards before updating only invoice master/logistics fields.
- Direct delete validates the full selected invoice batch first, rejects workflow-owned invoices with active `delivery_record` rows, logically deletes invoice and invoice-item rows, reverses linked project-product `DELIVERY`, corrects product-item `STATE`, and recalculates sale-project shipment state in one transaction.
- Extended `scripts/sale-project-invoice-add-http-smoke.ps1` to cover no-token, validation, edit success, mixed-delete rollback, delete success, readback before/after delete, and unchanged delivery-record/inventory/finance/workflow counts.
- Added `docs/tasks/sale-project-invoice-edit-delete-plan.md`.

### Modified Files

- `route/app.php`
- `app/controller/biz/SaleProjectInvoiceController.php`
- `app/service/biz/SaleProjectService.php`
- `scripts/sale-project-invoice-add-http-smoke.ps1`
- `scripts/project-preflight.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/sale-project-invoice-edit-delete-plan.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/api-gap-map.md`
- `docs/api/biz-saleproject-billing-readonly.md`
- `docs/api/biz-saleproject-readonly.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/test-agent-smoke-runbook.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\biz\SaleProjectInvoiceController.php`: passed.
- `php -l app\service\biz\SaleProjectService.php`: passed.
- `php -l route\app.php`: passed.
- PowerShell parser checks for `scripts\sale-project-invoice-add-http-smoke.ps1`, `scripts\project-preflight.ps1`, and `scripts\project-progress.ps1`: passed.
- `php think route:list | Select-String -Pattern "saleprojectinvoice/(add|edit|delete)"`: passed.
- `php think route:list` concrete route count: 585.
- `.\scripts\sale-project-invoice-add-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82`: passed.
- `.\scripts\workflow-project-delivery-approve-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82`: passed.
- `.\scripts\sale-project-reissue-order-add-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82`: passed.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 560/560 frontend endpoints covered and 585 route paths.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed.
- `.\scripts\project-progress.ps1 -Lean`: passed.
- `git diff --check`: passed with LF/CRLF warnings only.

### Current Issues

- Standalone invoice-item writes, invoice-item replacement through invoice edit, `delivery_record` deletion, inventory restoration, finance/settlement/payment/expenditure mutation, workflow runtime mutation, notifications, file cleanup, Java data-change events, Java source changes, database schema changes, `.env`, production data operations, and Git push remain out of scope.

### Next Plan

- Continue with no-account return auto-refund or the next finance write group.

## 2026-06-25 - api-agent/test-agent - Sale Project Invoice Direct Add

### Completed

- Added protected `POST /biz/saleprojectinvoice/add`.
- Direct delivery-invoice add validates active sale-project access, tenant/write scope, non-`FOLLOW` state, unique active `PROCESS_ID`, logistics fields, freight time, operator, and non-empty `projectProductItemList`.
- The successful write creates one `biz_sale_project_invoice` row and linked `biz_sale_project_invoice_item` rows.
- The invoice-item add path increments linked sale-project product-item `DELIVERY`, updates product-item delivery `STATE`, and corrects the owning sale project's shipment state in the same transaction.
- Added `scripts/sale-project-invoice-add-http-smoke.ps1`, added it to `scripts/project-preflight.ps1`, and surfaced it in `scripts/project-progress.ps1`.
- Added `docs/tasks/sale-project-invoice-add-plan.md`.

### Modified Files

- `route/app.php`
- `app/controller/biz/SaleProjectInvoiceController.php`
- `app/service/biz/SaleProjectService.php`
- `scripts/sale-project-invoice-add-http-smoke.ps1`
- `scripts/project-preflight.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/sale-project-invoice-add-plan.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/public-file-change-request.md`
- `docs/api/biz-saleproject-billing-readonly.md`
- `docs/api/biz-saleproject-readonly.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/test-agent-smoke-runbook.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\biz\SaleProjectInvoiceController.php`: passed.
- `php -l app\service\biz\SaleProjectService.php`: passed.
- `php -l route\app.php`: passed.
- PowerShell parser checks for `scripts\sale-project-invoice-add-http-smoke.ps1`, `scripts\project-preflight.ps1`, and `scripts\project-progress.ps1`: passed.
- `php think route:list | Select-String "saleprojectinvoice/add"`: passed.
- `php think route:list` concrete route count: 583.
- `.\scripts\sale-project-invoice-add-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82`: passed.
- `.\scripts\workflow-project-delivery-approve-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82`: passed.
- `.\scripts\sale-project-reissue-order-add-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82`: passed.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 560/560 frontend endpoints covered and 583 route paths.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed.
- `.\scripts\project-progress.ps1 -Lean`: passed.
- `git diff --check`: passed with LF/CRLF warnings only.

### Current Issues

- Direct invoice edit/delete and reverse delivery correction remain deferred.
- `delivery_record`, inventory, settlement, payment, expenditure, workflow runtime, notifications, file cleanup, Java data-change events, Java source changes, database schema changes, `.env`, production data operations, and Git push remain out of scope.

### Next Plan

- Continue with no-account return auto-refund or the next finance write group.

## 2026-06-25 - api-agent/test-agent - Sale Project Reissue Order Direct Add

### Completed

- Added protected `POST /biz/saleprojectreissueorder/add`.
- Direct reissue add now validates active sale-project access, tenant/write scope, non-`FOLLOW` state, unique active `PROCESS_ID`, submitted amount, and non-empty `productList`.
- The successful write creates one `biz_sale_project_reissue_order`, linked `REISSUE_ORDER`/`WAIT_DELIVER` project-product rows, and child `sale_project_product_item_relation` rows.
- Sale-project totals and `PROJECT_STATE`/`PLAY_STATE` are corrected in the same transaction after the reissue product rows exist.
- Added `scripts/sale-project-reissue-order-add-http-smoke.ps1`, added it to `scripts/project-preflight.ps1`, and surfaced it in `scripts/project-progress.ps1`.
- Added `docs/tasks/sale-project-reissue-order-add-plan.md`.

### Modified Files

- `route/app.php`
- `app/controller/biz/SaleProjectReissueOrderController.php`
- `app/service/biz/SaleProjectService.php`
- `scripts/sale-project-reissue-order-add-http-smoke.ps1`
- `scripts/project-preflight.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/sale-project-reissue-order-add-plan.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/public-file-change-request.md`
- `docs/api/biz-saleproject-billing-readonly.md`
- `docs/api/biz-saleproject-readonly.md`
- `docs/tasks/test-agent-smoke-runbook.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\biz\SaleProjectReissueOrderController.php`: passed.
- `php -l app\service\biz\SaleProjectService.php`: passed.
- PowerShell parser checks for `scripts\sale-project-reissue-order-add-http-smoke.ps1`, `scripts\project-preflight.ps1`, and `scripts\project-progress.ps1`: passed.
- `php think route:list | Select-String "saleprojectreissueorder/add"`: passed.
- `php think route:list` concrete route count: 582.
- `.\scripts\sale-project-reissue-order-add-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82`: passed.
- `.\scripts\workflow-project-reissue-approve-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82`: passed.
- `.\scripts\sale-project-product-item-standalone-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82`: passed.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed.
- `.\scripts\project-progress.ps1 -Lean`: passed.
- `git diff --check`: passed with CRLF warnings only.

### Current Issues

- Direct reissue edit/delete remains deferred.
- Delivery invoice rows, invoice item rows, inventory rows, delivery records, settlement statements, payment records, expenditure records, workflow runtime rows, notifications, file cleanup, Java data-change events, Java source changes, database schema changes, `.env`, production data operations, and Git push remain out of scope.

### Next Plan

- Continue with no-account return auto-refund or the next finance write group.

## 2026-06-25 - api-agent/test-agent - Workflow Project Return Auto Refund

### Completed

- `Process_sale_project_product_return` approval now creates an automatic `ReturnAndRefund` expenditure and settlement-account statement when the approved sale project has an active `ACCOUNT_ID`.
- The auto-refund path reuses `SettlementAccountService::expensesFromWorkflow()`, so account balance decrement, return-order settlement state, and sale-project return totals follow the same correction path as quick expenses.
- Approval remains idempotent by `return_order.PROCESS_ID`; existing return-order summaries do not create duplicate refund rows.
- Added `docs/tasks/workflow-project-return-auto-refund-plan.md`.
- Extended `scripts/workflow-project-return-approve-http-smoke.ps1` to verify account decrement, expenditure/statement rows, `AlreadySettled` state, and `TOTAL_RETURN_AMOUNT`.

### Modified Files

- `app/service/biz/ReturnOrderService.php`
- `scripts/workflow-project-return-approve-http-smoke.ps1`
- `docs/tasks/workflow-project-return-auto-refund-plan.md`
- `docs/tasks/workflow-project-return-approve-plan.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/api-gap-map.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/test-agent-smoke-runbook.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\service\biz\ReturnOrderService.php`: passed.
- PowerShell parser check for `scripts\workflow-project-return-approve-http-smoke.ps1`: passed.
- `.\scripts\workflow-project-return-approve-http-smoke.ps1`: passed.
- `.\scripts\return-order-write-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82`: passed.
- `.\scripts\settlement-account-expenses-add-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82`: passed when run serially.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed.
- `.\scripts\project-progress.ps1 -Lean`: passed.
- `git diff --check`: passed with CRLF warnings only.

### Current Issues

- Automatic refund creation when the sale project has no configured settlement account remains deferred.
- Direct edit/delete reverse stock/finance correction is covered by the later Return Order Reverse Stock/Finance Correction entry.
- Notifications, Java data-change events, file cleanup, Java source changes, database schema changes, `.env`, production data operations, and Git push remain out of scope.

### Next Plan

- Continue with the next complete feature-closure block: no-account return auto-refund or the next finance write group.

## 2026-06-25 - api-agent/test-agent - Return Order Inventory And Refund Settlement

### Completed

- Direct `POST /biz/returnorder/add` now creates Java-compatible return IN `delivery_record` rows and increments inventory in the same transaction.
- `Process_sale_project_product_return` approval now increments inventory for its Java-compatible IN delivery rows. Automatic refund creation for projects with configured settlement accounts is covered by the later Workflow Project Return Auto Refund entry above.
- Direct return-order edit/delete initially blocked once delivery records or `ReturnAndRefund` expenditure rows existed; the later Return Order Reverse Stock/Finance Correction entry replaces those blockers with transactional correction.
- `POST /biz/settlementaccount/expenses/add` now applies Java-compatible `ReturnAndRefund` correction by updating return-order `STATE`, recalculating sale-project return totals, and rolling back over-refunds.
- Added `docs/tasks/return-order-inventory-refund-plan.md`.
- Updated return-order and workflow-return smoke expectations.

### Modified Files

- `app/service/biz/ReturnOrderService.php`
- `app/service/biz/SettlementAccountService.php`
- `scripts/return-order-write-http-smoke.ps1`
- `scripts/workflow-project-return-approve-http-smoke.ps1`
- `scripts/project-preflight.ps1`
- `docs/tasks/return-order-inventory-refund-plan.md`
- `docs/tasks/workflow-project-return-approve-plan.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/test-agent-smoke-runbook.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\service\biz\ReturnOrderService.php`: passed.
- `php -l app\service\biz\SettlementAccountService.php`: passed.
- PowerShell parser checks for `scripts\return-order-write-http-smoke.ps1`, `scripts\workflow-project-return-approve-http-smoke.ps1`, and `scripts\project-preflight.ps1`: passed.
- `.\scripts\return-order-write-http-smoke.ps1`: passed.
- `.\scripts\workflow-project-return-approve-http-smoke.ps1`: passed.
- `.\scripts\settlement-account-expenses-add-http-smoke.ps1`: passed.

### Current Issues

- Automatic refund creation when the sale project has no configured settlement account remains deferred.
- Direct edit/delete reverse stock/finance correction is covered by the later Return Order Reverse Stock/Finance Correction entry.
- Notifications, Java data-change events, file cleanup, Java source changes, database schema changes, `.env`, production data operations, and Git push remain out of scope.

### Next Plan

- Continue with the next complete feature-closure block from the progress dashboard/API gap map.

## 2026-06-25 - api-agent/test-agent - Sale Project Product Item Standalone

### Completed

- Added protected `POST /biz/saleprojectproductitem/add`, `/edit`, and `/delete` routes.
- Standalone add/edit/delete now validate the owning sale project through tenant and data scope and are limited to `FOLLOW` projects.
- Add writes one normal `INIT`/`WAIT_DELIVER` product item and child relation rows in one transaction.
- Edit supports partial fields and preserves child relations when `children` is omitted and the product is unchanged.
- Delete logically deletes product-item rows and child relations.
- Active invoice/return references and delivered quantities block protected edits/deletes with rollback.
- Added `scripts/sale-project-product-item-standalone-http-smoke.ps1`, added it to `scripts/project-preflight.ps1`, and surfaced it in `scripts/project-progress.ps1`.
- Added `docs/tasks/sale-project-product-item-standalone-plan.md`.

### Modified Files

- `route/app.php`
- `app/controller/biz/SaleProjectProductItemController.php`
- `app/service/biz/SaleProjectProductItemService.php`
- `app/service/biz/SaleProjectService.php`
- `scripts/sale-project-product-item-standalone-http-smoke.ps1`
- `scripts/project-preflight.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/sale-project-product-item-standalone-plan.md`
- `docs/api/biz-saleproject-product-item-relation-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/test-agent-smoke-runbook.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\service\biz\SaleProjectService.php`: passed.
- `php -l app\service\biz\SaleProjectProductItemService.php`: passed.
- `php -l app\controller\biz\SaleProjectProductItemController.php`: passed.
- `php -l route\app.php`: passed.
- PowerShell parser check for `scripts\sale-project-product-item-standalone-http-smoke.ps1`: passed.
- `.\scripts\sale-project-product-item-standalone-http-smoke.ps1`: passed.
- `php think route:list | Select-String "biz/saleprojectproductitem/(add|edit|delete|mark/edit)"`: passed.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 560/560 frontend endpoints covered and 581 route paths.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed.
- `.\scripts\project-progress.ps1 -Lean`: passed.
- `git diff --check`: passed with existing LF/CRLF warnings only.

### Current Issues

- Non-`FOLLOW` product-item mutation remains deferred.
- Delivery, invoice, return, inventory, finance, workflow, notification, project-state recalculation, Java event-bus side effects, Java source changes, database schema changes, `.env`, production data operations, and Git push remain out of scope.

### Next Plan

- Continue with the next complete feature-closure block: no-account return auto-refund or the next finance write group.

## 2026-06-25 - api-agent/test-agent - Workflow Project Return Approval

### Completed

- Replaced `POST /biz/process/project/return/start` controlled-deferred behavior with a bounded `Process_sale_project_product_return` PHP runtime start.
- Start now validates return project payloads, approver/copy lists, warehouse id, amount, shipped project product item ids, product ids, and return quantity limits.
- Cancel and reject now close active project-return workflow rows without return-order, delivery-record, inventory, finance, invoice, or project-status side effects.
- Approval now applies bounded Java-compatible project return side effects through `ReturnOrderService::applyProjectReturnFromWorkflow()`.
- Workflow-owned project return writes one `return_order` row with `PROCESS_ID = processInstanceId`.
- Workflow-owned project return writes linked `return_order_item` rows and IN `delivery_record` rows with `PROCESS_CATEGORY = Process_sale_project_product_return` and `OBJECT_ID = returnOrderId`; child product relations decompose returned kit items.
- Added `scripts/workflow-project-return-approve-http-smoke.ps1`, added it to `scripts/project-preflight.ps1` and `scripts/project-progress.ps1`, removed `/biz/process/project/return/start` from `scripts/frontend-deferred-write-wrapper-smoke.ps1`, and added `docs/tasks/workflow-project-return-approve-plan.md`.

### Modified Files

- `app/controller/biz/ProcessController.php`
- `app/service/workflow/WorkflowRuntimeService.php`
- `app/service/biz/ReturnOrderService.php`
- `scripts/workflow-project-return-approve-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-preflight.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/workflow-project-return-approve-plan.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/workflow-agent-java-map.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Verification

- `php -l app\service\workflow\WorkflowRuntimeService.php`: passed.
- `php -l app\service\biz\ReturnOrderService.php`: passed.
- `php -l app\controller\biz\ProcessController.php`: passed.
- PowerShell parser checks for `scripts\workflow-project-return-approve-http-smoke.ps1`, `scripts\project-preflight.ps1`, `scripts\frontend-deferred-write-wrapper-smoke.ps1`, and `scripts\project-progress.ps1`: passed.
- `.\scripts\workflow-project-return-approve-http-smoke.ps1`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed.
- `.\scripts\frontend-api-route-gap-smoke.ps1`: passed.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.
- `php think route:list | Select-String "biz/process/project/(play|init|delivery|reissue|return)/start"`: passed.
- `git diff --check`: passed with LF/CRLF warnings only.

### Deferred

- Automatic refund creation when the sale project has no configured settlement account, direct standalone reissue writes, non-leave edit behavior, task SSE, notifications, Java data-change events, Java source changes, schema changes, `.env` changes, production data operations, and commits remain deferred.

## 2026-06-25 - api-agent/test-agent - Workflow Project Reissue Approval

### Completed

- Replaced `POST /biz/process/project/reissue/start` controlled-deferred behavior with a bounded `Process_project_reissue_product` PHP runtime start.
- Start now validates reissue project payloads, approver/copy lists, amount, product ids, quantities, money fields, and child product relations.
- Cancel and reject now close active project-reissue workflow rows without reissue-order, product-item, stock, finance, invoice, or sale-project amount/status side effects.
- Approval now applies bounded Java-compatible project reissue side effects through `SaleProjectService::applyProjectReissueFromWorkflow()`.
- Workflow-owned project reissue writes one `biz_sale_project_reissue_order` row with `PROCESS_ID = processInstanceId`.
- Workflow-owned project reissue appends `biz_sale_project_product_item` rows with `CATEGORY = REISSUE_ORDER`, `STATE = WAIT_DELIVER`, `PROJECT_REISSUE_ORDER_ID`, and submitted child `sale_project_product_item_relation` rows.
- Added `scripts/workflow-project-reissue-approve-http-smoke.ps1`, added it to `scripts/project-preflight.ps1` and `scripts/project-progress.ps1`, removed `/biz/process/project/reissue/start` from `scripts/frontend-deferred-write-wrapper-smoke.ps1`, and added `docs/tasks/workflow-project-reissue-approve-plan.md`.

### Modified Files

- `app/controller/biz/ProcessController.php`
- `app/service/workflow/WorkflowRuntimeService.php`
- `app/service/biz/SaleProjectService.php`
- `scripts/workflow-project-reissue-approve-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-preflight.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/workflow-project-reissue-approve-plan.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/workflow-agent-java-map.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Verification

- `php -l app\service\workflow\WorkflowRuntimeService.php`: passed.
- `php -l app\service\biz\SaleProjectService.php`: passed.
- `php -l app\controller\biz\ProcessController.php`: passed.
- PowerShell parser checks for `scripts\workflow-project-reissue-approve-http-smoke.ps1`, `scripts\project-preflight.ps1`, `scripts\frontend-deferred-write-wrapper-smoke.ps1`, and `scripts\project-progress.ps1`: passed.
- `.\scripts\workflow-project-reissue-approve-http-smoke.ps1`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed.
- `.\scripts\frontend-api-route-gap-smoke.ps1`: passed.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.

### Deferred

- Direct standalone reissue writes, non-leave edit behavior, task SSE, notifications, Java data-change events, Java source changes, schema changes, `.env` changes, production data operations, and commits remain deferred. Project return is covered by the Workflow Project Return Approval entry above.

## 2026-06-22 - api-agent/test-agent - Workflow Project Delivery Approval

### Completed

- Replaced `POST /biz/process/project/delivery/start` controlled-deferred behavior with a bounded `Process_sale_project_delivery` PHP runtime start.
- Start now validates delivery payloads, warehouse ids, project product item ids, product ids, and requested quantity against remaining undelivered quantity.
- Cancel and reject now close active project-delivery workflow rows without invoice, delivery-record, inventory, or project status side effects.
- Approval now applies bounded Java-compatible project delivery side effects through `SaleProjectService::applyProjectDeliveryFromWorkflow()`.
- Workflow-owned project delivery writes `biz_sale_project_invoice`, `biz_sale_project_invoice_item`, and `delivery_record` rows with `PROCESS_ID = processInstanceId`, `PROCESS_CATEGORY = Process_sale_project_delivery`, and `CATEGORY = OUT`.
- Workflow-owned project delivery increments sale-project product-item `DELIVERY`, moves partially delivered rows to `PART_WAIT_DELIVER`, decrements inventory, and recalculates sale-project `PROJECT_STATE`.
- Added `scripts/workflow-project-delivery-approve-http-smoke.ps1`, added it to `scripts/project-preflight.ps1` and `scripts/project-progress.ps1`, removed `/biz/process/project/delivery/start` from `scripts/frontend-deferred-write-wrapper-smoke.ps1`, and added `docs/tasks/workflow-project-delivery-approve-plan.md`.

### Modified Files

- `app/controller/biz/ProcessController.php`
- `app/service/workflow/WorkflowRuntimeService.php`
- `app/service/biz/SaleProjectService.php`
- `scripts/workflow-project-delivery-approve-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-preflight.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/workflow-project-delivery-approve-plan.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/workflow-agent-java-map.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Verification

- `php -l app\service\workflow\WorkflowRuntimeService.php`: passed.
- `php -l app\service\biz\SaleProjectService.php`: passed.
- `php -l app\controller\biz\ProcessController.php`: passed.
- PowerShell parser checks for `scripts\workflow-project-delivery-approve-http-smoke.ps1`, `scripts\project-preflight.ps1`, `scripts\frontend-deferred-write-wrapper-smoke.ps1`, and `scripts\project-progress.ps1`: passed.
- `.\scripts\workflow-project-delivery-approve-http-smoke.ps1`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed.
- `php think route:list | Select-String "biz/process/project/(play|init|delivery|reissue|return)/start"`: passed.

### Deferred

- Project reissue and return workflows, non-leave edit behavior, task SSE, notifications, Java data-change events, Java source changes, schema changes, `.env` changes, production data operations, and commits remain deferred.

## 2026-06-22 - api-agent/test-agent - Workflow Project Play Approval

### Completed

- Replaced `POST /biz/process/project/play/start` controlled-deferred behavior with a bounded `Process_sale_project_play` PHP runtime start.
- Start now validates project collection payloads, creates first-step runtime/history rows, and binds workflow CC/file rows.
- Cancel now supports active unapproved project-play workflows without payment, account, or sale-project status side effects.
- Reject now closes first-step or finance project-play workflow history without applying collection side effects.
- First approval now advances to BPMN-compatible `Activity_payment_approval` instead of closing the process.
- Finance approval now applies bounded project collection side effects through `SettlementAccountService::paymentFromWorkflow()` and `SaleProjectService::refreshProjectPaymentStatusFromWorkflow()`.
- Workflow-owned project play writes `settlement_account_statement` and `biz_payment_record` rows with `PROCESS_ID = processInstanceId`, `PROCESS_CATEGORY = Process_sale_project_play`, and `SETTLEMENT_CATEGORY = PROJECT_PLAY`.
- Workflow-owned project play increments the selected settlement account and recalculates sale-project `AMOUNT_COLLECTED`, `PLAY_STATE`, and `PROJECT_STATE`.
- Added `scripts/workflow-project-play-approve-http-smoke.ps1`, added it to `scripts/project-preflight.ps1` and `scripts/project-progress.ps1`, removed `/biz/process/project/play/start` from `scripts/frontend-deferred-write-wrapper-smoke.ps1`, and added `docs/tasks/workflow-project-play-approve-plan.md`.

### Modified Files

- `app/controller/biz/ProcessController.php`
- `app/service/workflow/WorkflowRuntimeService.php`
- `app/service/biz/SaleProjectService.php`
- `app/service/biz/SettlementAccountService.php`
- `scripts/workflow-project-play-approve-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-preflight.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/workflow-project-play-approve-plan.md`
- `docs/tasks/workflow-general-start-runtime-plan.md`
- `docs/tasks/workflow-project-init-approve-plan.md`
- `docs/api/biz-workflow-readonly-compat.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/workflow-agent-java-map.md`
- `docs/tasks/workflow-side-effect-map.md`
- `docs/tasks/workflow-payment-approve-plan.md`
- `docs/tasks/workflow-procure-warehouse-approve-plan.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Verification

- `php -l app\service\workflow\WorkflowRuntimeService.php`: passed.
- `php -l app\service\biz\SaleProjectService.php`: passed.
- `php -l app\service\biz\SettlementAccountService.php`: passed.
- `php -l app\controller\biz\ProcessController.php`: passed.
- PowerShell parser checks for `scripts\workflow-project-play-approve-http-smoke.ps1`, `scripts\project-preflight.ps1`, `scripts\frontend-deferred-write-wrapper-smoke.ps1`, and `scripts\project-progress.ps1`: passed.
- `.\scripts\workflow-project-play-approve-http-smoke.ps1`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed.
- `.\scripts\workflow-project-init-approve-http-smoke.ps1`: passed.
- `.\scripts\workflow-payment-approve-http-smoke.ps1`: passed.
- `.\scripts\workflow-payment-out-approve-http-smoke.ps1`: passed.

### Deferred

- Project delivery, reissue, and return workflows, non-leave edit behavior, task SSE, notifications, Java data-change events, Java source changes, schema changes, `.env` changes, production data operations, and commits remain deferred.

## 2026-06-22 - api-agent/test-agent - Workflow Project Init Approval

### Completed

- Replaced `POST /biz/process/project/init/start` controlled-deferred behavior with a bounded `Process_sale_project_init` PHP runtime start.
- Start now validates project-init payloads, marks a visible `FOLLOW` sale project as `PENDING_APPROVAL`, creates first-step runtime/history rows, and binds workflow CC/file rows.
- Cancel now supports active unapproved project-init workflows and rolls the sale project back to `FOLLOW`.
- Reject now closes `Process_sale_project_init` workflow history without applying project-init side effects and rolls the sale project back to `FOLLOW`.
- Approve now applies bounded initial project side effects through `SaleProjectService::applyProjectInitFromWorkflow()`.
- Workflow-owned project init writes sale-project delivery/account/amount fields, product items, `SALE_PROJECT` file relations, optional invoicing rows, customer deal amount, and `PROCESS_ID = processInstanceId`.
- Added `scripts/workflow-project-init-approve-http-smoke.ps1`, added it to `scripts/project-preflight.ps1` and `scripts/project-progress.ps1`, removed `/biz/process/project/init/start` from `scripts/frontend-deferred-write-wrapper-smoke.ps1`, and added `docs/tasks/workflow-project-init-approve-plan.md`.

### Modified Files

- `app/controller/biz/ProcessController.php`
- `app/service/workflow/WorkflowRuntimeService.php`
- `app/service/biz/SaleProjectService.php`
- `scripts/workflow-project-init-approve-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-preflight.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/workflow-project-init-approve-plan.md`
- `docs/tasks/workflow-general-start-runtime-plan.md`
- `docs/api/biz-workflow-readonly-compat.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/workflow-agent-java-map.md`
- `docs/tasks/workflow-side-effect-map.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Verification

- `php -l app\service\workflow\WorkflowRuntimeService.php`: passed.
- `php -l app\service\biz\SaleProjectService.php`: passed.
- `php -l app\controller\biz\ProcessController.php`: passed.
- PowerShell parser checks for `scripts\workflow-project-init-approve-http-smoke.ps1`, `scripts\project-preflight.ps1`, `scripts\frontend-deferred-write-wrapper-smoke.ps1`, and `scripts\project-progress.ps1`: passed.
- `.\scripts\workflow-project-init-approve-http-smoke.ps1`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed.
- `.\scripts\workflow-general-start-http-smoke.ps1`: passed.
- `.\scripts\workflow-task-transition-http-smoke.ps1`: passed.
- `.\scripts\workflow-process-cancel-edit-http-smoke.ps1`: passed.
- `.\scripts\workflow-payment-approve-http-smoke.ps1`: passed.
- `.\scripts\workflow-payment-out-approve-http-smoke.ps1`: passed.
- `.\scripts\workflow-procure-approve-http-smoke.ps1`: passed.
- `.\scripts\workflow-procure-warehouse-approve-http-smoke.ps1`: passed.

### Deferred

- Project delivery, reissue, and return workflows, non-leave edit behavior, task SSE, notifications, Java data-change events, Java source changes, schema changes, `.env` changes, production data operations, and commits remain deferred. Project play is covered by the Workflow Project Play Approval entry above.

## 2026-06-22 - api-agent/test-agent - Workflow Procure Approval

### Completed

- Replaced `Process_procure` purchase-order approval behavior with a bounded PHP replacement for Java `BizProcureApproveDelegate`.
- First `Activity_approval` approve now advances to `Activity_procure_approval` assigned to the workflow `procure` user without creating purchase rows.
- `Activity_procure_approval` approve now stores submitted `productList` and `amount`, then advances to `Activity_approval_procure` when `approvesGeneralOffice` is non-empty.
- Empty `approvesGeneralOffice` skips the general-office task and creates the purchase order immediately after procurement confirmation.
- Final `Activity_approval_procure` approve calls `PurchaseOrderService::purchaseOrderFromWorkflow()` inside the workflow transaction.
- Workflow-owned procurement writes `biz_purchase_order.INSTANCE_ID = processInstanceId`, `SETTLEMENT_STATUS = NOT_COMPLETED`, `STORAGE_STATUS = NOT_IN_WAREHOUSE`, supplier snapshot JSON, and one `biz_purchase_order_item` row per confirmed product.
- Reject at any stage closes workflow history without purchase-order, delivery, inventory, or finance side effects.
- Added `scripts/workflow-procure-approve-http-smoke.ps1`, added it to `scripts/project-preflight.ps1` and `scripts/project-progress.ps1`, updated `scripts/workflow-general-start-http-smoke.ps1`, and added `docs/tasks/workflow-procure-approve-plan.md`.

### Modified Files

- `app/service/workflow/WorkflowRuntimeService.php`
- `app/service/biz/PurchaseOrderService.php`
- `scripts/workflow-procure-approve-http-smoke.ps1`
- `scripts/workflow-general-start-http-smoke.ps1`
- `scripts/project-preflight.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/workflow-procure-approve-plan.md`
- `docs/tasks/workflow-general-start-runtime-plan.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/workflow-agent-java-map.md`
- `docs/tasks/workflow-side-effect-map.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Verification

- `php -l app\service\workflow\WorkflowRuntimeService.php`: passed.
- `php -l app\service\biz\PurchaseOrderService.php`: passed.
- PowerShell parser checks for `scripts\workflow-procure-approve-http-smoke.ps1`, `scripts\workflow-general-start-http-smoke.ps1`, `scripts\project-preflight.ps1`, and `scripts\project-progress.ps1`: passed.
- `.\scripts\workflow-procure-approve-http-smoke.ps1`: passed.
- `.\scripts\workflow-general-start-http-smoke.ps1`: passed.

### Deferred

- Project delivery, reissue, and return workflows, non-leave edit behavior, task SSE, notifications, Java data-change events, Java source changes, schema changes, `.env` changes, production data operations, and commits remain deferred. Project play is covered by the Workflow Project Play Approval entry above.

## 2026-06-22 - api-agent/test-agent - Workflow Payment-Out Approval

### Completed

- Replaced `Process_reimbursement` and `Process_make_payment` approval-out behavior with a bounded two-step PHP runtime path.
- First `Activity_approval` approve now advances to `Activity_pay_approval` assigned to the workflow `treasurer` without creating finance business rows.
- `Activity_pay_approval` approve now updates finance form variables, calls `SettlementAccountService::expensesFromWorkflow()`, writes expenditure/statement rows with `PROCESS_ID = processInstanceId`, writes statement `PROCESS_CATEGORY = Process_reimbursement` or `Process_make_payment`, and decrements the selected settlement account.
- Reject at either approval step closes the workflow without expenditure, statement, or account-balance side effects.
- Existing manual `/biz/settlementaccount/expenses/add` behavior remains unchanged and still writes `PROCESS_ID = Process_sys`.
- Added `scripts/workflow-payment-out-approve-http-smoke.ps1`, added it to `scripts/project-preflight.ps1`, and adjusted `scripts/workflow-general-start-http-smoke.ps1` for the new finance-confirmation step.

### Modified Files

- `app/service/workflow/WorkflowRuntimeService.php`
- `app/service/biz/SettlementAccountService.php`
- `scripts/workflow-payment-out-approve-http-smoke.ps1`
- `scripts/workflow-general-start-http-smoke.ps1`
- `scripts/project-preflight.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/workflow-payment-out-approve-plan.md`
- `docs/tasks/workflow-general-start-runtime-plan.md`
- `docs/api/biz-workflow-readonly-compat.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/workflow-agent-java-map.md`
- `docs/tasks/workflow-side-effect-map.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Verification

- `php -l app\service\workflow\WorkflowRuntimeService.php`: passed.
- `php -l app\service\biz\SettlementAccountService.php`: passed.
- PowerShell parser checks for `scripts\workflow-payment-out-approve-http-smoke.ps1`, `scripts\workflow-general-start-http-smoke.ps1`, and `scripts\project-preflight.ps1`: passed.
- `.\scripts\workflow-payment-out-approve-http-smoke.ps1`: passed.
- `.\scripts\workflow-general-start-http-smoke.ps1`: passed.
- `.\scripts\settlement-account-expenses-add-http-smoke.ps1`: passed; manual quick expense still writes `PROCESS_ID = Process_sys`.
- `.\scripts\workflow-payment-approve-http-smoke.ps1`: passed.
- `.\scripts\workflow-procure-warehouse-approve-http-smoke.ps1`: passed.
- `.\scripts\workflow-task-transition-http-smoke.ps1`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed.

### Deferred

- Procurement-order creation and remaining project reissue and return workflows, non-leave edit behavior, task SSE, notifications, Java data-change events, Java source changes, schema changes, `.env` changes, production data operations, and commits remain deferred. Project delivery and project play are covered by later workflow entries above.

## 2026-06-22 - api-agent/test-agent - Workflow Payment Approval

### Completed

- Replaced `Process_payment` `Activity_approval` approve behavior with a bounded PHP replacement for Java `BizPaymentApproveDelegate`.
- `WorkflowRuntimeService::transitionApprovalTask()` now allows `Process_payment` tasks in addition to `Process_ask_leave` and `Process_procure_in_warehouse`.
- Approved payment workflows call `SettlementAccountService::paymentFromWorkflow()` inside the workflow transaction.
- Workflow-owned income writes settlement statements with `PROCESS_ID = processInstanceId` and `PROCESS_CATEGORY = Process_payment`, writes linked `biz_payment_record.PROCESS_ID = processInstanceId`, and increments the selected settlement account balance.
- Existing manual `/biz/settlementaccount/payment/add` behavior remains unchanged and still writes `PROCESS_ID = Process_sys`.
- Added `scripts/workflow-payment-approve-http-smoke.ps1`, added it to `scripts/project-preflight.ps1` and `scripts/project-progress.ps1`, and added `docs/tasks/workflow-payment-approve-plan.md`.

### Modified Files

- `app/service/workflow/WorkflowRuntimeService.php`
- `app/service/biz/SettlementAccountService.php`
- `scripts/workflow-payment-approve-http-smoke.ps1`
- `scripts/workflow-general-start-http-smoke.ps1`
- `scripts/project-preflight.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/workflow-payment-approve-plan.md`
- `docs/tasks/workflow-general-start-runtime-plan.md`
- `docs/api/biz-workflow-readonly-compat.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/workflow-agent-java-map.md`
- `docs/tasks/workflow-side-effect-map.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Verification

- `php -l app\service\workflow\WorkflowRuntimeService.php`: passed.
- `php -l app\service\biz\SettlementAccountService.php`: passed.
- PowerShell parser checks for `scripts\workflow-payment-approve-http-smoke.ps1`, `scripts\workflow-general-start-http-smoke.ps1`, `scripts\project-preflight.ps1`, and `scripts\project-progress.ps1`: passed.
- `.\scripts\workflow-payment-approve-http-smoke.ps1`: passed.
- `.\scripts\workflow-general-start-http-smoke.ps1`: passed.
- `.\scripts\settlement-account-payment-add-http-smoke.ps1`: passed; manual quick income still writes `PROCESS_ID = Process_sys`.
- `.\scripts\workflow-task-transition-http-smoke.ps1`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed.

### Deferred

- Reimbursement and make-payment approval are covered by the Workflow Payment-Out Approval entry above; procurement-order creation and remaining project reissue and return workflows, non-leave edit behavior, task SSE, notifications, Java data-change events, Java source changes, schema changes, `.env` changes, production data operations, and commits remain deferred. Project delivery and project play are covered by later workflow entries above.

## 2026-06-22 - api-agent/test-agent - Workflow Procure Warehouse Approval

### Completed

- Replaced `Process_procure_in_warehouse` `Activity_approval` approve behavior with a bounded PHP replacement for Java `BizProcureInWareHouseJavaDelegate`.
- `WorkflowRuntimeService::transitionApprovalTask()` now allows `Process_procure_in_warehouse` tasks in addition to `Process_ask_leave`.
- Approved procurement-warehouse workflows call `PurchaseOrderService::warehouseOneFromWorkflow()` inside the workflow transaction.
- Workflow-owned stock-in writes delivery rows with `PROCESS_ID = processInstanceId`, `PROCESS_CATEGORY = Process_procure_in_warehouse`, and `OBJECT_ID = orderId`.
- Existing manual `/biz/bizpurchaseorder/warehouse/one/add` behavior remains unchanged and still writes `PROCESS_ID = Process_sys`.
- Added `scripts/workflow-procure-warehouse-approve-http-smoke.ps1`, added it to `scripts/project-preflight.ps1` and `scripts/project-progress.ps1`, and added `docs/tasks/workflow-procure-warehouse-approve-plan.md`.

### Modified Files

- `app/service/workflow/WorkflowRuntimeService.php`
- `app/service/biz/PurchaseOrderService.php`
- `scripts/workflow-procure-warehouse-approve-http-smoke.ps1`
- `scripts/workflow-general-start-http-smoke.ps1`
- `scripts/project-preflight.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/workflow-procure-warehouse-approve-plan.md`
- `docs/tasks/workflow-general-start-runtime-plan.md`
- `docs/api/biz-workflow-readonly-compat.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/workflow-agent-java-map.md`
- `docs/tasks/workflow-side-effect-map.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Verification

- `php -l app\service\workflow\WorkflowRuntimeService.php`: passed.
- `php -l app\service\biz\PurchaseOrderService.php`: passed.
- PowerShell parser checks for `scripts\workflow-procure-warehouse-approve-http-smoke.ps1`, `scripts\workflow-general-start-http-smoke.ps1`, `scripts\project-preflight.ps1`, and `scripts\project-progress.ps1`: passed.
- `.\scripts\workflow-procure-warehouse-approve-http-smoke.ps1`: passed.
- `.\scripts\workflow-general-start-http-smoke.ps1`: passed.
- `.\scripts\workflow-task-transition-http-smoke.ps1`: passed.
- `.\scripts\purchase-order-warehouse-one-add-http-smoke.ps1`: passed; manual quick stock-in still writes `PROCESS_ID = Process_sys`.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed.
- `.\scripts\project-progress.ps1 -Lean`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.

### Deferred

- Reimbursement and make-payment approval are covered by the later Workflow Payment-Out Approval entry above; procurement-order creation and remaining project reissue and return workflows, non-leave edit behavior, task SSE, notifications, Java data-change events, Java source changes, schema changes, `.env` changes, production data operations, and commits remain deferred. Payment approval, project delivery, and project play are now covered by later workflow slices above.

## 2026-06-22 - api-agent/test-agent - Workflow General Start Runtime

### Completed

- Replaced five non-project workflow start controlled-deferred wrappers with minimal first-step runtime/history writes.
- Covered `Process_payment`, `Process_reimbursement`, `Process_make_payment`, `Process_procure`, and `Process_procure_in_warehouse`.
- The routes now validate current user, tenant context, approvers, process-specific required fields, optional copy users, and optional file ids.
- Each successful start creates root/approval executions, one active `Activity_approval` task, runtime variables, history rows, CC records, and workflow file relations with the actual process key.
- `POST /biz/process/cancel` now also cancels active unapproved non-project first-step processes; non-leave approve/reject remains blocked.
- Added `scripts/workflow-general-start-http-smoke.ps1`, added it to `scripts/project-preflight.ps1`, removed the five routes from `scripts/frontend-deferred-write-wrapper-smoke.ps1`, and added `docs/tasks/workflow-general-start-runtime-plan.md`.

### Modified Files

- `app/controller/biz/ProcessController.php`
- `app/service/workflow/WorkflowRuntimeService.php`
- `scripts/workflow-general-start-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-preflight.ps1`
- `docs/tasks/workflow-general-start-runtime-plan.md`
- `docs/api/biz-workflow-readonly-compat.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/workflow-agent-java-map.md`
- `docs/tasks/workflow-side-effect-map.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Verification

- `php -l app\service\workflow\WorkflowRuntimeService.php`: passed.
- `php -l app\controller\biz\ProcessController.php`: passed.
- PowerShell parser checks for `scripts\workflow-general-start-http-smoke.ps1`, `scripts\frontend-deferred-write-wrapper-smoke.ps1`, and `scripts\project-preflight.ps1`: passed.
- Targeted `php think route:list` check listed all five non-project start routes and a remaining project-start deferred route.
- `.\scripts\workflow-general-start-http-smoke.ps1`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed after the five routes were removed from the deferred list.
- `git diff --check`: passed with CRLF conversion warnings only.
- `.\scripts\project-progress.ps1 -Lean`: passed.

### Deferred

- Project delivery, reissue, and return workflows, non-leave approve/reject completion, non-leave edit behavior, remaining project Java delegate side effects, task SSE, notifications, Java data-change events, Java source changes, schema changes, `.env` changes, production data operations, and commits remain deferred. Project play is covered by the Workflow Project Play Approval entry above.

## 2026-06-22 - api-agent/test-agent - Settlement Account Delete

### Completed

- Replaced `POST /biz/settlementaccount/delete` deferred behavior with protected logical deletion for unused settlement accounts.
- The route now accepts Java/copied-frontend delete payload shapes, validates the full batch, locks active account rows, and reuses existing tenant/write-scope guards.
- Active references in settlement statements, payment-record target/object fields, and expenditure-record target/object fields block deletion.
- Successful deletion updates only `settlement_account.DELETE_FLAG`, `UPDATE_TIME`, and `UPDATE_USER`.
- Added `scripts/settlement-account-delete-http-smoke.ps1`, added it to `scripts/project-preflight.ps1`, removed the route from `scripts/frontend-deferred-write-wrapper-smoke.ps1`, and added `docs/tasks/settlement-account-delete-plan.md`.

### Modified Files

- `app/controller/biz/SettlementAccountController.php`
- `app/service/biz/SettlementAccountService.php`
- `scripts/settlement-account-delete-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-preflight.ps1`
- `docs/tasks/settlement-account-delete-plan.md`
- `docs/api/biz-settlement-account-readonly-compat.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Verification

- `php -l app\controller\biz\SettlementAccountController.php`: passed.
- `php -l app\service\biz\SettlementAccountService.php`: passed.
- PowerShell parser checks for `scripts\settlement-account-delete-http-smoke.ps1`, `scripts\frontend-deferred-write-wrapper-smoke.ps1`, and `scripts\project-preflight.ps1`: passed.
- `.\scripts\settlement-account-delete-http-smoke.ps1`: passed.
- `.\scripts\settlement-account-payment-add-http-smoke.ps1`: passed when rerun serially.
- `.\scripts\settlement-account-expenses-add-http-smoke.ps1`: passed when rerun serially.
- `.\scripts\settlement-account-transfer-add-http-smoke.ps1`: passed when rerun serially.
- `.\scripts\settlement-account-read-http-smoke.ps1`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed after `/biz/settlementaccount/delete` was removed from the deferred list.

### Deferred

- Physical delete through the HTTP route, balance mutation, statement/payment/expenditure mutation, workflow hooks, notifications, Java data-change events, Java source changes, schema changes, `.env` changes, production data operations, and commits remain deferred.

## 2026-06-22 - api-agent/test-agent - Workflow Payroll Generation Coverage

### Completed

- Confirmed the Java-compatible boundary: workflow approval creates the `biz_leave_application` row, while payroll deduction is calculated later by explicit `/biz/bizpayroll/generate/add`.
- Extended `scripts/workflow-task-transition-http-smoke.ps1` so an approved `Process_ask_leave` `leaveOfAbsence` row is followed by payroll generation for the same user/month.
- The smoke verifies generated `biz_payroll.VACATION` equals the approved workflow leave amount.
- Added `docs/tasks/workflow-payroll-generation-coverage-plan.md` and updated workflow, payroll, leave, gap, progress, and deferred-wrapper docs.

### Modified Files

- `scripts/workflow-task-transition-http-smoke.ps1`
- `scripts/biz-payroll-generate-add-http-smoke.ps1`
- `docs/tasks/workflow-payroll-generation-coverage-plan.md`
- `docs/tasks/workflow-task-transition-runtime-plan.md`
- `docs/tasks/workflow-leave-application-side-effect-plan.md`
- `docs/tasks/workflow-annual-leave-deduction-plan.md`
- `docs/tasks/workflow-process-cancel-edit-plan.md`
- `docs/tasks/workflow-leave-start-runtime-plan.md`
- `docs/api/biz-payroll-readonly.md`
- `docs/api/biz-leave-application-readonly.md`
- `docs/api/biz-workflow-readonly-compat.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Verification

- `php -l app\service\workflow\WorkflowRuntimeService.php`: passed.
- `php -l app\service\biz\BizPayrollService.php`: passed.
- PowerShell parser check for `scripts\workflow-task-transition-http-smoke.ps1`: passed.
- `.\scripts\workflow-task-transition-http-smoke.ps1`: passed; `/biz/bizpayroll/generate/add` included the workflow-approved leave amount in generated payroll `VACATION`.
- PowerShell parser check for `scripts\biz-payroll-generate-add-http-smoke.ps1`: passed.
- `.\scripts\biz-payroll-generate-add-http-smoke.ps1`: passed after tightening temporary narrow-field ids in the smoke setup.

### Deferred

- Automatic updates to already-created `biz_payroll` rows on workflow approval, payroll add, EasyExcel-style xlsx export rendering, notifications, data-change events, non-leave delegates, task SSE push, Java source changes, schema changes, `.env` changes, production data operations, and commits remain deferred.

## 2026-06-22 - api-agent/test-agent - Workflow File Relation Binding

### Completed

- Active `POST /biz/process/leave/start` now generates `biz_file_relation` rows for submitted active same-tenant `fileIdList` files.
- The generated rows mirror the file-relation portion of Java `CopyUserDelegate`: `OBJECT_ID = processInstanceId`, `TARGET_ID = dev_file.ID`, `CATEGORY = Process_ask_leave`, `FILE_NAME = dev_file.NAME`, starter `CREATE_USER`, tenant id, and `DELETE_FLAG = NOT_DELETE`.
- Leave-start responses now include `fileRelationCount`.
- Extended `scripts/workflow-leave-start-http-smoke.ps1` to create a temporary `dev_file`, pass it through `fileIdList`, verify generated DB state, verify `/biz/process/fileList` readback, and clean up temporary rows.
- Added `docs/tasks/workflow-file-relation-binding-plan.md` and updated workflow/file-relation/gap/progress docs.

### Modified Files

- `app/service/workflow/WorkflowRuntimeService.php`
- `scripts/workflow-leave-start-http-smoke.ps1`
- `docs/tasks/workflow-file-relation-binding-plan.md`
- `docs/api/biz-file-relation-readonly-compat.md`
- `docs/tasks/workflow-copy-user-records-plan.md`
- `docs/tasks/workflow-leave-start-runtime-plan.md`
- `docs/api/biz-workflow-readonly-compat.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Verification

- `php -l app\service\workflow\WorkflowRuntimeService.php`: passed.
- PowerShell parser check for `scripts\workflow-leave-start-http-smoke.ps1`: passed.
- `.\scripts\workflow-leave-start-http-smoke.ps1`: passed; generated file-relation DB state, `/biz/process/fileList` readback, generated CC row, runtime rows, and cleanup were verified.
- `.\scripts\workflow-read-http-smoke.ps1`: passed.
- `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -FileRelationHttpSmoke`: passed; this also ran Composer dump-autoload, route coverage, full PHP lint, frontend API method smoke, and whitespace check.
- `.\scripts\workflow-task-transition-http-smoke.ps1`: passed.

### Deferred

- Copy/file generation outside active `Process_ask_leave` leave starts, notifications, data-change events, automatic existing-payroll row updates, non-leave delegates, task SSE push, Java source changes, schema changes, `.env` changes, production data operations, and commits remain deferred. Workflow-approved `leaveOfAbsence` payroll generation is covered by the Workflow Payroll Generation Coverage slice.

## 2026-06-22 - api-agent/test-agent - Workflow Copy-User Records

### Completed

- Active `POST /biz/process/leave/start` now generates `biz_cc_records` rows for submitted `copyUserIdList` users.
- The generated rows mirror the CC-record portion of Java `CopyUserDelegate`: workflow title, process definition id, instance id, promoter id, `CATEGORY = Process_ask_leave`, copied user, copied-user `CREATE_USER`, tenant id, and `DELETE_FLAG = NOT_DELETE`.
- Leave-start responses now include `ccRecordCount`.
- `/biz/ccrecords/page` now returns a clean camelCase payload shape without duplicate uppercase/lowercase JSON keys.
- Extended `scripts/workflow-leave-start-http-smoke.ps1` to verify generated CC-row DB state, current-user readback, and cleanup.
- Added `docs/tasks/workflow-copy-user-records-plan.md` and updated workflow/CC/gap/progress docs.

### Modified Files

- `app/service/workflow/WorkflowRuntimeService.php`
- `app/service/biz/CcRecordsService.php`
- `scripts/workflow-leave-start-http-smoke.ps1`
- `docs/tasks/workflow-copy-user-records-plan.md`
- `docs/api/biz-cc-records-readonly.md`
- `docs/tasks/biz-cc-records-write-plan.md`
- `docs/tasks/workflow-leave-start-runtime-plan.md`
- `docs/api/biz-workflow-readonly-compat.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Verification

- `php -l app\service\workflow\WorkflowRuntimeService.php`: passed.
- `php -l app\service\biz\CcRecordsService.php`: passed.
- PowerShell parser check for `scripts\workflow-leave-start-http-smoke.ps1`: passed.
- `.\scripts\workflow-leave-start-http-smoke.ps1`: passed; generated copy-user CC row DB state, `/biz/ccrecords/page` readback, runtime rows, and cleanup were verified.
- `.\scripts\biz-cc-records-write-http-smoke.ps1`: passed.
- `.\scripts\workflow-read-http-smoke.ps1`: passed.
- `.\scripts\workflow-task-transition-http-smoke.ps1`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed.

### Deferred

- Copy-user generation outside active `Process_ask_leave` leave starts, notifications, data-change events, automatic existing-payroll row updates, non-leave delegates, task SSE push, Java source changes, schema changes, `.env` changes, production data operations, and commits remain deferred. Subsequent state on 2026-06-22: active leave-start file relation binding is covered by the Workflow File Relation Binding slice; workflow-approved `leaveOfAbsence` payroll generation is covered by the Workflow Payroll Generation Coverage slice.

## 2026-06-22 - api-agent/test-agent - Biz Leave Application Vacation Adjustment

### Completed

- Direct `POST /biz/bizleaveapplication/edit` now adjusts current-year `biz_user_vacation.USED_AMOUNT` when the old or new leave row category is `annualLeave`.
- Direct `POST /biz/bizleaveapplication/delete` now restores current-year annual-leave amounts before logical delete.
- Missing annual-leave balance, insufficient remaining balance, and used-amount underflow return `400` and roll back the leave write.
- Added `scripts/biz-leave-application-vacation-adjustment-http-smoke.ps1` and included it in `scripts/project-preflight.ps1` with `-SkipBizLeaveApplicationVacationAdjustment`.
- Added `docs/tasks/biz-leave-application-vacation-adjustment-plan.md` and updated leave/vacation/workflow/progress docs.

### Modified Files

- `app/service/biz/BizLeaveApplicationService.php`
- `scripts/biz-leave-application-vacation-adjustment-http-smoke.ps1`
- `scripts/project-preflight.ps1`
- `docs/tasks/biz-leave-application-vacation-adjustment-plan.md`
- `docs/api/biz-leave-application-readonly.md`
- `docs/api/biz-user-vacation-readonly.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Verification

- `php -l app\service\biz\BizLeaveApplicationService.php`: passed.
- PowerShell parser checks for `scripts\biz-leave-application-vacation-adjustment-http-smoke.ps1` and `scripts\project-preflight.ps1`: passed.
- `.\scripts\biz-leave-application-vacation-adjustment-http-smoke.ps1`: passed; direct annual-leave edit amount deltas, category restoration, insufficient-balance rollback, and delete restoration were verified.

### Deferred

- Direct leave add, vacation generation, automatic existing-payroll row updates, copy-user generation outside active leave start, notifications, data-change events, Java source changes, schema changes, `.env` changes, production data operations, and commits remain deferred. Subsequent state on 2026-06-22: active leave-start copy-user CC rows are covered by the Workflow Copy-User Records slice; workflow-approved `leaveOfAbsence` payroll generation is covered by the Workflow Payroll Generation Coverage slice.

## 2026-06-22 - api-agent/test-agent - Workflow Process Cancel/Edit Runtime

### Completed

- Replaced `POST /biz/process/cancel` and `POST /biz/process/leave/edit` controlled-deferred behavior for active `Process_ask_leave` workflows.
- `cancel` now allows the initiator to cancel an active unapproved leave process, closes task/activity/process history rows, writes `status/state = cancel`, writes `approval = false` and `cancel = true`, clears runtime rows, and creates no leave/vacation side effects.
- `leave/edit` now allows the initiator to edit an active leave process only while `isEdit = true`, updates runtime and historic `endTime`, `amount`, and `remark`, then sets `isEdit = false`.
- Approval after edit now uses the edited values for the final `biz_leave_application` row and annual-leave deduction.
- Added `scripts/workflow-process-cancel-edit-http-smoke.ps1` and added it to `scripts/project-preflight.ps1` with `-SkipWorkflowProcessCancelEdit`.
- Added `docs/tasks/workflow-process-cancel-edit-plan.md` and updated workflow/deferred-wrapper/progress docs.

### Modified Files

- `app/controller/biz/ProcessController.php`
- `app/service/workflow/WorkflowRuntimeService.php`
- `scripts/workflow-process-cancel-edit-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-preflight.ps1`
- `docs/tasks/workflow-process-cancel-edit-plan.md`
- `docs/api/biz-workflow-readonly-compat.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/api/biz-leave-application-readonly.md`
- `docs/api/biz-user-vacation-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Verification

- `php -l app\controller\biz\ProcessController.php`: passed.
- `php -l app\service\workflow\WorkflowRuntimeService.php`: passed.
- PowerShell parser checks for `scripts\workflow-process-cancel-edit-http-smoke.ps1`, `scripts\frontend-deferred-write-wrapper-smoke.ps1`, and `scripts\project-preflight.ps1`: passed.
- `.\scripts\workflow-process-cancel-edit-http-smoke.ps1`: passed; cancel verified runtime cleanup/final cancel variables/no vacation change, edit verified one-time variable update, approval after edit used edited leave amount, and non-editable processes were rejected.

### Deferred

- Other process starts/transitions, non-leave cancel/edit behavior, copy-user generation outside active leave start, automatic existing-payroll row updates, non-leave delegates, task SSE push, Java source changes, schema changes, `.env` changes, production data operations, and commits remain deferred. Subsequent state on 2026-06-22: direct leave-row annual-leave restoration is covered by the Biz Leave Application Vacation Adjustment slice, active leave-start copy-user CC rows are covered by the Workflow Copy-User Records slice, and workflow-approved `leaveOfAbsence` payroll generation is covered by the Workflow Payroll Generation Coverage slice.

## 2026-06-22 - api-agent/test-agent - Workflow Annual Leave Deduction

### Completed

- Extended approved `Process_ask_leave` `Activity_approval` transitions so `category = annualLeave` deducts the current-year `biz_user_vacation` balance.
- The deduction locks the active annual-leave balance row, validates `AMOUNT - USED_AMOUNT >= leave amount`, increments `USED_AMOUNT`, refreshes update audit fields, and increments `VERSION`.
- The deduction runs only after a new approved leave row is inserted; idempotent leave-row updates do not deduct again.
- Insufficient balance rolls back the whole approval transaction: the runtime task remains active, no leave row is inserted, and `USED_AMOUNT` remains unchanged.
- Extended `scripts/workflow-task-transition-http-smoke.ps1` to cover approved annual-leave deduction and insufficient-balance rollback.
- Added `docs/tasks/workflow-annual-leave-deduction-plan.md` and updated workflow/deferred-wrapper/progress docs.

### Modified Files

- `app/service/workflow/WorkflowRuntimeService.php`
- `scripts/workflow-task-transition-http-smoke.ps1`
- `docs/tasks/workflow-annual-leave-deduction-plan.md`
- `docs/tasks/workflow-leave-application-side-effect-plan.md`
- `docs/tasks/workflow-task-transition-runtime-plan.md`
- `docs/tasks/workflow-leave-start-runtime-plan.md`
- `docs/api/biz-workflow-readonly-compat.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Verification

- `php -l app\service\workflow\WorkflowRuntimeService.php`: passed.
- PowerShell parser check for `scripts\workflow-task-transition-http-smoke.ps1`: passed.
- `.\scripts\workflow-task-transition-http-smoke.ps1`: passed; annual-leave approve deducted `USED_AMOUNT`/incremented `VERSION`, and insufficient-balance approve rolled back workflow/leave/vacation writes.

### Deferred

- Copy-user records, automatic existing-payroll row updates, other workflow starts/transitions, non-leave delegates, task SSE push, Java source changes, schema changes, `.env` changes, production data operations, and commits remain deferred. Subsequent state on 2026-06-22: active leave process cancel/edit is covered by the Workflow Process Cancel/Edit Runtime slice, direct leave-row annual-leave restoration is covered by the Biz Leave Application Vacation Adjustment slice, and workflow-approved `leaveOfAbsence` payroll generation is covered by the Workflow Payroll Generation Coverage slice.

## 2026-06-22 - api-agent/test-agent - Workflow Leave Application Side Effect

### Completed

- Extended the minimal `Process_ask_leave` `Activity_approval` approve path so approved workflows generate or update one `biz_leave_application` row.
- The leave row is built from historic workflow variables: `initiator`, `category`, `amount`, `remark`, `startTime`, `endTime`, `tenantId`, and `objectId`.
- Added overlap protection for active leave rows with the same user, tenant, and time range, excluding the same `PROCESS_ID` for idempotent retries.
- Reject transitions still close workflow history but create zero leave-application rows.
- Extended `scripts/workflow-task-transition-http-smoke.ps1` to verify approved leave-row creation/read-back through `/biz/bizleaveapplication/my/page` and `/detail`, rejected zero-row behavior, and cleanup by `processInstanceId`.
- Added `docs/tasks/workflow-leave-application-side-effect-plan.md` and updated workflow/deferred-wrapper/progress docs.

### Modified Files

- `app/service/workflow/WorkflowRuntimeService.php`
- `scripts/workflow-task-transition-http-smoke.ps1`
- `docs/tasks/workflow-leave-application-side-effect-plan.md`
- `docs/tasks/workflow-task-transition-runtime-plan.md`
- `docs/tasks/workflow-leave-start-runtime-plan.md`
- `docs/api/biz-workflow-readonly-compat.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Verification

- `php -l app\service\workflow\WorkflowRuntimeService.php`: passed.
- PowerShell parser check for `scripts\workflow-task-transition-http-smoke.ps1`: passed.
- `.\scripts\workflow-task-transition-http-smoke.ps1`: passed; approve verified leave-row creation/read-back, reject verified zero leave rows, and cleanup removed temporary workflow/leave rows.

### Deferred

- Copy-user records, automatic existing-payroll row updates, other workflow starts/transitions, non-leave delegates, task SSE push, Java source changes, schema changes, `.env` changes, production data operations, and commits remain deferred. Subsequent state on 2026-06-22: approved annual-leave deduction is covered by the Workflow Annual Leave Deduction slice, active leave process cancel/edit is covered by the Workflow Process Cancel/Edit Runtime slice, direct leave-row annual-leave restoration is covered by the Biz Leave Application Vacation Adjustment slice, and workflow-approved `leaveOfAbsence` payroll generation is covered by the Workflow Payroll Generation Coverage slice.

## 2026-06-22 15:45 +08:00 - api-agent/test-agent - Workflow Task Transition Runtime

### Completed

- Replaced `POST /biz/task/approve` and `POST /biz/task/reject` controlled-deferred behavior with minimal ThinkPHP runtime transitions for `Process_ask_leave` `Activity_approval`.
- Added current-assignee validation so only the active task assignee can complete or reject the task.
- Restricted the implementation to `Process_ask_leave` plus `Activity_approval`; other process/task combinations still return deferred transition errors.
- Approve writes `approval = true`, `status = AGREE`, `state = AGREE`, closes the historic task/activity/process rows, and removes matching runtime task/variable/execution rows.
- Reject writes `approval = false`, `status = REJECT`, `state = REJECT`, closes the historic task/activity/process rows with task `DELETE_REASON_ = deleted`, and removes matching runtime task/variable/execution rows.
- Added `scripts/workflow-task-transition-http-smoke.ps1` and included it in `scripts/project-preflight.ps1` with `-SkipWorkflowTaskTransition`.
- Removed `/biz/task/approve` and `/biz/task/reject` from `scripts/frontend-deferred-write-wrapper-smoke.ps1`.
- Added `docs/tasks/workflow-task-transition-runtime-plan.md` and updated workflow/deferred-wrapper/progress docs.

### Modified Files

- `app/controller/biz/TaskController.php`
- `app/service/workflow/WorkflowRuntimeService.php`
- `scripts/workflow-task-transition-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-preflight.ps1`
- `docs/tasks/workflow-task-transition-runtime-plan.md`
- `docs/api/biz-workflow-readonly-compat.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Verification

- `php -l app\service\workflow\WorkflowRuntimeService.php`: passed.
- `php -l app\controller\biz\TaskController.php`: passed.
- PowerShell parser checks for `scripts\workflow-task-transition-http-smoke.ps1`, `scripts\frontend-deferred-write-wrapper-smoke.ps1`, and `scripts\project-preflight.ps1`: passed.
- `.\scripts\workflow-task-transition-http-smoke.ps1`: passed; approve and reject both verified runtime cleanup, history completion, final variables, history page read-back, and temporary-row cleanup.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed with 38 authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, and 8 representative POST no-token checks plus one task-SSE no-token check.
- `.\scripts\workflow-read-http-smoke.ps1`: passed; optional sample detail checks skipped when local sample rows were unavailable.
- `.\scripts\workflow-leave-start-http-smoke.ps1`: passed after task transition changes.
- `git diff --check`: passed with normal LF/CRLF working-copy warnings only.

### Deferred

- Task transitions outside `Process_ask_leave` `Activity_approval`, other process starts, non-leave BPMN service-task/delegate behavior, copy-user notification records, payroll recalculation, task SSE push, Java source changes, schema changes, `.env` changes, production data operations, and commits remain deferred. Subsequent state on 2026-06-22: approved leave-row creation, approved annual-leave deduction, active leave process cancel/edit, and direct leave-row annual-leave restoration are now covered by later slices.

## 2026-06-22 15:10 +08:00 - api-agent/test-agent - Workflow Leave Start Runtime

### Completed

- Replaced `POST /biz/process/leave/start` controlled-deferred behavior with the first minimal ThinkPHP workflow runtime write path for `Process_ask_leave`.
- Added `WorkflowRuntimeService::startLeaveProcess` to validate the current user, tenant/org context, leave category, start time, and approver/copy/file lists.
- The start path now creates Camunda-compatible runtime/history rows in `act_ru_execution`, `act_ru_task`, `act_ru_variable`, `act_hi_procinst`, `act_hi_taskinst`, `act_hi_actinst`, and `act_hi_varinst`.
- The active task is assigned to the first submitted approver and reads back through the existing `/biz/task/page`, `/biz/process/page`, and `/biz/task/runtime/activity/detail` adapters.
- Removed `/biz/process/leave/start` from `scripts/frontend-deferred-write-wrapper-smoke.ps1`.
- Added `scripts/workflow-leave-start-http-smoke.ps1` and included it in `scripts/project-preflight.ps1` with `-SkipWorkflowLeaveStart`.
- Added `docs/tasks/workflow-leave-start-runtime-plan.md` and updated the deferred-wrapper/dashboard/top-level logs.

### Modified Files

- `app/controller/biz/ProcessController.php`
- `app/service/workflow/WorkflowRuntimeService.php`
- `scripts/workflow-leave-start-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-preflight.ps1`
- `docs/tasks/workflow-leave-start-runtime-plan.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Verification

- `php -l app\service\workflow\WorkflowRuntimeService.php`: passed.
- `php -l app\controller\biz\ProcessController.php`: passed.
- PowerShell parser checks for `scripts\workflow-leave-start-http-smoke.ps1`, `scripts\frontend-deferred-write-wrapper-smoke.ps1`, and `scripts\project-preflight.ps1`: passed.
- `php think route:list | Select-String -Pattern 'biz/process/leave/start|biz/process/leave/edit|biz/process/cancel|biz/task/approve|biz/task/reject|biz/task/sse/stream'`: listed expected routes.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed with 40 authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, and 9 representative POST no-token checks plus one task-SSE no-token check. Subsequent state after the task transition slice: 38 authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, and 8 representative POST no-token checks plus one task-SSE no-token check.
- `.\scripts\workflow-leave-start-http-smoke.ps1`: passed; the temporary process/task/variable rows were verified through read APIs and cleaned up.

### Deferred

- Other process starts, non-leave BPMN service-task/delegate behavior, copy-user notification records, payroll recalculation, Java source changes, schema changes, `.env` changes, production data operations, and commits remain deferred. Subsequent state on 2026-06-22: minimal `Process_ask_leave` `Activity_approval` approve/reject transitions, approved leave-row creation, approved annual-leave deduction, active leave process cancel/edit, and direct leave-row annual-leave restoration are now covered by later slices.

## 2026-06-18 17:43 +08:00 - api-agent/test-agent - Sale Project Foundation Closure

### Completed

- Replaced `/biz/saleproject/add`, `/biz/saleproject/edit`, `/biz/saleproject/history/add`, and `/biz/saleproject/special/add` controlled-deferred responses with guarded service behavior.
- Implemented Java-compatible normal add:
  - validates active customer access, tenant, and data scope;
  - creates one `biz_sale_project` row with `FOLLOW`, `UNPAID`, `PRIVATE`, and zero amount defaults;
  - ignores copied-frontend product/state/amount spoof fields.
- Implemented Java-compatible normal edit:
  - validates active project access;
  - requires `PROJECT_STATE = FOLLOW`;
  - updates only `PROJECT_NAME`, `PROJECT_CATEGORY`, `REMARK`, `AREA`, `DETAILS_ADDRESS`, and `PROJECT_CODE`;
  - preserves customer, state, visibility, amount, product, finance, invoice, workflow, and delete fields.
- Implemented `history/add` as one history customer plus one direct private sale-project row with `HISTORY_AMOUNT` and Java-style payment-state correction.
- Implemented `special/add` as one history customer plus one direct private reimbursement project with `special_type = PUBLIC_FOR_REIMBURSEMENT` and Java-style payment-state correction.
- Added `scripts/sale-project-foundation-closure-http-smoke.ps1`.
- Removed the four foundation routes from `scripts/frontend-deferred-write-wrapper-smoke.ps1`; remaining deferred smoke now covers 47 authenticated POST wrappers plus the task-SSE deferred GET wrapper.
- Updated sale-project API docs, deferred-wrapper docs, API gap map, dashboard, bootstrap/handoff guidance, and top-level plan/status logs.

### Modified Files

- `app/controller/biz/SaleProjectController.php`
- `app/service/biz/SaleProjectService.php`
- `scripts/sale-project-foundation-closure-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/sale-project-foundation-closure-plan.md`
- `docs/api/biz-saleproject-readonly.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/context-handoff.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Verification

- `php -l app\controller\biz\SaleProjectController.php`: passed.
- `php -l app\service\biz\SaleProjectService.php`: passed.
- PowerShell parser checks for `scripts\sale-project-foundation-closure-http-smoke.ps1` and `scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed.
- `.\scripts\runtime-ready.ps1`: passed.
- Started local ThinkPHP backend at `http://127.0.0.1:82` for HTTP smoke.
- `php think route:list | Select-String -Pattern 'biz/saleproject/(add|edit|history/add|special/add|deal/edit|delete|repeal|cancel|amount/edit|visibility/edit)'`: listed expected routes.
- `.\scripts\sale-project-foundation-closure-http-smoke.ps1`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed.

### Deferred

- Product-item mutation through normal add/edit remains deferred until the Java ownership path is mapped as its own feature block.
- Invoice/invoicing item, payment, expenditure, settlement-account, delivery, inventory, return-order, reissue-order, workflow, file cleanup, notification, Java data-change events, frontend source changes, Java source changes, schema changes, `.env` changes, production data operations, and commits remain deferred.

## 2026-06-18 22:00 +08:00 - merge-agent - Feature Closure Execution Baseline

### Completed

- Changed the active project execution rule from "next smallest safe slice" to "next complete feature-closure block".
- Updated continuation workflow docs so a feature block must include:
  - Java reference mapping;
  - copied frontend caller mapping;
  - current ThinkPHP route/controller/service mapping;
  - database and downstream-read mapping;
  - side-effect map, non-goals, rollback strategy, and end-to-end smoke plan.
- Updated new-conversation and context-handoff starter prompts to default to the sale-project foundation closure block unless the user redirects.
- Updated the API gap map and progress helper output so future continuations do not pick one isolated wrapper when the route belongs to an opened feature block.
- Added `docs/tasks/sale-project-foundation-closure-plan.md` as the next default block for `/biz/saleproject/add`, `/biz/saleproject/edit`, `/biz/saleproject/history/add`, and `/biz/saleproject/special/add`.
- Added problem-log rows:
  - `P-038` for route-by-route slices being too slow and integration-risky for remaining business flows.
  - `P-039` for misclassifying database readiness by checking the Windows `MySQL80` service instead of the configured local runtime and ThinkPHP app-level connection.
- Confirmed the project database rule remains: read DB/Redis/login smoke credentials only from ignored `.env`, use the configured local runtime targets, and do not print or commit secrets.

### Modified Files

- `docs/tasks/lean-continuation-workflow.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/context-handoff.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/problem-optimization-log.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/sale-project-foundation-closure-plan.md`
- `scripts/project-progress.ps1`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Verification

- PowerShell parser check for `scripts\project-progress.ps1`: passed.
- `.\scripts\runtime-ready.ps1`: passed; `127.0.0.1:3306`, Redis `6379`, and PHP FastCGI `9000` were listening.
- ThinkPHP app-level DB query `SELECT 1 AS ok`: passed with `[{"ok":1}]`.
- Active startup wording scan for removed `next smallest safe slice` guidance in workflow/bootstrap/handoff/gap/progress files: passed.
- `.\scripts\project-progress.ps1 -Lean`: passed and now shows the feature-closure baseline, sale-project foundation closure target, and `P-038/P-039`.
- `git diff --check`: passed with normal LF/CRLF working-copy warnings only.

### Deferred

- No sale-project PHP business behavior was changed in this process update.
- Next implementation should start from `docs/tasks/sale-project-foundation-closure-plan.md` and close the sale-project foundation feature as one block.
- Product-item mutation through normal sale-project add/edit remains deferred until the Java-compatible ownership path is explicitly mapped.

## 2026-06-18 21:45 +08:00 - api-agent/test-agent - Sale Project Deal Edit

### Completed

- Selected `/biz/saleproject/deal/edit` as the next Java-exposed narrow sale-project route after completing `/biz/saleproject/delete`.
- Wrote `docs/tasks/sale-project-deal-edit-plan.md` for the Java reference, field whitelist, mutation boundary, and smoke-test scope.
- Replaced the controlled-deferred deal-edit controller wrapper with a guarded service call.
- Implemented `SaleProjectService::editDeal` as Java-compatible sale-project delivery/freight field maintenance:
  - accepts copied frontend/Java JSON bodies with `id`;
  - locks and validates the active project through the existing tenant/data-scope query;
  - updates only `UNIT`, `ADDRESS`, `LOGISTICS_CATEGORY`, `CONSIGNEE`, `PHONE`, `REMARK`, `FREIGHT`, `FREIGHT_CATEGORY`, and `DELIVERY_NOTE`;
  - refreshes audit fields and `VERSION`;
  - ignores protected spoofed state, amount, logical-delete, invoice, product, finance, and workflow fields.
- Added `scripts/sale-project-deal-edit-http-smoke.ps1`.
- Removed `/biz/saleproject/deal/edit` from the deferred-wrapper smoke list.

### Modified Files

- `app/controller/biz/SaleProjectController.php`
- `app/service/biz/SaleProjectService.php`
- `scripts/sale-project-deal-edit-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/sale-project-deal-edit-plan.md`
- `docs/api/biz-saleproject-readonly.md`
- `docs/api/biz-saleproject-cost-readonly.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Verification

- `php -l app\controller\biz\SaleProjectController.php`: passed.
- `php -l app\service\biz\SaleProjectService.php`: passed.
- `php -l route\app.php`: passed.
- PowerShell parser checks passed for:
  - `scripts\sale-project-deal-edit-http-smoke.ps1`
  - `scripts\sale-project-delete-http-smoke.ps1`
  - `scripts\sale-project-repeal-http-smoke.ps1`
  - `scripts\sale-project-cancel-http-smoke.ps1`
  - `scripts\biz-payroll-import-http-smoke.ps1`
  - `scripts\frontend-deferred-write-wrapper-smoke.ps1`
  - `scripts\project-progress.ps1`
- `php think route:list | Select-String -Pattern 'biz/saleproject/(deal/edit|delete|repeal|cancel|amount/edit|visibility/edit|add|edit|history/add|special/add)'`: listed the expected routes.
- `Select-String -Path scripts\frontend-deferred-write-wrapper-smoke.ps1 -Pattern '/biz/saleproject/deal/edit|/biz/saleproject/add|/biz/saleproject/edit'`: listed only `/biz/saleproject/add` and `/biz/saleproject/edit`.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: 560 unique endpoints, 578 route paths, 560 covered, 0 missing.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.
- `.\scripts\project-progress.ps1 -Lean`: passed and shows the sale-project deal-edit note plus `.\scripts\sale-project-deal-edit-http-smoke.ps1` in fast commands.
- `git diff --check`: passed with only existing LF/CRLF working-copy warnings.
- `Get-Service -Name MySQL80`: `Stopped` / `Manual`.
- DB-backed HTTP smoke execution is pending because local MySQL `MySQL80` is stopped; this includes `.\scripts\sale-project-deal-edit-http-smoke.ps1` and the refreshed deferred-wrapper smoke.

### Deferred

- Sale-project add/edit, history add, special add, workflow, payment/settlement correction, inventory, delivery, invoice mutation, product-item mutation, file cleanup, Java data-change events, frontend source changes, Java source changes, schema changes, Composer changes, `.env` changes, production data operations, and commits remain deferred.

## 2026-06-18 21:30 +08:00 - api-agent/test-agent - Sale Project Delete

### Completed

- Selected `/biz/saleproject/delete` as the next Java-exposed narrow state route after completing `/biz/saleproject/repeal`.
- Wrote `docs/tasks/sale-project-delete-plan.md` for the Java reference, payload shape, mutation boundary, rollback condition, and smoke-test scope.
- Replaced the controlled-deferred delete controller wrapper with a guarded service call.
- Implemented `SaleProjectService::delete` as Java-compatible sale-project logical delete maintenance:
  - accepts Java/copied-frontend arrays such as `[{ id }]`;
  - also accepts compatible `ids`, `idList`, `projectIds`, `items`, and single `id` payloads;
  - locks and validates all active projects through the existing tenant/data-scope query;
  - rejects missing, unauthorized, or non-`FOLLOW` rows before writing;
  - updates `PROJECT_STATE` to `DISCARD`, sets `DELETE_FLAG = DELETED`, audit fields, and `VERSION`.
- Added `scripts/sale-project-delete-http-smoke.ps1`.
- Removed `/biz/saleproject/delete` from the deferred-wrapper smoke list.

### Modified Files

- `app/controller/biz/SaleProjectController.php`
- `app/service/biz/SaleProjectService.php`
- `scripts/sale-project-delete-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/sale-project-delete-plan.md`
- `docs/api/biz-saleproject-readonly.md`
- `docs/api/biz-saleproject-cost-readonly.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Verification

- `php -l app\controller\biz\SaleProjectController.php`: passed.
- `php -l app\service\biz\SaleProjectService.php`: passed.
- `php -l route\app.php`: passed.
- PowerShell parser checks passed for:
  - `scripts\sale-project-delete-http-smoke.ps1`
  - `scripts\sale-project-repeal-http-smoke.ps1`
  - `scripts\sale-project-cancel-http-smoke.ps1`
  - `scripts\biz-payroll-import-http-smoke.ps1`
  - `scripts\frontend-deferred-write-wrapper-smoke.ps1`
  - `scripts\project-progress.ps1`
- `php think route:list | Select-String -Pattern 'biz/saleproject/(delete|repeal|cancel|amount/edit|visibility/edit|add|edit|deal/edit|history/add|special/add)'`: listed the expected routes.
- `Select-String -Path scripts\frontend-deferred-write-wrapper-smoke.ps1 -Pattern '/biz/saleproject/delete|/biz/saleproject/add|/biz/saleproject/deal/edit'`: listed only `/biz/saleproject/add` and `/biz/saleproject/deal/edit`.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: 560 unique endpoints, 578 route paths, 560 covered, 0 missing.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.
- `.\scripts\project-progress.ps1 -Lean`: passed and shows the sale-project delete note plus `.\scripts\sale-project-delete-http-smoke.ps1` in fast commands.
- `git diff --check`: passed with only existing LF/CRLF working-copy warnings.
- `Get-Service -Name MySQL80`: `Stopped` / `Manual`.
- DB-backed HTTP smoke execution is pending because local MySQL `MySQL80` is stopped; this includes `.\scripts\sale-project-delete-http-smoke.ps1` and the refreshed deferred-wrapper smoke.

### Deferred

- Sale-project add/edit, deal edit, history add, special add, workflow, payment/settlement correction, inventory, delivery, invoice mutation, product-item mutation, file cleanup, Java data-change events, frontend source changes, Java source changes, schema changes, Composer changes, `.env` changes, production data operations, and commits remain deferred.

## 2026-06-18 21:15 +08:00 - api-agent/test-agent - Sale Project Repeal

### Completed

- Selected `/biz/saleproject/repeal` as the next Java-exposed narrow state route after completing `/biz/saleproject/cancel`.
- Wrote `docs/tasks/sale-project-repeal-plan.md` for the Java reference, payload shape, mutation boundary, rollback condition, and smoke-test scope.
- Replaced the controlled-deferred repeal controller wrapper with a guarded service call.
- Implemented `SaleProjectService::repeal` as Java-compatible sale-project discard state maintenance:
  - accepts Java/copied-frontend arrays such as `[{ id, repealContent }]`;
  - also accepts compatible `ids`, `idList`, `projectIds`, `items`, and single `id` payloads;
  - locks and validates all active projects through the existing tenant/data-scope query;
  - rejects missing, unauthorized, or non-`FOLLOW` rows before writing;
  - updates `PROJECT_STATE` to `DISCARD`, writes `REPEAL_CONTENT`, audit fields, and `VERSION`.
- Added `scripts/sale-project-repeal-http-smoke.ps1`.
- Removed `/biz/saleproject/repeal` from the deferred-wrapper smoke list.

### Modified Files

- `app/controller/biz/SaleProjectController.php`
- `app/service/biz/SaleProjectService.php`
- `scripts/sale-project-repeal-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/sale-project-repeal-plan.md`
- `docs/api/biz-saleproject-readonly.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Verification

- `php -l app\controller\biz\SaleProjectController.php`: passed.
- `php -l app\service\biz\SaleProjectService.php`: passed.
- `php -l route\app.php`: passed.
- PowerShell parser checks passed for:
  - `scripts\sale-project-repeal-http-smoke.ps1`
  - `scripts\sale-project-cancel-http-smoke.ps1`
  - `scripts\biz-payroll-import-http-smoke.ps1`
  - `scripts\frontend-deferred-write-wrapper-smoke.ps1`
  - `scripts\project-progress.ps1`
- `php think route:list | Select-String -Pattern 'biz/saleproject/(repeal|cancel|amount/edit|visibility/edit|add|edit|delete|deal/edit|history/add|special/add)'`: listed the expected routes.
- `Select-String -Path scripts\frontend-deferred-write-wrapper-smoke.ps1 -Pattern '/biz/saleproject/repeal|/biz/saleproject/cancel|/biz/saleproject/delete'`: only listed `/biz/saleproject/delete`.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: 560 unique endpoints, 578 route paths, 560 covered, 0 missing.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.
- `git diff --check`: passed with only existing LF/CRLF working-copy warnings.
- `Get-Service -Name MySQL80`: `Stopped` / `Manual`.
- DB-backed HTTP smoke execution is pending because local MySQL `MySQL80` is stopped; this includes `.\scripts\sale-project-repeal-http-smoke.ps1` and the refreshed deferred-wrapper smoke.

### Deferred

- Sale-project add/edit, deal edit, history add, special add, workflow, payment/settlement correction, inventory, delivery, invoice mutation, notification, file cleanup, Java data-change events, frontend source changes, Java source changes, schema changes, Composer changes, `.env` changes, production data operations, and commits remain deferred.

## 2026-06-18 21:00 +08:00 - api-agent/test-agent - Sale Project Cancel

### Completed

- Selected `/biz/saleproject/cancel` after confirming nearby payroll add, return-order add/edit/delete, sale-project invoicing add/edit/delete, collection-receipt CRUD, debit-note CRUD, and leave add are absent or commented out in Java controllers.
- Wrote `docs/tasks/sale-project-cancel-plan.md` for the Java reference, mutation boundary, rollback condition, and smoke-test scope.
- Replaced the controlled-deferred cancel controller wrapper with a guarded service call.
- Implemented `SaleProjectService::cancel` as Java-compatible sale-project status rollback:
  - accepts `id` or `projectId`;
  - locks and validates the active sale project through the existing tenant/data-scope query;
  - rejects projects whose `PROJECT_STATE` is not `WAIT_DELIVER`;
  - updates `PROJECT_STATE` to `FOLLOW`, audit fields, and `VERSION`;
  - logically deletes active `biz_sale_project_invoicing` rows for the same project and tenant.
- Added `scripts/sale-project-cancel-http-smoke.ps1`.
- Removed `/biz/saleproject/cancel` from the deferred-wrapper smoke list.

### Modified Files

- `app/controller/biz/SaleProjectController.php`
- `app/service/biz/SaleProjectService.php`
- `scripts/sale-project-cancel-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/sale-project-cancel-plan.md`
- `docs/api/biz-saleproject-readonly.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Verification

- `php -l app\controller\biz\SaleProjectController.php`: passed.
- `php -l app\service\biz\SaleProjectService.php`: passed.
- `php -l route\app.php`: passed.
- PowerShell parser check for `scripts\sale-project-cancel-http-smoke.ps1`: passed.
- PowerShell parser checks for `scripts\frontend-deferred-write-wrapper-smoke.ps1` and `scripts\project-progress.ps1`: passed.
- `php think route:list | Select-String -Pattern 'biz/saleproject/(cancel|amount/edit|visibility/edit|add|edit|delete|deal/edit|history/add|repeal|special/add)'`: listed the expected routes.
- `Select-String -Path scripts\frontend-deferred-write-wrapper-smoke.ps1 -Pattern '/biz/saleproject/cancel|/biz/saleproject/delete'`: showed only `/biz/saleproject/delete`; cancel is no longer in the deferred list.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 560 unique frontend endpoints, 578 route paths, 560 covered paths, and 0 missing read-like or side-effect-like endpoints.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.
- `git diff --check`: passed; only normal LF/CRLF warnings were emitted.
- `Get-Service -Name MySQL80`: `Stopped` / `Manual`.
- DB-backed HTTP smoke execution is pending because local MySQL `MySQL80` is stopped; this includes `.\scripts\sale-project-cancel-http-smoke.ps1` and the refreshed deferred-wrapper smoke.

### Deferred

- Sale-project add/edit/delete, deal edit, repeal, history add, special add, workflow, payment/settlement correction, inventory, delivery, notification, file cleanup, Java data-change events, frontend source changes, Java source changes, schema changes, Composer changes, `.env` changes, production data operations, and commits remain deferred.

## 2026-06-18 20:45 +08:00 - api-agent/test-agent - Payroll Import

### Completed

- Selected `/biz/bizpayroll/import` as the next Java-exposed deferred wrapper after confirming several nearby wrappers remain commented out or absent in Java controllers.
- Wrote `docs/tasks/biz-payroll-import-plan.md` for the parser, transaction, row-level error, permission, and smoke-test boundary.
- Replaced the controlled-deferred payroll import wrapper with a guarded service call.
- Implemented `BizPayrollService::importExcel` as focused Java-template payroll import:
  - accepts multipart `.xlsx` uploads plus optional `orgId`/`org`;
  - parses the template with built-in ZIP/XML support and no Composer dependency;
  - reads the salary month from row 1 column A;
  - maps data rows after the three header rows to payroll salary fields;
  - matches whitespace-normalized names against active tenant users in the requested organization subtree;
  - inserts one `biz_payroll` row per matched import row;
  - returns Java-style `totalCount`, `successCount`, `errorCount`, and `errorDetail` while committing successful rows.
- Added `scripts/biz-payroll-import-http-smoke.ps1`.
- Removed `/biz/bizpayroll/import` from the deferred-wrapper smoke list and documented `/biz/bizpayroll/add` as the remaining payroll deferred write wrapper.

### Modified Files

- `app/controller/biz/BizPayrollController.php`
- `app/service/biz/BizPayrollService.php`
- `scripts/biz-payroll-import-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/biz-payroll-import-plan.md`
- `docs/api/biz-payroll-readonly.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Verification

- `php -l app\controller\biz\BizPayrollController.php`: passed.
- `php -l app\service\biz\BizPayrollService.php`: passed.
- PowerShell parser check for `scripts\biz-payroll-import-http-smoke.ps1`: passed.
- PowerShell parser check for `scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed.
- PowerShell parser check for `scripts\project-progress.ps1`: passed.
- XLSX parser reflection smoke against `app/resources/biz/payroll/userPayrollTemplate.xlsx`: passed with title `2025年01月工资表`, zero data rows, and salary time `2025-01-01 00:00:00`.
- `Get-Service -Name MySQL80`: `Stopped` / `Manual`.
- DB-backed HTTP smoke execution is pending because local MySQL `MySQL80` is stopped; this includes `.\scripts\biz-payroll-import-http-smoke.ps1` and the refreshed deferred-wrapper smoke.

### Deferred

- Payroll add, EasyExcel-style xlsx export rendering/styling, workflow hooks, Java data-change events, duplicate-month prevention, frontend source changes, Java source changes, schema changes, Composer changes, `.env` changes, production data operations, and commits remain deferred.

## 2026-06-18 20:30 +08:00 - api-agent/test-agent - Sale Project Amount Edit

### Completed

- Selected `/biz/saleproject/amount/edit` as the next Java-exposed deferred wrapper after confirming broader sale-project add/edit/delete/deal/cancel/history/special/workflow routes are higher-risk and should remain deferred.
- Wrote `docs/tasks/sale-project-amount-edit-plan.md` for the Java reference, mutation whitelist, correction order, and smoke-test boundary.
- Replaced the controlled-deferred amount controller wrapper with a guarded service call.
- Implemented `SaleProjectService::editAmount` as Java-compatible sale-project amount maintenance:
  - accepts `id`/`projectId` and `initPrice` input aliases plus optional `remark`;
  - validates non-negative numeric amount input;
  - locks and validates the active sale project through the existing tenant/data-scope query;
  - updates `INIT_PRICE`;
  - recalculates `AMOUNT_COLLECTED`, `PLAY_STATE`, `PROJECT_STATE`, `TOTAL_PRICE`, `TOTAL_REFUND_AMOUNT`, and `TOTAL_RETURN_AMOUNT`;
  - preserves Java's over-collected guard against the pre-recalculation `TOTAL_PRICE`;
  - writes one `sales_project_field_change_log` row for `INIT_PRICE`.
- Added `scripts/sale-project-amount-edit-http-smoke.ps1`.
- Removed `/biz/saleproject/amount/edit` from the deferred-wrapper smoke list and documented the remaining sale-project wrappers as still deferred.

### Modified Files

- `app/controller/biz/SaleProjectController.php`
- `app/service/biz/SaleProjectService.php`
- `scripts/sale-project-amount-edit-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/sale-project-amount-edit-plan.md`
- `docs/api/biz-saleproject-readonly.md`
- `docs/api/biz-saleproject-cost-readonly.md`
- `docs/api/sales-project-field-change-log-readonly.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Verification

- `php -l app\controller\biz\SaleProjectController.php`: passed.
- `php -l app\service\biz\SaleProjectService.php`: passed.
- `php -l route\app.php`: passed.
- PowerShell parser check for `scripts\sale-project-amount-edit-http-smoke.ps1`: passed.
- PowerShell parser check for `scripts\project-progress.ps1`: passed.
- `php think route:list | Select-String -Pattern 'biz/saleproject/(amount/edit|visibility/edit|add|edit|delete|deal/edit|cancel|history/add|repeal|special/add)'`: listed the expected routes.
- `Select-String -Path scripts\frontend-deferred-write-wrapper-smoke.ps1 -Pattern '/biz/saleproject/amount/edit'`: no output.
- `Select-String -Path scripts\frontend-deferred-write-wrapper-smoke.ps1 -Pattern '/biz/saleproject/visibility/edit'`: no output.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 560/560 routed endpoints and 0 missing read-like endpoints.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.
- `git diff --check`: passed with only LF-to-CRLF warnings.
- `Get-Service -Name MySQL80`: `Stopped` / `Manual`.
- DB-backed HTTP smoke execution is pending because local MySQL `MySQL80` is stopped; this includes `.\scripts\sale-project-amount-edit-http-smoke.ps1` and the refreshed deferred-wrapper smoke.

### Deferred

- Sale-project add/edit/delete, deal edit, repeal, history add, special add, workflow, attachment, notification, inventory, delivery, invoice side effects beyond cancel's invoicing logical delete, customer side effects, Java data-change events, frontend source changes, Java source changes, schema changes, Composer changes, `.env` changes, production data operations, and commits remain deferred. Cancel is covered separately by the sale-project cancel slice.

## 2026-06-18 20:00 +08:00 - api-agent/test-agent - Sale Project Visibility Edit

### Completed

- Selected `/biz/saleproject/visibility/edit` as the next Java-exposed deferred wrapper after confirming broader sale-project add/edit/delete/state/finance/workflow routes are higher-risk and should remain deferred.
- Wrote `docs/tasks/sale-project-visibility-edit-plan.md` for the Java reference, copied-frontend private-toggle compatibility, mutation whitelist, and smoke-test boundary.
- Replaced the controlled-deferred visibility controller wrapper with a guarded service call.
- Implemented `SaleProjectService::editVisibility` as narrow Java-compatible field maintenance:
  - accepts `projectId`/`id` and `visibilityState`/`visibility` input aliases;
  - validates `PUBLIC` and `PRIVATE`;
  - requires `specimenCategory` for `PUBLIC`;
  - preserves existing specimen fields for copied frontend private toggles that omit them;
  - validates active sale-project access through the existing tenant/data-scope query;
  - updates only visibility/specimen/audit/version fields.
- Added `scripts/sale-project-visibility-edit-http-smoke.ps1`.
- Removed `/biz/saleproject/visibility/edit` from the deferred-wrapper smoke list and documented the remaining sale-project wrappers as still deferred.

### Modified Files

- `app/controller/biz/SaleProjectController.php`
- `app/service/biz/SaleProjectService.php`
- `scripts/sale-project-visibility-edit-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/sale-project-visibility-edit-plan.md`
- `docs/api/biz-saleproject-readonly.md`
- `docs/api/biz-saleproject-cost-readonly.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Verification

- `php -l app\controller\biz\SaleProjectController.php`: passed.
- `php -l app\service\biz\SaleProjectService.php`: passed.
- `php -l route\app.php`: passed.
- PowerShell parser check for `scripts\sale-project-visibility-edit-http-smoke.ps1`: passed.
- `php think route:list | Select-String -Pattern 'biz/saleproject/(visibility/edit|add|edit|delete|amount/edit|deal/edit|cancel|history/add|repeal|special/add)'`: listed the expected routes.
- `Select-String -Path scripts\frontend-deferred-write-wrapper-smoke.ps1 -Pattern '/biz/saleproject/visibility/edit'`: no output.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 560/560 routed endpoints and 0 missing read-like endpoints.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.
- `git diff --check`: passed with only LF-to-CRLF warnings.
- DB-backed HTTP smoke execution is pending because local MySQL `MySQL80` is stopped; this includes `.\scripts\sale-project-visibility-edit-http-smoke.ps1` and the refreshed deferred-wrapper smoke.

### Deferred

- Sale-project add/edit/delete, amount edit, deal edit, repeal, history add, special add, project-state changes beyond cancel, play-state, finance, invoice side effects beyond cancel's invoicing logical delete, inventory, delivery, workflow, attachment, notification, change-log, customer side effects, Java data-change events, frontend source changes, Java source changes, schema changes, Composer changes, `.env` changes, production data operations, and commits remain deferred. Cancel is covered separately by the sale-project cancel slice.

## 2026-06-18 19:30 +08:00 - api-agent/test-agent - Dev Job Action Status

### Completed

- Selected `/dev/job/stopJob`, `/dev/job/runJob`, and `/dev/job/runJobNow` as the next Java-exposed deferred wrapper group after confirming the Java controller and service behavior.
- Wrote `docs/tasks/dev-job-actions-plan.md` for the status transition, scheduler exclusion, and smoke-test boundary.
- Replaced the three dev-job controlled-deferred controller wrappers with guarded service calls.
- Implemented narrow `JobService` action-status compatibility:
  - validates Java-style `{ id }` input;
  - rejects missing or inactive job rows;
  - rejects `stopJob` when the job is already `STOPPED`;
  - rejects `runJob` when the job is already `RUNNING`;
  - updates stopped `runJobNow` rows to `RUNNING`;
  - does not register/remove scheduler jobs or execute task classes.
- Extended `scripts/dev-job-write-http-smoke.ps1` to verify no-token, missing-id, repeated state guard, status readback, and `runJobNow` transitions.
- Removed `/dev/job/stopJob`, `/dev/job/runJob`, and `/dev/job/runJobNow` from the deferred-wrapper smoke list.

### Modified Files

- `app/controller/dev/JobController.php`
- `app/service/dev/JobService.php`
- `scripts/dev-job-write-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `docs/tasks/dev-job-actions-plan.md`
- `docs/api/dev-job-readonly-compat.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Verification

- `php -l app\controller\dev\JobController.php`: passed.
- `php -l app\service\dev\JobService.php`: passed.
- `php -l route\app.php`: passed.
- PowerShell parser check for `scripts\dev-job-write-http-smoke.ps1`: passed.
- `php think route:list | Select-String -Pattern 'dev/job/(stopJob|runJob|runJobNow|add|edit|delete|page|detail|getActionClass)'`: listed the expected routes.
- `Select-String -Path scripts\frontend-deferred-write-wrapper-smoke.ps1 -Pattern '/dev/job/(stopJob|runJob|runJobNow)'`: no output.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 560/560 routed endpoints and 0 missing read-like endpoints.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.
- `git diff --check`: passed with only LF-to-CRLF warnings.
- DB-backed HTTP smoke execution is pending because local MySQL `MySQL80` is stopped.

### Deferred

- Real scheduler registration/removal, scheduler lifecycle, task-class execution, full Java bean validation, provider calls, cache invalidation, frontend source changes, Java source changes, schema changes, Composer changes, `.env` changes, production data operations, and commits remain deferred.

## 2026-06-18 19:00 +08:00 - api-agent/test-agent - Payroll Generate Add

### Completed

- Selected `/biz/bizpayroll/generate/add` as the next Java-exposed deferred wrapper after confirming nearby payroll/HR candidates: payroll add is not a Java controller route, payroll import is parsing-heavy, and leave add is commented out in Java.
- Wrote `docs/tasks/biz-payroll-generate-add-plan.md` for the transaction, permission, aggregation, formula, rollback, and smoke-test boundary.
- Replaced the payroll generate controlled-deferred controller wrapper with a guarded service call.
- Implemented `BizPayrollService::generate` as Java-compatible payroll generation:
  - validates selected users, `salaryTime`, and non-negative `socialSecurity`;
  - checks selected users under tenant/data-scope/current-user guard rules;
  - initializes one payroll row per selected user from `sys_user.BASIC_SALARY`;
  - aggregates current-month deal-state sale projects into `TRANSACTION_VOLUME`;
  - aggregates current-month `PROJECT_PLAY` payment records and paid sale projects into current/prior received amounts;
  - aggregates leave-of-absence rows with Java-compatible cross-month overlap and 12:00 half-day handling;
  - calculates `BASE_AMOUNT`, `VACATION_SUB_AMOUNT`, `PAYABLE_AMOUNT`, and `ACTUAL_AMOUNT`;
  - inserts generated payroll rows in one transaction and preserves Java's duplicate-month behavior.
- Added `scripts/biz-payroll-generate-add-http-smoke.ps1`.
- Removed `/biz/bizpayroll/generate/add` from the deferred-wrapper smoke list.

### Modified Files

- `app/controller/biz/BizPayrollController.php`
- `app/service/biz/BizPayrollService.php`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/biz-payroll-generate-add-http-smoke.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/biz-payroll-generate-add-plan.md`
- `docs/api/biz-payroll-readonly.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Verification

- `php -l app\controller\biz\BizPayrollController.php`: passed.
- `php -l app\service\biz\BizPayrollService.php`: passed.
- PowerShell parser check for `scripts\biz-payroll-generate-add-http-smoke.ps1`: passed.
- DB-backed HTTP smoke execution is pending because local MySQL `MySQL80` is stopped.

### Deferred

- Payroll add/import, EasyExcel-style xlsx rendering, workflow hooks, Java data-change events, duplicate-month prevention, frontend source changes, Java source changes, schema changes, Composer changes, `.env` changes, production data operations, and commits remain deferred.

## 2026-06-18 18:30 +08:00 - api-agent/test-agent - Purchase Order Warehouse Add

### Completed

- Selected `/biz/bizpurchaseorder/warehouse/add` as the next Java-exposed deferred write slice after confirming nearby candidates such as inventory delete, settlement-account delete, and payment/expenditure add/delete are not active Java routes.
- Wrote `docs/tasks/purchase-order-warehouse-add-plan.md` for the transaction, permission, rollback, inventory/delivery mutation, and smoke-test boundary.
- Replaced the batch warehouse controlled-deferred controller wrapper with a guarded service call.
- Refactored purchase-order stock-in into a shared locked-order helper used by both one-add and batch add.
- Implemented `PurchaseOrderService::warehouseAdd` as Java-compatible completed-order batch stock-in behavior:
  - validates `warehousesId`;
  - selects active purchase orders with `SETTLEMENT_STATUS = COMPLETED` and `STORAGE_STATUS = NOT_IN_WAREHOUSE`;
  - applies tenant and Java-style data-scope/create-user visibility rules;
  - locks selected orders and active purchase-order items;
  - validates order/warehouse write scope and referenced products;
  - inserts `IN` delivery rows with `Process_sys`, `Process_procure_in_warehouse`, and `OBJECT_ID = orderId`;
  - increases or creates inventory rows for stocked products;
  - marks processed purchase-order items and orders `IN_WAREHOUSE` and increments versions in the same transaction;
  - returns success with `count = 0` when no eligible orders are visible.
- Added `scripts/purchase-order-warehouse-add-http-smoke.ps1`.
- Removed `/biz/bizpurchaseorder/warehouse/add` from the deferred-wrapper smoke list.

### Modified Files

- `app/controller/biz/PurchaseOrderController.php`
- `app/service/biz/PurchaseOrderService.php`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/purchase-order-warehouse-add-http-smoke.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/purchase-order-warehouse-add-plan.md`
- `docs/api/biz-purchase-order-readonly-compat.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Verification

- `php -l app\controller\biz\PurchaseOrderController.php`: passed.
- `php -l app\service\biz\PurchaseOrderService.php`: passed.
- `php -l route\app.php`: passed.
- PowerShell syntax check for `scripts\purchase-order-warehouse-add-http-smoke.ps1`: passed.
- `php think route:list | Select-String -Pattern 'biz/bizpurchaseorder/(warehouse/add|warehouse/one/add|add|delete|cancel|edit|audit/edit|page|detail)'`: listed the expected routes.
- Deferred-wrapper smoke no longer contains `/biz/bizpurchaseorder/warehouse/add`.

### Blocked Verification

- `.\scripts\purchase-order-warehouse-add-http-smoke.ps1`: DB-backed execution is pending because MySQL `MySQL80` is stopped.
- `Get-Service MySQL80` currently reports `Stopped`.

### Still Deferred

- Purchase-order add/delete, purchase-order item creation/deletion, expenditure creation, settlement-account statements, workflow hooks, Java data-change event publishing, frontend source changes, Java source changes, database schema changes, `.env` changes, production data operations, and commits remain out of scope.

## 2026-06-18 18:00 +08:00 - api-agent/test-agent - Purchase Order Warehouse One Add

### Completed

- Selected `/biz/bizpurchaseorder/warehouse/one/add` as the next Java-exposed deferred write slice.
- Wrote `docs/tasks/purchase-order-warehouse-one-add-plan.md` for the transaction, permission, rollback, inventory/delivery mutation, and smoke-test boundary.
- Replaced the one-add controlled-deferred controller wrapper with a guarded service call.
- Implemented `PurchaseOrderService::warehouseOneAdd` as Java-compatible single-order purchase stock-in behavior:
  - validates `orderId`, `warehousesId`, and optional `remark`;
  - locks the active purchase order and requires `STORAGE_STATUS = NOT_IN_WAREHOUSE`;
  - validates order/warehouse write scope and referenced products;
  - locks purchase-order items and rejects already-warehoused or non-positive quantity rows;
  - inserts one `IN` delivery row per item with `Process_sys`, `Process_procure_in_warehouse`, and `OBJECT_ID = orderId`;
  - increases or creates inventory rows for the stocked products;
  - marks purchase-order items and the order `IN_WAREHOUSE` and increments versions in the same transaction.
- Added `scripts/purchase-order-warehouse-one-add-http-smoke.ps1`.
- Removed `/biz/bizpurchaseorder/warehouse/one/add` from the deferred-wrapper smoke list.
- Adjusted delivery insert defaults so omitted `remark` and missing operator fallback do not write null into non-null delivery columns.

### Modified Files

- `app/controller/biz/PurchaseOrderController.php`
- `app/service/biz/PurchaseOrderService.php`
- `app/service/biz/DeliveryRecordService.php`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/purchase-order-warehouse-one-add-http-smoke.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/purchase-order-warehouse-one-add-plan.md`
- `docs/api/biz-purchase-order-readonly-compat.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Verification

- `php -l app\controller\biz\PurchaseOrderController.php`: passed.
- `php -l app\service\biz\PurchaseOrderService.php`: passed.
- `php -l route\app.php`: passed.
- PowerShell syntax check for `scripts\purchase-order-warehouse-one-add-http-smoke.ps1`: passed.
- `php think route:list | Select-String -Pattern 'biz/bizpurchaseorder/(warehouse/one/add|warehouse/add|add|delete|cancel|edit|audit/edit|page|detail)'`: listed the expected routes.
- Deferred-wrapper smoke no longer contains `/biz/bizpurchaseorder/warehouse/one/add`.

### Blocked Verification

- `.\scripts\purchase-order-warehouse-one-add-http-smoke.ps1`: DB-backed execution is pending because MySQL `MySQL80` is stopped and failed to start in this run.
- `Get-Service MySQL80` currently reports `Stopped`.

### Still Deferred

- Purchase-order add/delete, purchase-order item creation/deletion, expenditure creation, settlement-account statements, workflow hooks, Java data-change event publishing, frontend source changes, Java source changes, database schema changes, `.env` changes, production data operations, and commits remain out of scope. Batch `/biz/bizpurchaseorder/warehouse/add` is covered separately as a narrow completed-order stock-in slice.

## 2026-06-18 08:38 +08:00 - api-agent/test-agent - Delivery Record Add Stocktake

### Completed

- Selected `/biz/warehouses/delivery/add` as the next Java-exposed deferred write slice.
- Wrote `docs/tasks/delivery-record-add-plan.md` for the transaction, permission, rollback, inventory mutation, and smoke-test boundary.
- Replaced the delivery add controlled-deferred controller wrapper with a guarded service call.
- Implemented `DeliveryRecordService::add` as Java-compatible system stocktake behavior:
  - validates `warehousesId`, `productId`, non-negative target `amount`, and `deliveryTime`;
  - locks the active warehouse/product inventory row;
  - validates active enabled product and conservative write scope;
  - computes target stock minus current stock;
  - writes one `IN` or `OUT` `delivery_record` row with `Process_sys` only for non-zero movement;
  - updates `inventory.CURRENT_COUNT` to the submitted target and increments `VERSION`;
  - treats equal target/current counts as a no-movement inventory refresh.
- Added `scripts/delivery-record-add-http-smoke.ps1`.
- Removed `/biz/warehouses/delivery/add` from the deferred-wrapper smoke list.

### Modified Files

- `app/controller/biz/DeliveryRecordController.php`
- `app/service/biz/DeliveryRecordService.php`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/delivery-record-add-http-smoke.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/delivery-record-add-plan.md`
- `docs/api/biz-delivery-record-readonly-compat.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Verification

- `php -l app\controller\biz\DeliveryRecordController.php`: passed.
- `php -l app\service\biz\DeliveryRecordService.php`: passed.
- `php -l route\app.php`: passed.
- PowerShell syntax check for `scripts\delivery-record-add-http-smoke.ps1`: passed.
- `php think route:list | Select-String -Pattern 'biz/warehouses/delivery/(add|page|detail|exportOtherCompanyRecordsList)'`: listed the expected routes.

### Blocked Verification

- `.\scripts\web-ready.ps1`: failed because backend TCP `127.0.0.1:82` and frontend TCP `127.0.0.1:83` are unavailable.
- `.\scripts\delivery-record-add-http-smoke.ps1`: blocked before HTTP execution because MySQL `127.0.0.1:3306` refused the connection.
- `Get-Service MySQL80` reported `Stopped`; `Start-Service MySQL80` failed and the service remained stopped.

### Still Deferred

- Delivery edit/delete, inventory delete, purchase-order add/delete, batch purchase-order warehouse stock-in, sale-project delivery, return stock-in, workflow hooks, Java data-change event publishing, frontend source changes, Java source changes, database schema changes, `.env` changes, production data operations, and commits remain out of scope.

## 2026-06-17 16:39 +08:00 - api-agent/test-agent - Purchase Order Audit Edit

### Completed

- Selected `/biz/bizpurchaseorder/audit/edit` from the remaining controlled-deferred wrapper list.
- Wrote `docs/tasks/purchase-order-audit-edit-plan.md` for the transaction, permission, rollback, side-effect, and smoke-test boundary.
- Replaced the deferred response with narrow Java-compatible audit-remediation purchase-order edit behavior.
- The endpoint now requires `id` and nonempty unique `productList`, locks the active order in the current tenant, checks admin/data-scope/create-user write scope, intentionally skips normal edit's completed-settlement and goods-expenditure guards, requires submitted item ids to be active rows belonging to the same order, updates only `biz_purchase_order.AMOUNT`, existing purchase-order item amount/cost fields, audit fields, and `VERSION`.
- Added `scripts/purchase-order-audit-edit-http-smoke.ps1`.
- Removed `/biz/bizpurchaseorder/audit/edit` from the deferred-wrapper smoke list.
- Did not implement purchase-order add/delete, batch warehouse stock-in, purchase-order item creation/deletion, inventory movement outside one-add, delivery records outside one-add, expenditure creation, settlement-account statements, workflow hooks, Java data-change events, frontend source changes, Java source changes, database schema changes, Composer/npm changes, `.env` changes, production data operations, or commits.

### Modified Files

- `app/controller/biz/PurchaseOrderController.php`
- `app/service/biz/PurchaseOrderService.php`
- `scripts/purchase-order-audit-edit-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/purchase-order-audit-edit-plan.md`
- `docs/tasks/purchase-order-edit-plan.md`
- `docs/api/biz-purchase-order-readonly-compat.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\biz\PurchaseOrderController.php`: passed.
- `php -l app\service\biz\PurchaseOrderService.php`: passed.
- `php -l route\app.php`: passed.
- PowerShell syntax check for `scripts\purchase-order-audit-edit-http-smoke.ps1`: passed.
- `php think route:list | Select-String -Pattern 'biz/bizpurchaseorder/(audit/edit|edit|add|delete|warehouse/add|warehouse/one/add|cancel|page|detail)'`: listed the expected purchase-order routes.
- `.\scripts\web-ready.ps1`: passed.
- `.\scripts\purchase-order-audit-edit-http-smoke.ps1`: passed.
- `.\scripts\purchase-order-edit-http-smoke.ps1`: passed.
- `.\scripts\purchase-order-cancel-http-smoke.ps1`: passed.
- `.\scripts\purchase-order-read-http-smoke.ps1`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed for 65 authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, and 14 representative no-token checks.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 560/560 frontend endpoints covered by route path and zero missing reads.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.
- `.\scripts\project-progress.ps1 -Lean`: passed and shows dashboard last updated at `2026-06-17 16:39 +08:00`.
- `git diff --check`: passed with existing LF/CRLF warnings only.

### Current Issues

- `/biz/bizpurchaseorder/audit/edit` is audit-remediation edit only; it does not create purchase orders, create/delete item rows, audit workflow state, batch warehouse stock-in, move inventory outside one-add, create delivery rows outside one-add, create expenditure records, update settlement-account statements, start workflow, or publish Java data-change events.
- DB row-count smoke scripts that create temporary finance/purchase/inventory rows should keep running serially when count stability is asserted.

### Next Plan

- Continue choosing remaining controlled-deferred wrapper groups only after a module-specific transaction, permission, rollback, side-effect, and smoke-test plan.
- Do not commit unless the user explicitly asks for a commit.

## 2026-06-17 16:03 +08:00 - api-agent/test-agent - Purchase Order Edit

### Completed

- Selected `/biz/bizpurchaseorder/edit` from the remaining controlled-deferred wrapper list.
- Wrote `docs/tasks/purchase-order-edit-plan.md` for the transaction, permission, rollback, side-effect, and smoke-test boundary.
- Replaced the deferred response with narrow Java-compatible normal purchase-order edit behavior.
- The endpoint now requires `id` and nonempty unique `productList`, locks the active order in the current tenant, checks admin/data-scope/create-user write scope, rejects completed settlement orders, rejects orders that already have goods expenditure rows, requires submitted item ids to be active rows belonging to the same order, updates only `biz_purchase_order.AMOUNT`, existing purchase-order item amount/cost fields, audit fields, and `VERSION`.
- Added `scripts/purchase-order-edit-http-smoke.ps1`.
- Removed `/biz/bizpurchaseorder/edit` from the deferred-wrapper smoke list.
- Did not implement purchase-order add/delete, batch warehouse stock-in, purchase-order item creation/deletion, inventory movement outside one-add, delivery records outside one-add, expenditure creation, settlement-account statements, workflow hooks, Java data-change events, frontend source changes, Java source changes, database schema changes, Composer/npm changes, `.env` changes, production data operations, or commits. Audit edit and single-order warehouse one add are now covered by later narrow slices.

### Modified Files

- `app/controller/biz/PurchaseOrderController.php`
- `app/service/biz/PurchaseOrderService.php`
- `scripts/purchase-order-edit-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/purchase-order-edit-plan.md`
- `docs/api/biz-purchase-order-readonly-compat.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\biz\PurchaseOrderController.php`: passed.
- `php -l app\service\biz\PurchaseOrderService.php`: passed.
- `php -l route\app.php`: passed.
- PowerShell syntax check for `scripts\purchase-order-edit-http-smoke.ps1`: passed.
- `php think route:list | Select-String -Pattern 'biz/bizpurchaseorder/(edit|audit/edit|add|delete|warehouse/add|warehouse/one/add|cancel|page|detail)'`: listed the expected purchase-order routes.
- `.\scripts\web-ready.ps1`: passed.
- `.\scripts\purchase-order-edit-http-smoke.ps1`: passed.
- `.\scripts\purchase-order-cancel-http-smoke.ps1`: passed.
- `.\scripts\purchase-order-read-http-smoke.ps1`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed for 66 authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, and 14 representative no-token checks.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 560/560 frontend endpoints covered by route path and zero missing reads.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.
- `.\scripts\project-progress.ps1 -Lean`: passed and shows dashboard last updated at `2026-06-17 16:03 +08:00`.
- `git diff --check`: passed with existing LF/CRLF warnings only.

### Current Issues

- `/biz/bizpurchaseorder/edit` is normal-order edit only; it does not create purchase orders, create/delete item rows, audit orders, batch warehouse stock-in, move inventory outside one-add, create delivery rows outside one-add, create expenditure records, update settlement-account statements, start workflow, or publish Java data-change events.
- DB row-count smoke scripts that create temporary finance/purchase/inventory rows should keep running serially when count stability is asserted.

### Next Plan

- Continue choosing remaining controlled-deferred wrapper groups only after a module-specific transaction, permission, rollback, side-effect, and smoke-test plan.
- Do not commit unless the user explicitly asks for a commit.

## 2026-06-17 13:48 +08:00 - api-agent/test-agent - Inventory Add Registration

### Completed

- Selected `/biz/inventory/add` from the remaining controlled-deferred wrapper list.
- Wrote `docs/tasks/inventory-add-plan.md` for the transaction, permission, rollback, side-effect, and smoke-test boundary.
- Replaced the deferred response with narrow warehouse/product inventory registration.
- The endpoint now requires `warehousesId` and nonempty unique `productIds`, locks the active warehouse in the current tenant, checks conservative warehouse/product write scope, validates active enabled products, derives inventory tenant from the warehouse, inserts missing `inventory` rows with `CURRENT_COUNT = 0`, preserves existing active row counts while refreshing audit fields and `VERSION`, and rejects deleted unique-key conflicts.
- Added `scripts/inventory-add-http-smoke.ps1`.
- Removed `/biz/inventory/add` from the deferred-wrapper smoke list.
- Did not implement inventory delete, stock movement, delivery records, purchase-order warehouse entry, workflow hooks, Java data-change events, frontend source changes, Java source changes, database schema changes, Composer/npm changes, `.env` changes, production data operations, or commits.

### Modified Files

- `app/controller/biz/InventoryController.php`
- `app/service/biz/InventoryService.php`
- `scripts/inventory-add-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/inventory-add-plan.md`
- `docs/api/biz-inventory-readonly-compat.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\biz\InventoryController.php`: passed.
- `php -l app\service\biz\InventoryService.php`: passed.
- `php -l route\app.php`: passed.
- PowerShell syntax check for `scripts\inventory-add-http-smoke.ps1`: passed.
- `php think route:list | Select-String -Pattern 'biz/inventory/(add|delete|page|list|detail)'`: listed the expected inventory routes.
- `.\scripts\web-ready.ps1`: passed.
- `.\scripts\inventory-add-http-smoke.ps1`: passed.
- `.\scripts\inventory-delivery-read-http-smoke.ps1`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed for 67 authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, and 14 representative no-token checks.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 560/560 frontend endpoints covered by route path and zero missing reads.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.
- `.\scripts\project-progress.ps1 -Lean`: passed and shows dashboard last updated at `2026-06-17 13:48 +08:00`.
- `git diff --check`: passed with existing LF/CRLF warnings only.

### Current Issues

- `/biz/inventory/add` is registration only; it does not move stock, create delivery rows, perform purchase-order warehouse entry, publish Java data-change events, or delete inventory.
- DB row-count smoke scripts that create temporary finance/purchase/inventory rows should keep running serially when count stability is asserted.

### Next Plan

- Continue choosing remaining controlled-deferred wrapper groups only after a module-specific transaction, permission, rollback, side-effect, and smoke-test plan.
- Do not commit unless the user explicitly asks for a commit.

## 2026-06-17 13:05 +08:00 - api-agent/test-agent - Purchase Order Cancel

### Completed

- Selected `/biz/bizpurchaseorder/cancel` from the remaining controlled-deferred wrapper list.
- Replaced the deferred response with a narrow purchase-order status marker.
- The endpoint now requires `id`, locks the active order in the current tenant, checks admin/data-scope/create-user write scope, rejects completed settlement and in-warehouse orders, updates only `biz_purchase_order.SETTLEMENT_STATUS = Canceled`, `UPDATE_TIME`, `UPDATE_USER`, and `VERSION`, and returns a Java-style success envelope.
- Added `scripts/purchase-order-cancel-http-smoke.ps1`.
- Removed `/biz/bizpurchaseorder/cancel` from the deferred-wrapper smoke list.
- Did not implement purchase-order add/delete, batch warehouse stock-in, inventory movement outside one-add, expenditure creation, workflow hooks, Java data-change events, frontend source changes, Java source changes, database schema changes, Composer/npm changes, `.env` changes, production data operations, or commits. Normal purchase-order edit, audit edit, and single-order warehouse one add are now covered by later narrow slices.

### Modified Files

- `app/controller/biz/PurchaseOrderController.php`
- `app/service/biz/PurchaseOrderService.php`
- `scripts/purchase-order-cancel-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-progress.ps1`
- `docs/api/biz-purchase-order-readonly-compat.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\biz\PurchaseOrderController.php`: passed.
- `php -l app\service\biz\PurchaseOrderService.php`: passed.
- `php -l route\app.php`: passed.
- PowerShell syntax check for `scripts\purchase-order-cancel-http-smoke.ps1`: passed.
- `php think route:list | Select-String -Pattern 'biz/bizpurchaseorder/(page|detail/list|list|detail|cancel|add|edit|audit/edit|warehouse/add|warehouse/one/add|delete)'`: listed the expected purchase-order routes.
- `.\scripts\web-ready.ps1`: passed.
- `.\scripts\purchase-order-cancel-http-smoke.ps1`: passed.
- `.\scripts\purchase-order-read-http-smoke.ps1`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed for 68 authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, and 14 representative no-token checks.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 560/560 frontend endpoints covered by route path and zero missing reads.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.
- `.\scripts\project-progress.ps1 -Lean`: passed.
- `git diff --check`: passed with existing LF/CRLF warnings only.

### Current Issues

- Purchase-order cancel is implemented as a status marker, and normal purchase-order edit, audit edit, single-order warehouse one add, batch warehouse stock-in, and direct purchase-order add/delete are covered by later narrow slices.
- Inventory movement outside the covered warehouse/delivery paths, expenditure creation outside the approved finance routes, workflow hooks outside the approved procurement paths, and Java data-change events remain deferred.
- Do not run DB row-count smoke scripts that insert temporary purchase/finance rows in parallel; run them serially when count stability is part of the assertion.

### Next Plan

- Finish the pending verification commands above, then continue choosing remaining controlled-deferred wrapper groups only after a module-specific transaction, permission, rollback, side-effect, and smoke-test plan.
- Do not commit unless the user explicitly asks for a commit.

## 2026-06-17 11:01 +08:00 - api-agent/test-agent - Settlement Account Transfer Add

### Completed

- Selected `/biz/settlementaccount/transfer/add` from the remaining controlled-deferred wrapper list.
- Wrote `docs/tasks/settlement-account-transfer-add-plan.md` before replacing behavior.
- Replaced the deferred response with narrow Java-compatible account transfer creation.
- The endpoint now requires `expensesAccountId`, `revenueAccountId`, `payerTime`, and positive `amount`, rejects same-account transfers, locks both accounts in stable id order, writes EXPEND and INCOME settlement statements with `Process_sys`, writes linked expenditure/payment records with fixed `dealings` category, moves the amount between the two settlement accounts, and preserves tenant/user/org links.
- Added `scripts/settlement-account-transfer-add-http-smoke.ps1`.
- Removed `/biz/settlementaccount/transfer/add` from the deferred-wrapper smoke list.
- Did not implement settlement-account delete, Java data-change events, workflow hooks, collection-receipt/debit-note propagation, Java source changes, database schema changes, Composer/npm/frontend source changes, production data operations, or commits.

### Modified Files

- `app/controller/biz/SettlementAccountController.php`
- `app/service/biz/SettlementAccountService.php`
- `scripts/settlement-account-transfer-add-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/settlement-account-transfer-add-plan.md`
- `docs/api/biz-settlement-account-readonly-compat.md`
- `docs/api/biz-settlement-account-payment-readonly.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\biz\SettlementAccountController.php`: passed.
- `php -l app\service\biz\SettlementAccountService.php`: passed.
- `php -l route\app.php`: passed.
- PowerShell syntax check for `scripts\settlement-account-transfer-add-http-smoke.ps1`: passed.
- `php think route:list | Select-String -Pattern 'biz/settlementaccount/(transfer/add|expenses/add|payment/add|delete|page|list|detail)'`: listed the expected settlement-account routes.
- `.\scripts\web-ready.ps1`: passed.
- `.\scripts\settlement-account-transfer-add-http-smoke.ps1`: passed.
- `.\scripts\settlement-account-expenses-add-http-smoke.ps1`: passed.
- `.\scripts\settlement-account-payment-add-http-smoke.ps1`: passed.
- `.\scripts\settlement-account-read-http-smoke.ps1`: passed.
- `.\scripts\settlement-account-payment-read-http-smoke.ps1`: passed.
- `.\scripts\finance-read-http-smoke.ps1`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed for 72 authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, and 16 representative no-token checks.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 560/560 frontend endpoints covered by route path and zero missing reads.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.
- `.\scripts\project-progress.ps1 -Lean`: passed.
- `git diff --check`: passed with existing LF/CRLF warnings only.

### Current Issues

- Settlement-account quick income, quick expense, and transfer are now implemented, but delete, Java data-change events, workflow hooks, and receipt/debit propagation remain deferred.
- Do not run DB row-count smoke scripts that insert temporary finance rows in parallel; run them serially when count stability is part of the assertion.

### Next Plan

- Continue choosing remaining controlled-deferred wrapper groups only after a module-specific transaction, permission, rollback, side-effect, and smoke-test plan.
- Do not commit unless the user explicitly asks for a commit.

## 2026-06-17 10:29 +08:00 - api-agent/test-agent - Settlement Account Expenses Add

### Completed

- Selected `/biz/settlementaccount/expenses/add` from the remaining controlled-deferred wrapper list.
- Wrote `docs/tasks/settlement-account-expenses-add-plan.md` before replacing behavior.
- Replaced the deferred response with narrow Java-compatible quick-expense creation.
- The endpoint now requires `targetId`, `settlementCategory`, `payer`, `payerTime`, and positive `amount`, locks the target settlement account, writes an `EXPEND` settlement statement with `Process_sys`, writes a linked expenditure record, decrements the account balance, and preserves tenant/user/org links.
- Added `scripts/settlement-account-expenses-add-http-smoke.ps1`.
- Removed `/biz/settlementaccount/expenses/add` from the deferred-wrapper smoke list.
- Did not implement settlement-account transfer add, delete, Java data-change events, workflow hooks, collection-receipt settlement propagation, Java source changes, database schema changes, Composer/npm/frontend source changes, production data operations, or commits.

### Modified Files

- `app/controller/biz/SettlementAccountController.php`
- `app/service/biz/SettlementAccountService.php`
- `scripts/settlement-account-expenses-add-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/settlement-account-expenses-add-plan.md`
- `docs/api/biz-settlement-account-readonly-compat.md`
- `docs/api/biz-settlement-account-payment-readonly.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\biz\SettlementAccountController.php`: passed.
- `php -l app\service\biz\SettlementAccountService.php`: passed.
- `php -l route\app.php`: passed.
- PowerShell syntax check for `scripts\settlement-account-expenses-add-http-smoke.ps1`: passed.
- `php think route:list | Select-String -Pattern 'biz/settlementaccount/(expenses/add|payment/add|transfer/add|delete|page|list|detail)'`: listed the expected settlement-account routes.
- `.\scripts\web-ready.ps1`: passed.
- `.\scripts\settlement-account-expenses-add-http-smoke.ps1`: passed.
- `.\scripts\settlement-account-payment-add-http-smoke.ps1`: passed.
- `.\scripts\settlement-account-read-http-smoke.ps1`: passed.
- `.\scripts\settlement-account-payment-read-http-smoke.ps1`: passed.
- `.\scripts\finance-read-http-smoke.ps1`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed for 73 authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, and 16 representative no-token checks.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 560/560 frontend endpoints covered by route path and zero missing reads.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.
- `.\scripts\project-progress.ps1 -Lean`: passed.
- `git diff --check`: passed with existing LF/CRLF warnings only.

### Current Issues

- Settlement-account quick income and quick expense are now implemented, but transfer, delete, Java data-change events, workflow hooks, and receipt-settlement propagation remain deferred.
- Do not run DB row-count smoke scripts that insert temporary finance rows in parallel; run them serially when count stability is part of the assertion.

### Next Plan

- Continue choosing remaining controlled-deferred wrapper groups only after a module-specific transaction, permission, rollback, side-effect, and smoke-test plan.
- Do not commit unless the user explicitly asks for a commit.

## 2026-06-17 09:55 +08:00 - api-agent/test-agent - Settlement Account Payment Add

### Completed

- Selected `/biz/settlementaccount/payment/add` from the remaining controlled-deferred wrapper list.
- Wrote `docs/tasks/settlement-account-payment-add-plan.md` before replacing behavior.
- Replaced the deferred response with narrow Java-compatible quick-income creation.
- The endpoint now requires `targetId`, `settlementCategory`, `payer`, `payerTime`, and positive `amount`, locks the target settlement account, writes an `INCOME` settlement statement with `Process_sys`, writes a linked payment record, increments the account balance, and preserves tenant/user/org links.
- Added `scripts/settlement-account-payment-add-http-smoke.ps1`.
- Removed `/biz/settlementaccount/payment/add` from the deferred-wrapper smoke list.
- Did not implement settlement-account expenses add, transfer add, delete, Java data-change events, workflow hooks, Java source changes, database schema changes, Composer/npm/frontend source changes, production data operations, or commits.

### Modified Files

- `app/controller/biz/SettlementAccountController.php`
- `app/service/biz/SettlementAccountService.php`
- `scripts/settlement-account-payment-add-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/settlement-account-payment-add-plan.md`
- `docs/api/biz-settlement-account-readonly-compat.md`
- `docs/api/biz-settlement-account-payment-readonly.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\biz\SettlementAccountController.php`: passed.
- `php -l app\service\biz\SettlementAccountService.php`: passed.
- `php -l route\app.php`: passed.
- PowerShell syntax check for `scripts\settlement-account-payment-add-http-smoke.ps1`: passed.
- `php think route:list | Select-String -Pattern 'biz/settlementaccount/(payment/add|expenses/add|transfer/add|delete|page|list|detail)'`: listed the expected settlement-account routes.
- `.\scripts\web-ready.ps1`: passed.
- `.\scripts\settlement-account-payment-add-http-smoke.ps1`: passed.
- `.\scripts\settlement-account-read-http-smoke.ps1`: passed.
- `.\scripts\settlement-account-payment-read-http-smoke.ps1`: passed.
- `.\scripts\finance-read-http-smoke.ps1`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed for 74 authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, and 16 representative no-token checks.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 560/560 frontend endpoints covered by route path and zero missing reads.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.
- `.\scripts\project-progress.ps1 -Lean`: passed.
- `git diff --check`: passed with existing LF/CRLF warnings only.

### Current Issues

- Settlement-account quick income was implemented in this slice; quick expense was implemented in the subsequent 2026-06-17 expenses-add slice. Transfer, delete, Java data-change events, and workflow hooks remain deferred.
- Do not run DB row-count smoke scripts that insert temporary finance rows in parallel; run them serially when count stability is part of the assertion.

### Next Plan

- Continue choosing remaining controlled-deferred wrapper groups only after a module-specific transaction, permission, rollback, side-effect, and smoke-test plan.
- Do not commit unless the user explicitly asks for a commit.

## 2026-06-17 09:21 +08:00 - api-agent/test-agent - Biz Expenditure Record Account Switch

### Completed

- Selected `/biz/bizexpenditurerecord/edit/account` from the remaining controlled-deferred wrapper list.
- Wrote `docs/tasks/biz-expenditure-record-edit-account-plan.md` before replacing behavior.
- Replaced the deferred response with narrow Java-compatible account-switch maintenance.
- The endpoint now requires `id`, `currentTargetId`, and `targetId`, rejects same-account switches, verifies the expenditure record and linked statement still point to the current account, checks tenant/write scope for the expenditure record and both settlement accounts, locks both accounts in one transaction, adds the stored expenditure amount back to the current account, subtracts it from the target account, updates expenditure-record target account, preserves expenditure-record org, and syncs the linked statement account.
- Added `scripts/biz-expenditure-record-edit-account-http-smoke.ps1`.
- Removed `/biz/bizexpenditurerecord/edit/account` from the deferred-wrapper smoke list.
- Did not implement expenditure-record add/delete, new statement creation, workflow/data-change events, Java source changes, database schema changes, Composer/npm/frontend source changes, production data operations, or commits.

### Modified Files

- `app/controller/biz/ExpenditureRecordController.php`
- `app/service/biz/ExpenditureRecordService.php`
- `scripts/biz-expenditure-record-edit-account-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/biz-expenditure-record-edit-account-plan.md`
- `docs/api/biz-expenditure-record-readonly-compat.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\biz\ExpenditureRecordController.php`: passed.
- `php -l app\service\biz\ExpenditureRecordService.php`: passed.
- PowerShell syntax check for `scripts\biz-expenditure-record-edit-account-http-smoke.ps1`: passed.
- `php think route:list | Select-String -Pattern 'biz/bizexpenditurerecord/(edit|edit/account|add|delete|page|detail)'`: listed the expected expenditure-record routes with `edit/account` before `edit`.
- `.\scripts\web-ready.ps1`: passed.
- `.\scripts\biz-expenditure-record-edit-account-http-smoke.ps1`: passed.
- `.\scripts\biz-expenditure-record-edit-http-smoke.ps1`: passed.
- `.\scripts\biz-payment-record-edit-account-http-smoke.ps1`: passed.
- `.\scripts\finance-read-http-smoke.ps1`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed for 74 authenticated deferred wrappers and 17 representative no-token checks.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 560/560 frontend endpoints covered by route path and zero missing reads.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.
- `.\scripts\project-progress.ps1 -Lean`: passed.
- `git diff --check`: passed with existing LF/CRLF warnings only.

### Current Issues

- Account switching now matches the narrow Java balance-transfer path, but expenditure-record add/delete and new expenditure/statement creation remain deferred.
- Do not run DB row-count smoke scripts that insert temporary finance rows in parallel; run them serially when count stability is part of the assertion.

### Next Plan

- Continue choosing remaining controlled-deferred wrapper groups only after a module-specific transaction, permission, rollback, side-effect, and smoke-test plan.
- Prefer isolated finance corrections or metadata maintenance before broader workflow, inventory, purchase, provider-send, scheduler lifecycle, or sale-project state behavior.
- Do not commit unless the user explicitly asks for a commit.

## 2026-06-17 08:49 +08:00 - api-agent/test-agent - Biz Payment Record Account Switch

### Completed

- Selected `/biz/bizpaymentrecord/edit/account` from the remaining controlled-deferred wrapper list.
- Wrote `docs/tasks/biz-payment-record-edit-account-plan.md` before replacing behavior.
- Replaced the deferred response with narrow Java-compatible account-switch maintenance.
- The endpoint now requires `id`, `currentTargetId`, and `targetId`, rejects same-account switches, verifies the payment record and linked statement still point to the current account, checks tenant/write scope for the payment record and both settlement accounts, locks both accounts in one transaction, subtracts the stored payment amount from the current account, adds it to the target account, updates payment-record target/org fields, and syncs the linked statement account.
- Added `scripts/biz-payment-record-edit-account-http-smoke.ps1`.
- Removed `/biz/bizpaymentrecord/edit/account` from the deferred-wrapper smoke list.
- Did not implement payment-record add/delete, new statement creation, workflow/data-change events, Java source changes, database schema changes, Composer/npm/frontend source changes, production data operations, or commits.

### Modified Files

- `app/controller/biz/PaymentRecordController.php`
- `app/service/biz/PaymentRecordService.php`
- `scripts/biz-payment-record-edit-account-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/biz-payment-record-edit-account-plan.md`
- `docs/api/biz-payment-record-readonly-compat.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\biz\PaymentRecordController.php`: passed.
- `php -l app\service\biz\PaymentRecordService.php`: passed.
- `php -l route\app.php`: passed.
- PowerShell syntax check for `scripts\biz-payment-record-edit-account-http-smoke.ps1`: passed.
- `php think route:list | Select-String -Pattern 'biz/bizpaymentrecord/(edit|edit/account|add|delete|page|detail)'`: listed the expected payment-record routes with `edit/account` before `edit`.
- `.\scripts\web-ready.ps1`: passed.
- `.\scripts\biz-payment-record-edit-account-http-smoke.ps1`: passed.
- `.\scripts\biz-payment-record-edit-http-smoke.ps1`: passed.
- `.\scripts\finance-read-http-smoke.ps1`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed for 75 authenticated deferred wrappers and 17 representative no-token checks.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 560/560 frontend endpoints covered by route path and zero missing reads.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.
- `.\scripts\project-progress.ps1 -Lean`: passed.
- `git diff --check`: passed with existing LF/CRLF warnings only.

### Current Issues

- Account switching now matches the narrow Java balance-transfer path, but payment-record add/delete and new payment/statement creation remain deferred.
- Do not run DB row-count smoke scripts that insert temporary finance rows in parallel; run them serially when count stability is part of the assertion.

### Next Plan

- Continue choosing remaining controlled-deferred wrapper groups only after a module-specific transaction, permission, rollback, side-effect, and smoke-test plan.
- Prefer isolated finance corrections or metadata maintenance before broader workflow, inventory, purchase, provider-send, scheduler lifecycle, or sale-project state behavior.
- Do not commit unless the user explicitly asks for a commit.

## 2026-06-16 16:06 +08:00 - api-agent/test-agent - Dev Config EditBatch Value Maintenance

### Completed

- Selected `/dev/config/editBatch` from the remaining controlled-deferred wrapper list.
- Wrote `docs/tasks/dev-config-edit-batch-plan.md` before replacing behavior.
- Replaced the deferred response with narrow existing-row `dev_config.CONFIG_VALUE` batch maintenance.
- `editBatch` now requires a non-empty batch of `{ configKey, configValue }`, rejects missing/blank/duplicate/unknown/deleted keys before writes, rejects blank values, updates only existing active rows, writes update audit fields, preserves sensitive raw values when the submitted value is `******`, and returns `data = null`.
- Hardened `ConfigController::bodyInput()` to strip UTF-8 BOM from raw JSON request bodies and return `400` for invalid JSON.
- Added `scripts/dev-config-edit-batch-http-smoke.ps1`.
- Removed `/dev/config/editBatch` from the deferred-wrapper smoke list and no-token representative list.
- Did not implement provider send/test behavior, external service calls, Redis/cache invalidation, unmasking secrets, Java source changes, database schema changes, Composer/npm changes, frontend source changes, or commits.

### Modified Files

- `app/controller/dev/ConfigController.php`
- `app/service/dev/ConfigService.php`
- `scripts/dev-config-edit-batch-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/dev-config-edit-batch-plan.md`
- `docs/api/dev-config-readonly-compat.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\dev\ConfigController.php`: passed.
- `php -l app\service\dev\ConfigService.php`: passed.
- `php think route:list | Select-String -Pattern 'dev/config/(editBatch|page|list|detail)'`: listed the expected dev config routes.
- `.\scripts\dev-config-edit-batch-http-smoke.ps1`: passed after fixing raw JSON BOM parsing.
- `.\scripts\dev-read-http-smoke.ps1`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed for 79 authenticated deferred wrappers and 18 representative no-token checks after dev-config editBatch moved out of the deferred list.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.

### Current Issues

- Java removes a `dev-config:{key}` cache entry after editBatch. Current ThinkPHP config readers query `dev_config` directly, so this slice does not add cache mutation.
- Provider sends/tests, external service calls, secret unmasking, and runtime config cache behavior remain deferred.

### Next Plan

- Continue choosing remaining controlled-deferred wrapper groups only after a module-specific transaction, permission, rollback, side-effect, and smoke-test plan.
- Prefer lower-risk metadata or isolated maintenance before workflow, finance, inventory, provider-send, scheduler lifecycle, or sale-project state behavior.
- Do not commit unless the user explicitly asks for a commit.

## 2026-06-16 15:39 +08:00 - api-agent/test-agent - Biz CC Records Current-User Maintenance

### Completed

- Selected `/biz/ccrecords/add` and `/biz/ccrecords/edit` from the remaining controlled-deferred wrapper list.
- Wrote `docs/tasks/biz-cc-records-write-plan.md` before replacing behavior.
- Replaced CC-record add/edit controlled-deferred responses with narrow current-user `biz_cc_records` row maintenance.
- Add now requires the current authenticated user, tenant id, `title`, `processId`, `instanceId`, and `category`, forces `USER` to the current token user, defaults `promoterId` to the token user when omitted, writes audit fields, ignores client-spoofed `user`/`deleteFlag`, and returns `data = null`.
- Edit now requires the current authenticated user and `id`, only updates the current user's active row in the current tenant, whitelists `title`, `processId`, `promoterId`, `instanceId`, `category`, and `extJson`, preserves user/create/tenant/delete fields, and returns `data = null`.
- Added `scripts/biz-cc-records-write-http-smoke.ps1`.
- Removed `/biz/ccrecords/add` and `/edit` from the deferred-wrapper smoke list.
- Updated the deferred-wrapper smoke assertion to accept the stable `data.operation` marker because the current `ApiResponse` normalizes unmapped English failure messages to the generic request-failed text.
- Did not implement workflow copy-user delegate generation, file-relation binding, workflow transitions, notifications, data-change events, Java source changes, database schema changes, Composer/npm changes, frontend source changes, or commits.

### Modified Files

- `app/controller/biz/CcRecordsController.php`
- `app/service/biz/CcRecordsService.php`
- `scripts/biz-cc-records-write-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/biz-cc-records-write-plan.md`
- `docs/api/biz-cc-records-readonly.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\biz\CcRecordsController.php`: passed.
- `php -l app\service\biz\CcRecordsService.php`: passed.
- `php think route:list | Select-String -Pattern 'biz/ccrecords/(add|edit|delete|page|detail)'`: listed the expected CC-record routes.
- `.\scripts\biz-cc-records-write-http-smoke.ps1`: passed.
- `.\scripts\workflow-read-http-smoke.ps1`: passed after restarting the local runtime and web services; skipped only missing local pending-task and current-user CC-detail samples.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed for 80 authenticated deferred wrappers and 19 representative no-token checks after CC add/edit moved out of the deferred list.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 560 of 560 frontend endpoints covered by route path and 0 missing read-like routes.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.
- `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean`: passed after restarting the local runtime bundle plus ThinkPHP/Vue dev servers.
- `git diff --check`: passed with only normal LF/CRLF conversion warnings.

### Current Issues

- Java `BizCcRecordsController` exposes page/delete/detail, while add/edit live in the service and workflow delegate path. The ThinkPHP add/edit endpoints are therefore limited to current-user row maintenance for copied frontend wrapper compatibility.
- Workflow copy/file delegate generation outside active leave start, broader workflow transitions, notifications, and data-change events remain deferred.

### Next Plan

- Prefer low-risk metadata or narrow CRUD groups before scheduler lifecycle, provider sends, tenant bootstrap/default-data, workflow transitions, finance state changes, inventory movements, or sale-project state changes.
- Do not commit unless the user explicitly asks for a commit.

## 2026-06-16 15:28 +08:00 - api-agent/test-agent - Tenant Metadata Maintenance

### Completed

- Selected `/tenants/tenant/add`, `/tenants/tenant/edit`, and `/tenants/tenant/delete` from the remaining controlled-deferred wrapper list.
- Wrote `docs/tasks/tenant-metadata-write-plan.md` before replacing behavior.
- Replaced tenant add/edit/delete controlled-deferred responses with narrow `tenants` row metadata maintenance.
- Add now validates `tenantName`, rejects duplicate active names, creates one active tenant row with a generated 10-digit numeric code, fills create audit fields from the bearer token when available, and returns `data = null`.
- Edit now validates `tenantId` and `tenantName`, rejects missing/deleted/system tenants, rejects duplicate active names, updates only `Tenant_Name` plus update audit fields, and returns `data = null`.
- Delete now requires the copied frontend safe-password marker for `mark = tenants`, validates the full id batch before writing, rejects system and referenced tenants, logically deletes active rows, and returns `data = null`.
- Fixed the tenant delete reference scan to query `information_schema.COLUMNS` with parameterized SQL instead of ThinkPHP table-name quoting.
- Removed tenant add/edit/delete from the deferred-wrapper smoke list and added a focused tenant write HTTP smoke.
- Did not implement tenant default user/role/resource/permission bootstrap, cache invalidation, data-change events, physical tenant deletion, Java source changes, database schema changes, Composer/npm changes, or commits.

### Modified Files

- `app/controller/tenant/TenantsController.php`
- `app/service/tenant/TenantsService.php`
- `scripts/tenant-write-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/tenant-metadata-write-plan.md`
- `docs/api/tenant-readonly-compat.md`
- `docs/api/tenants-readonly-compat.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\tenant\TenantsController.php`: passed.
- `php -l app\service\tenant\TenantsService.php`: passed.
- `php think route:list | Select-String -Pattern 'tenants/tenant/(add|edit|delete|page|detail)'`: listed the expected tenant routes.
- `.\scripts\tenant-write-http-smoke.ps1`: passed.
- `.\scripts\tenant-read-http-smoke.ps1`: passed, with the existing sample-detail skip when the local database has no active tenant detail sample.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed after tenant add/edit/delete moved out of the deferred list.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 560 of 560 frontend endpoints covered by route path and 0 missing read-like routes.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.
- `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean`: passed; MySQL, Redis, PHP FastCGI, ThinkPHP HTTP, and Vue HTTP were ready.
- `git diff --check`: passed with only normal LF/CRLF conversion warnings.

### Current Issues

- Tenant default user, role, resource, relation, permission generation, cache invalidation, and data-change events remain deferred.
- The delete guard blocks tenant deletion when any other table still has active `TENANT_ID` references; this is intentionally conservative until full tenant lifecycle behavior is implemented.

### Next Plan

- Choose the next controlled-deferred wrapper group only after a dedicated transaction, permission, rollback, side-effect, and smoke-test plan is written.
- Prefer low-risk metadata or narrow CRUD groups before scheduler lifecycle, provider sends, tenant bootstrap/default-data, workflow transitions, finance state changes, inventory movements, or sale-project state changes.
- Do not commit unless the user explicitly asks for a commit.

## 2026-06-16 15:09 +08:00 - api-agent/test-agent - Gen Config Edit/Delete Metadata Maintenance

### Completed

- Selected Java-compatible `/gen/config/edit` and `/gen/config/delete` from the remaining generator controlled-deferred wrapper list.
- Wrote `docs/tasks/gen-config-edit-delete-plan.md` before replacing behavior.
- Kept `/gen/config/add` controlled-deferred because Java `GenConfigController` exposes `list`, `detail`, `edit`, `delete`, and `editBatch`, but no add route.
- Replaced gen-config edit/delete controlled-deferred responses with narrow `gen_config` row metadata maintenance.
- Edit now requires an active config id, uses the same Java edit-parameter whitelist as editBatch, ignores client-supplied audit/delete fields, writes update audit metadata from the bearer token when available, and returns `data = null`.
- Delete now accepts Java-style `[{ id }]`, `idList`, `ids`, or a single `id`, validates the full batch before writing, logically deletes selected active config rows, preserves the parent `gen_basic` row, and returns `data = null`.
- Removed `/gen/config/edit` and `/delete` from the deferred-wrapper smoke list and added a dedicated write smoke.
- Did not implement `/gen/config/add`, direct project generation, Java/ThinkPHP/frontend source generation, menu/role/resource generation, database schema changes, Composer/npm changes, Java source changes, or commits.

### Modified Files

- `app/controller/gen/ConfigController.php`
- `app/service/gen/ConfigService.php`
- `scripts/gen-config-write-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/gen-config-edit-delete-plan.md`
- `docs/api/gen-readonly-compat.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\gen\ConfigController.php`: passed.
- `php -l app\service\gen\ConfigService.php`: passed.
- `php think route:list | Select-String -Pattern 'gen/config/(add|edit|delete|editBatch|list|detail)'`: listed the expected gen config routes.
- `.\scripts\gen-config-write-http-smoke.ps1`: passed.
- `.\scripts\gen-read-http-smoke.ps1`: passed, with the existing documented skip for a missing saved config-detail sample row.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed for 85 authenticated deferred wrappers and 21 representative no-token checks after gen-config edit/delete moved out of the deferred list.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 560 of 560 frontend endpoints covered by route path and 0 missing read-like routes.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.
- `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean`: passed; MySQL, Redis, PHP FastCGI, ThinkPHP HTTP, and Vue HTTP were ready.
- `git diff --check`: passed with only normal LF/CRLF conversion warnings.
- Git relation check: local `HEAD` and upstream both resolved to `f700c24`, with `HEAD...@{u}` ahead/behind `0 0`.

### Current Issues

- `/gen/basic/execGenPro` remains controlled-deferred because direct generator project output can write files and create menu/role/resource side effects.
- `/gen/config/add` remains controlled-deferred because the Java reference has no add route and copied active configuration saves are covered by edit/delete/editBatch.

### Next Plan

- Choose the next controlled-deferred wrapper group only after a dedicated transaction, permission, rollback, side-effect, and smoke-test plan is written.
- Prefer low-risk metadata or narrow CRUD groups before scheduler lifecycle, provider sends, tenant bootstrap/default-data, workflow transitions, finance state changes, inventory movements, or sale-project state changes.
- Do not commit unless the user explicitly asks for a commit.

## 2026-06-16 14:59 +08:00 - api-agent/test-agent - Gen Basic Metadata Maintenance

### Completed

- Selected the low-risk `/gen/basic/add`, `/gen/basic/edit`, and `/gen/basic/delete` group from the remaining controlled-deferred wrapper list.
- Wrote `docs/tasks/gen-basic-metadata-write-plan.md` before replacing behavior.
- Replaced gen-basic controlled-deferred add/edit/delete responses with narrow `gen_basic` metadata maintenance.
- Add now validates the copied generator form fields, rejects missing target tables, rejects `ACT_` workflow tables, rejects missing primary-key columns, inserts one `gen_basic` row, and creates default `gen_config` rows from current database columns.
- Edit now updates only generator basic metadata fields, preserves create audit fields, refreshes config primary-key flags when the selected key changes, and rebuilds active config rows only when the selected table changes.
- Delete now validates the full id batch before writing and logically deletes both selected `gen_basic` rows and their active `gen_config` rows in one transaction.
- Kept `/gen/basic/execGenPro` and `/gen/config/add`, `/edit`, `/delete` controlled-deferred.
- Removed `/gen/basic/add`, `/edit`, and `/delete` from the deferred-wrapper smoke list and added a dedicated write smoke.
- Did not implement direct project generation, Java/ThinkPHP/frontend source generation, menu/role/resource generation, database schema changes, Composer/npm changes, Java source changes, or commits.

### Modified Files

- `app/controller/gen/BasicController.php`
- `app/service/gen/BasicService.php`
- `scripts/gen-basic-write-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/gen-basic-metadata-write-plan.md`
- `docs/api/gen-readonly-compat.md`
- `docs/api/gen-basic-metadata-readonly.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/public-file-change-request.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\gen\BasicController.php`: passed.
- `php -l app\service\gen\BasicService.php`: passed.
- `php think route:list | Select-String -Pattern 'gen/basic/(add|edit|delete|execGenPro|page|detail|tables|tableColumns)|gen/config/(add|edit|delete|editBatch)'`: listed the expected gen routes.
- `.\scripts\gen-basic-write-http-smoke.ps1`: passed.
- `.\scripts\gen-read-http-smoke.ps1`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed for 89 authenticated deferred wrappers and 21 representative no-token checks after gen-basic add/edit/delete moved out of the deferred list.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 560 of 560 frontend endpoints covered by route path and 0 missing read-like routes.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.
- `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean`: passed; MySQL, Redis, PHP FastCGI, ThinkPHP HTTP, and Vue HTTP were ready.
- `git diff --check`: passed with only normal LF/CRLF conversion warnings.
- Git relation check: local `HEAD` and upstream both resolved to `f700c24`, with `HEAD...@{u}` ahead/behind `0 0`.

### Current Issues

- Java direct project generation writes generated files and can create menu/role/resource side effects. ThinkPHP still keeps `/gen/basic/execGenPro` disabled.
- `gen/config/add`, `/edit`, and `/delete` remain controlled-deferred because the copied active configuration grid saves through `/gen/config/editBatch`; single-row config form behavior needs a separate plan if opened later.

### Next Plan

- Choose the next controlled-deferred wrapper group only after a dedicated transaction, permission, rollback, side-effect, and smoke-test plan is written.
- Prefer low-risk metadata or narrow CRUD groups before scheduler lifecycle, provider sends, tenant bootstrap/default-data, workflow transitions, finance state changes, inventory movements, or sale-project state changes.
- Do not commit unless the user explicitly asks for a commit.

## 2026-06-16 14:45 +08:00 - api-agent/test-agent - Dev Job Metadata Maintenance

### Completed

- Selected the lower-risk `/dev/job/add` and `/dev/job/edit` group from the remaining controlled-deferred wrapper list.
- Wrote `docs/tasks/dev-job-metadata-write-plan.md` before replacing behavior.
- Replaced add/edit controlled-deferred responses with narrow `dev_job` metadata maintenance.
- Added Java-style field validation for `name`, `category`, `actionClass`, `cronExpression`, and `sortCode`.
- Added `FRM`/`BIZ` category validation, Java-style cron text shape validation, action-class allow-listing from the existing compatibility action-class list, duplicate active `ACTION_CLASS + CRON_EXPRESSION` guard, and running-job edit rejection.
- New jobs are created as `JOB_STATUS = STOPPED`; edit preserves `CODE`, `JOB_STATUS`, delete state, and create audit fields.
- Kept `/dev/job/stopJob`, `/runJob`, and `/runJobNow` controlled-deferred.
- Removed `/dev/job/add` and `/dev/job/edit` from the deferred-wrapper smoke list and added a dedicated write smoke.
- Did not implement scheduler registration/removal, task execution, Java bean execution, provider calls, notifications, cache invalidation, data-change events, schema changes, Java source changes, or commits.

### Modified Files

- `app/controller/dev/JobController.php`
- `app/service/dev/JobService.php`
- `scripts/dev-job-write-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/dev-job-metadata-write-plan.md`
- `docs/api/dev-job-readonly-compat.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/problem-optimization-log.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\dev\JobController.php`: passed.
- `php -l app\service\dev\JobService.php`: passed.
- `php think route:list | Select-String -Pattern 'dev/job/(add|edit|stopJob|runJob|runJobNow|delete|page|detail|getActionClass)'`: listed all dev-job routes.
- `.\scripts\dev-job-write-http-smoke.ps1`: passed.
- `.\scripts\dev-read-http-smoke.ps1`: passed.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 560 of 560 frontend endpoints covered by route path and 0 missing read-like routes.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed for 92 authenticated deferred wrappers and 21 representative no-token checks, with `/dev/job/stopJob`, `/runJob`, and `/runJobNow` still returning `code = 400`.
- `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean`: passed; MySQL, Redis, PHP FastCGI, ThinkPHP HTTP, and Vue HTTP were ready.
- `git diff --check`: passed with only normal LF/CRLF conversion warnings.
- Git relation check: local `HEAD` and upstream both resolved to `f700c24`, with `HEAD...@{u}` ahead/behind `0 0`.

### Current Issues

- Java `DevJobServiceImpl` validates actual Java classes and scheduler task interfaces. ThinkPHP cannot execute those Java beans, so this slice uses the current compatibility action-class list as an allow-list and keeps task execution deferred.
- Real scheduler lifecycle, run/stop/run-now, class execution, provider behavior, notification hooks, cache invalidation hooks, data-change events, production data sync, and Java source changes remain deferred.

### Next Plan

- Choose the next controlled-deferred wrapper group only after a dedicated transaction, permission, rollback, side-effect, and smoke-test plan is written.
- Prefer low-risk metadata or narrow CRUD groups before scheduler lifecycle, provider sends, tenant bootstrap/default-data, workflow transitions, finance state changes, inventory movements, or sale-project state changes.
- Do not commit unless the user explicitly asks for a commit.

## 2026-06-16 14:31 +08:00 - api-agent/test-agent - Biz User Vacation Manual Maintenance

### Completed

- Selected the low-risk `biz_user_vacation` group from the controlled-deferred wrapper set and wrote the module-specific implementation plan.
- Replaced `/biz/bizuservacation/add`, `/edit`, and `/delete` controlled-deferred responses with narrow manual maintenance behavior.
- Added transactional validation for required Java-style fields, target user existence, token tenant match when available, category/id/userId length, duplicate current-year rows, nonnegative amounts, and `usedAmount <= amount`.
- Kept delete as logical delete with full-batch validation before any update and `VERSION` increments on edit/delete.
- Removed the three vacation routes from the deferred-wrapper smoke list and added a dedicated authenticated write smoke.
- Updated API compatibility docs, gap map, progress dashboard, and problem log.
- Did not implement vacation generation/reduction helpers, leave approval deductions, workflow writes, payroll-facing recalculation, notifications, data-change events, schema changes, Java source changes, or commits.

### Modified Files

- `app/controller/biz/BizUserVacationController.php`
- `app/service/biz/BizUserVacationService.php`
- `scripts/biz-user-vacation-write-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/biz-user-vacation-write-plan.md`
- `docs/api/biz-user-vacation-readonly.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/problem-optimization-log.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\biz\BizUserVacationController.php`: passed.
- `php -l app\service\biz\BizUserVacationService.php`: passed.
- `php think route:list | Select-String -Pattern 'bizuservacation'`: listed page/detail/add/edit/delete routes.
- `.\scripts\biz-user-vacation-write-http-smoke.ps1`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed for 94 authenticated deferred wrappers and 21 representative no-token checks after vacation add/edit/delete moved out of the deferred list.
- `.\scripts\hr-read-http-smoke.ps1`: passed.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 560 of 560 frontend endpoints covered by route path and 0 missing read-like routes.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.
- `git diff --check`: passed with only normal LF/CRLF conversion warnings.
- `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean`: passed; MySQL, Redis, PHP FastCGI, ThinkPHP HTTP, and Vue HTTP were ready.
- Git relation check: local `HEAD` and upstream both resolved to `f700c24`, with `HEAD...@{u}` ahead/behind `0 0`.

### Current Issues

- Java `BizUserVacationController` currently exposes only `detail`; ThinkPHP write endpoints are frontend/service compatibility manual maintenance around the existing service shapes, not Java controller parity.
- Vacation generation/reduction, leave approval deductions, workflow side effects, payroll recalculation, provider/scheduler behavior, notifications, data-change events, production data sync, and Java source changes remain deferred.

### Next Plan

- Choose the next controlled-deferred wrapper group only after a dedicated transaction, permission, rollback, side-effect, and smoke-test plan is written.
- Prefer low-risk CRUD groups before finance, inventory, workflow, sale-project state, provider, scheduler, or tenant bootstrap side effects.
- Do not commit unless the user explicitly asks for a commit.

## 2026-05-28 15:36 +08:00

Agent: db-agent

### Completed Content

- Completed db-agent foundation database mapping phase.
- Analyzed Java SQL snapshot, system/auth/client/mobile/tenant entities, mapper XML, and RBAC relation categories.
- Generated passive ThinkPHP foundation Models.
- Generated database mapping, relation, and index analysis documents.
- Created long-term workflow tracking files required by the multi-agent process.
## 2026-05-28 17:25 +08:00

Agent: auth-agent

### Completed Content

- Started auth-agent after db-agent completion.
- Confirmed worktree is on `refactor/auth` and clean before edits.
- Read project rules from `AGENTS.md`.
- Confirmed `PLANS.md`, `IMPLEMENT.md`, and `STATUS.md` were missing in `OA-auth`; created them for the long-term workflow.
- Analyzed Java auth controller/service/config and system login user provider at a high level.
- Identified that Java-compatible auth routes require modifying locked file `route/app.php`.
- Created public file change request before any route or auth business implementation.
## 2026-06-16 13:58 +08:00 - test-agent/frontend-agent - Guarded Browser Smoke Refresh

### Completed

- Reran the upload/provider guard browser smoke batches after the preflight coverage hardening slice.
- Verified 53 guarded render/detail targets across:
  - default upload/provider pages;
  - management pages;
  - finance, purchase, inventory, warehouse, supplier, and invoicing pages;
  - sales, operations, proxy-payment, security-deposit, and report pages;
  - workflow, home, HR, history, team-project list, and member-visible team-project detail pages.
- Kept the slice render/detail-only under the guarded forbidden-request pattern; no upload, import, export, delete, send, provider, scheduler, workflow-transition, grant, reset, status, or save requests were triggered.
- Updated the progress dashboard and API gap map so the next execution order now points to selecting one controlled-deferred wrapper group with an explicit transaction, permission, rollback, side-effect, and smoke-test plan.

### Modified Files

- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/api-gap-map.md`
- `STATUS.md`

### Test Results

- `.\scripts\web-ready.ps1`: passed; backend `127.0.0.1:82` and frontend `127.0.0.1:83` were listening and returned HTTP OK.
- `.\scripts\browser-upload-provider-guard-smoke.ps1`: passed for the five default targets with zero forbidden requests, zero bad API statuses, zero failed loads, and zero console/page errors.
- `.\scripts\browser-upload-provider-guard-smoke.ps1 -SkipDefaultTargets -TargetPath <management targets>`: passed for 10 targets with zero forbidden requests and zero bad API statuses.
- `.\scripts\browser-upload-provider-guard-smoke.ps1 -SkipDefaultTargets -TargetPath <finance/purchase/inventory targets>`: passed for 12 targets with zero forbidden requests and zero bad API statuses.
- `.\scripts\browser-upload-provider-guard-smoke.ps1 -SkipDefaultTargets -TargetPath <sales/operations/report targets>`: passed for 15 targets with zero forbidden requests and zero bad API statuses.
- `.\scripts\browser-upload-provider-guard-smoke.ps1 -SkipDefaultTargets -TargetPath <workflow/home/HR/team targets>`: passed for 10 list/render targets plus one member-visible team-project detail target with zero forbidden requests and zero bad API statuses.

### Current Issues

- This is browser smoke only; it does not implement cloud storage, provider sends, physical file cleanup, scheduler execution, workflow transitions, or side-effect-heavy business writes.

### Next Plan

- Continue only with an explicit module-specific write plan for a selected controlled-deferred wrapper group, or keep using guarded browser smokes for read/render-only frontend checks.
- Do not commit unless the user explicitly asks for a commit.

## 2026-06-16 13:35 +08:00 - test-agent/frontend-agent - Preflight Coverage Hardening

### Completed

- Added frontend route-gap and controlled-deferred wrapper checks to `scripts/project-preflight.ps1`.
- Added skip switches:
  - `-SkipFrontendApiRouteGap`
  - `-SkipFrontendDeferredWrites`
- Added `scripts/frontend-deferred-write-wrapper-smoke.ps1` to `scripts/project-progress.ps1` fast commands.
- Updated docs so future sessions know default preflight now guards static frontend route coverage and deferred-write behavior.
- Added problem-log row `P-035` for this preflight coverage gap.

### Modified Files

- `scripts/project-preflight.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/problem-optimization-log.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/lean-continuation-workflow.md`
- `docs/api/frontend-api-route-gap-smoke.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `STATUS.md`

### Test Results

- `.\scripts\project-preflight.ps1`: reached the later `Dev Read HTTP Smoke` phase after passing Git status, runtime readiness, web readiness, frontend API method smoke, frontend API route-gap smoke, frontend deferred write wrapper smoke, role-selector, user-display, business-read, inventory/delivery-read, finance-read, purchase-order-read, settlement-account-payment-read, settlement-account-read, supplier/warehouse-read, product-read, HR-read, team-project-read, datareport-read, and resource-read smokes; the command then hit the tool-level 300s timeout before finishing.
- `.\scripts\dev-read-http-smoke.ps1`: passed.
- `.\scripts\gen-read-http-smoke.ps1`: passed, with documented sample skip for missing gen config detail sample.
- `.\scripts\auth-index-read-http-smoke.ps1`: passed.
- `.\scripts\directory-alias-http-smoke.ps1`: passed.
- `.\scripts\tenant-read-http-smoke.ps1`: passed, with documented sample skip for missing tenant detail sample.
- `.\scripts\message-sse-http-smoke.ps1`: passed.
- `.\scripts\workflow-read-http-smoke.ps1`: passed, with documented sample skips for missing pending task/runtime and current-user CC detail samples.
- `git diff --check`: passed with only normal LF/CRLF warnings.

### Current Issues

- The full default project preflight can exceed a 300s command timeout after adding the coverage-critical frontend checks. Use a longer command timeout for one-shot full preflight, or run the later HTTP smoke groups separately when working inside a shorter tool timeout.

### Next Plan

- Keep frontend route coverage and deferred-wrapper behavior in the default preflight path.
- Replace selected controlled-deferred wrappers only after a module-specific transaction, permission, rollback, and browser-smoke plan is written.

## 2026-06-16 13:25 +08:00 - test-agent/frontend-agent - Workflow Task Sale Project Controlled Deferred Wrappers

### Completed

- Added protected controlled-deferred wrappers for the final copied frontend workflow/task/sale-project side-effect paths:
  - `POST /biz/process/cancel`
  - `POST /biz/process/leave/edit`
  - `POST /biz/process/leave/start`
  - `POST /biz/process/makePayment/start`
  - `POST /biz/process/payment/start`
  - `POST /biz/process/procure/start`
  - `POST /biz/process/procure/warehouse/start`
  - `POST /biz/process/project/delivery/start`
  - `POST /biz/process/project/init/start`
  - `POST /biz/process/project/play/start`
  - `POST /biz/process/project/reissue/start`
  - `POST /biz/process/project/return/start`
  - `POST /biz/process/reimbursement/start`
  - `POST /biz/task/approve`
  - `POST /biz/task/reject`
  - `GET /biz/task/sse/stream`
  - `POST /biz/saleproject/add`
  - `POST /biz/saleproject/edit`
  - `POST /biz/saleproject/delete`
  - `POST /biz/saleproject/amount/edit`
  - `POST /biz/saleproject/deal/edit`
  - `POST /biz/saleproject/cancel`
  - `POST /biz/saleproject/history/add`
  - `POST /biz/saleproject/repeal`
  - `POST /biz/saleproject/special/add`
  - `POST /biz/saleproject/visibility/edit`
- The wrappers return authenticated `code=400` deferred responses and do not start/cancel workflow, approve/reject tasks, open a long-lived task SSE stream, create/edit/delete/cancel/repeal sale projects, mutate project amount/deal/visibility/history, write finance/inventory/project state, emit notifications/data-change events, change schema, or touch Java source.
- Extended `scripts/frontend-deferred-write-wrapper-smoke.ps1`, `docs/api/frontend-controlled-deferred-write-wrappers.md`, `docs/api/biz-workflow-readonly-compat.md`, and `docs/api/biz-saleproject-cost-readonly.md`.
- Updated the API gap map, route-gap smoke doc, and dashboard route-gap counts to 578 ThinkPHP routes, 560 frontend endpoints covered by route path, and 0 missing frontend wrapper paths.

### Modified Files

- `app/controller/biz/ProcessController.php`
- `app/controller/biz/TaskController.php`
- `app/controller/biz/SaleProjectController.php`
- `route/app.php`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/api/biz-workflow-readonly-compat.md`
- `docs/api/biz-saleproject-cost-readonly.md`
- `docs/api/frontend-api-route-gap-smoke.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\biz\ProcessController.php`: passed.
- `php -l app\controller\biz\TaskController.php`: passed.
- `php -l app\controller\biz\SaleProjectController.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list | Select-String -Pattern 'biz/process/(cancel|leave|makePayment|payment|procure|project|reimbursement)|biz/saleproject/(add|edit|delete|amount|deal|cancel|history|repeal|special|visibility)|biz/task/(approve|reject|sse)'`: listed the new wrappers.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed for 97 authenticated deferred wrappers and 21 no-token checks.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 560 route-path-covered frontend endpoints and 0 missing frontend wrapper paths.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.

### Current Issues

- Static frontend wrapper route-path coverage is complete, but many write/action routes intentionally return controlled deferred responses. Real workflow transitions, sale-project state changes, finance/inventory side effects, provider sends, scheduler actions, generator writes, tenant bootstrap, and production data sync still require module-specific transaction, permission, rollback, and browser-smoke plans.

### Next Plan

- Run final readiness/Git hygiene checks after documentation updates.
- Keep future work focused on replacing selected controlled-deferred wrappers with real implementations only after each module has an explicit transaction and side-effect plan.

## 2026-06-16 13:21 +08:00 - test-agent/frontend-agent - Dev Gen Tenant Controlled Deferred Wrappers

### Completed

- Added protected controlled-deferred wrappers for dev/config, dev/email, dev/job, gen/basic, gen/config, and tenant copied side-effect controls:
  - `POST /dev/config/editBatch`
  - `POST /dev/email/sendLocalTxt`
  - `POST /dev/email/sendLocalHtml`
  - `POST /dev/email/sendAliyunTxt`
  - `POST /dev/email/sendAliyunHtml`
  - `POST /dev/email/sendAliyunTmp`
  - `POST /dev/email/sendTencentTxt`
  - `POST /dev/email/sendTencentHtml`
  - `POST /dev/email/sendTencentTmp`
  - `POST /dev/job/add`
  - `POST /dev/job/edit`
  - `POST /dev/job/stopJob`
  - `POST /dev/job/runJob`
  - `POST /dev/job/runJobNow`
  - `POST /gen/basic/add`
  - `POST /gen/basic/edit`
  - `POST /gen/basic/delete`
  - `POST /gen/basic/execGenPro`
  - `POST /gen/config/add`
  - `POST /gen/config/edit`
  - `POST /gen/config/delete`
  - `POST /tenants/tenant/add`
  - `POST /tenants/tenant/edit`
  - `POST /tenants/tenant/delete`
- The wrappers return authenticated `code=400` deferred responses and do not mutate provider/system config, read provider credentials, send email, run scheduler jobs, execute task classes, write generator metadata, generate project files, create tenant bootstrap data, mutate tenant cache/events, change schema, or touch Java source.
- Extended `scripts/frontend-deferred-write-wrapper-smoke.ps1`, `docs/api/frontend-controlled-deferred-write-wrappers.md`, and dev/config, dev/email/SMS, dev/job, gen, and tenant compatibility docs.
- Updated the API gap map, route-gap smoke doc, and dashboard route-gap counts to 552 ThinkPHP routes, 534 frontend endpoints covered by route path, 0 read-like gaps, and 26 side-effect-like deferred gaps.

### Modified Files

- `app/controller/dev/ConfigController.php`
- `app/controller/dev/EmailController.php`
- `app/controller/dev/JobController.php`
- `app/controller/gen/BasicController.php`
- `app/controller/gen/ConfigController.php`
- `app/controller/tenant/TenantsController.php`
- `route/app.php`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/api/dev-config-readonly-compat.md`
- `docs/api/dev-email-sms-readonly-compat.md`
- `docs/api/dev-job-readonly-compat.md`
- `docs/api/gen-readonly-compat.md`
- `docs/api/gen-basic-metadata-readonly.md`
- `docs/api/tenant-readonly-compat.md`
- `docs/api/tenants-readonly-compat.md`
- `docs/api/frontend-api-route-gap-smoke.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\dev\ConfigController.php`: passed.
- `php -l app\controller\dev\EmailController.php`: passed.
- `php -l app\controller\dev\JobController.php`: passed.
- `php -l app\controller\gen\BasicController.php`: passed.
- `php -l app\controller\gen\ConfigController.php`: passed.
- `php -l app\controller\tenant\TenantsController.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list | Select-String -Pattern 'dev/config/editBatch|dev/email/send(Local|Aliyun|Tencent)|dev/job/(add|edit|stopJob|runJob|runJobNow)|gen/basic/(add|edit|delete|execGenPro)|gen/config/(add|edit|delete)|tenants/tenant/(add|edit|delete)'`: listed the new wrappers.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed for 71 authenticated deferred wrappers and 17 no-token checks.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 534 route-path-covered frontend endpoints, 0 read-like missing endpoints, and 26 side-effect-like missing endpoints.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.

### Current Issues

- Real provider config batch edits, email sends, scheduler lifecycle, job execution, generator writes, direct project code generation, tenant add/edit/delete, and tenant bootstrap/cache/event side effects remain deferred because they need provider, scheduler, output-path, permission, transaction, and rollback plans.

### Next Plan

- Run final readiness/Git hygiene checks after documentation updates.
- Remaining frontend route-gap paths are workflow/task transitions and sale-project state/write actions; keep them behind controlled-deferred wrappers or module-specific transaction plans.

## 2026-06-16 13:16 +08:00 - test-agent/frontend-agent - HR And CC Controlled Deferred Wrappers

### Completed

- Added protected controlled-deferred wrappers for HR and workflow-copy copied side-effect controls:
  - `POST /biz/bizleaveapplication/add`
  - `POST /biz/bizpayroll/add`
  - `POST /biz/bizpayroll/import`
  - `GET /biz/bizpayroll/export`
  - `POST /biz/bizpayroll/generate/add`
  - `POST /biz/bizuservacation/add`
  - `POST /biz/bizuservacation/edit`
  - `POST /biz/bizuservacation/delete`
  - `POST /biz/ccrecords/add`
  - `POST /biz/ccrecords/edit`
- The wrappers return authenticated `code=400` deferred responses and do not create leave records, parse/import/export payroll files, generate payroll rows, mutate vacation balances, create or edit CC records, start workflow, send notifications, emit data-change events, change schema, or touch Java source.
- Extended `scripts/frontend-deferred-write-wrapper-smoke.ps1`, `docs/api/frontend-controlled-deferred-write-wrappers.md`, and the leave/payroll/vacation/CC compatibility docs.
- Updated the API gap map, route-gap smoke doc, and dashboard route-gap counts to 528 ThinkPHP routes, 510 frontend endpoints covered by route path, 0 read-like gaps, and 50 side-effect-like deferred gaps.

### Modified Files

- `app/controller/biz/BizLeaveApplicationController.php`
- `app/controller/biz/BizPayrollController.php`
- `app/controller/biz/BizUserVacationController.php`
- `app/controller/biz/CcRecordsController.php`
- `route/app.php`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/api/biz-leave-application-readonly.md`
- `docs/api/biz-payroll-readonly.md`
- `docs/api/biz-user-vacation-readonly.md`
- `docs/api/biz-cc-records-readonly.md`
- `docs/api/frontend-api-route-gap-smoke.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\biz\BizLeaveApplicationController.php`: passed.
- `php -l app\controller\biz\BizPayrollController.php`: passed.
- `php -l app\controller\biz\BizUserVacationController.php`: passed.
- `php -l app\controller\biz\CcRecordsController.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list | Select-String -Pattern 'bizleaveapplication/add|bizpayroll/(add|import|export|generate)|bizuservacation/(add|edit|delete)|ccrecords/(add|edit)'`: listed all ten wrappers.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed for 47 authenticated deferred wrappers and 12 no-token checks.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 510 route-path-covered frontend endpoints, 0 read-like missing endpoints, and 50 side-effect-like missing endpoints.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.

### Current Issues

- At this checkpoint, real leave creation, payroll add, payroll import, payroll export, payroll generation, vacation-balance writes, CC add/edit, workflow-copy generation, and related workflow/payroll side effects remained deferred because they required transaction, rollback, file, and workflow plans. Later 2026-06-22 slices covered active leave-start copy-user CC rows, but other process copy generation and file binding remain deferred.

Subsequent state on 2026-06-16: vacation add/edit/delete, CC add/edit/delete, and payroll CSV export are now covered by narrow slices; payroll add, payroll import, and payroll generation remained deferred at that date.

Subsequent state on 2026-06-18: payroll generation and focused Java-template payroll import are now covered by narrow slices; payroll add remains deferred.

### Next Plan

- Run final readiness/Git hygiene checks after documentation updates.
- Use `.\scripts\frontend-api-route-gap-smoke.ps1 -ShowMissing` before selecting the next controlled-deferred or module-planned write slice.
- Do not implement real payroll, vacation, workflow-copy, or workflow transition side effects without a transaction and rollback plan.

## 2026-06-16 13:12 +08:00 - test-agent/frontend-agent - Inventory Delivery Settlement Controlled Deferred Wrappers

### Completed

- Added protected controlled-deferred wrappers for inventory, delivery-record, and settlement-account copied side-effect controls:
  - `POST /biz/inventory/add`
  - `POST /biz/inventory/delete`
  - `POST /biz/warehouses/delivery/add`
  - `POST /biz/settlementaccount/delete`
  - `POST /biz/settlementaccount/expenses/add`
  - `POST /biz/settlementaccount/payment/add`
  - `POST /biz/settlementaccount/transfer/add`
- The wrappers return authenticated `code=400` deferred responses and do not create inventory rows, delete inventory, create delivery records, delete settlement accounts, mutate balances, write statements, create payment/expenditure rows, transfer funds, trigger workflow, emit data-change events, change schema, or touch Java source.
- Extended `scripts/frontend-deferred-write-wrapper-smoke.ps1`, `docs/api/frontend-controlled-deferred-write-wrappers.md`, and the inventory/delivery/settlement-account compatibility docs.
- Updated the API gap map, route-gap smoke doc, and dashboard route-gap counts to 518 ThinkPHP routes, 500 frontend endpoints covered by route path, 0 read-like gaps, and 60 side-effect-like deferred gaps.

### Modified Files

- `app/controller/biz/InventoryController.php`
- `app/controller/biz/DeliveryRecordController.php`
- `app/controller/biz/SettlementAccountController.php`
- `route/app.php`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/api/biz-inventory-readonly-compat.md`
- `docs/api/biz-delivery-record-readonly-compat.md`
- `docs/api/biz-settlement-account-readonly-compat.md`
- `docs/api/frontend-api-route-gap-smoke.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\biz\InventoryController.php`: passed.
- `php -l app\controller\biz\DeliveryRecordController.php`: passed.
- `php -l app\controller\biz\SettlementAccountController.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list | Select-String -Pattern 'inventory/(add|delete)|warehouses/delivery/add|settlementaccount/(delete|expenses|payment|transfer)'`: listed all seven wrappers.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed for 37 authenticated deferred wrappers and 9 no-token checks.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 500 route-path-covered frontend endpoints, 0 read-like missing endpoints, and 60 side-effect-like missing endpoints.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.

### Current Issues

- At this checkpoint, real inventory add/delete, delivery stock movement, settlement-account delete, quick income/expense/payment, and transfer behavior remained deferred because they can mutate warehouse stock, settlement balances, statements, finance rows, workflow state, and rollback boundaries. Subsequent state: settlement-account payment/expenses/transfer and protected logical delete are now covered by focused slices.

### Next Plan

- Run the final smoke/readiness/Git hygiene checks for this slice.
- Use `.\scripts\frontend-api-route-gap-smoke.ps1 -ShowMissing` before selecting the next controlled-deferred or module-planned write slice.
- Do not implement real stock, finance, settlement, or workflow side effects without a transaction and rollback plan.

## 2026-06-16 12:58 +08:00 - test-agent/frontend-agent - Purchase Order Controlled Deferred Wrappers

### Completed

- Added protected controlled-deferred wrappers for purchase-order copied write controls:
  - `POST /biz/bizpurchaseorder/add`
  - `POST /biz/bizpurchaseorder/edit`
  - `POST /biz/bizpurchaseorder/audit/edit`
  - `POST /biz/bizpurchaseorder/warehouse/add`
  - `POST /biz/bizpurchaseorder/warehouse/one/add`
  - `POST /biz/bizpurchaseorder/cancel`
  - `POST /biz/bizpurchaseorder/delete`
- The wrappers return authenticated `code=400` deferred responses and do not create or edit purchase orders, audit records, stock-in records, inventory movements, expenditure records, workflow actions, data-change events, schema changes, or Java source changes.
- Extended `scripts/frontend-deferred-write-wrapper-smoke.ps1`, `docs/api/frontend-controlled-deferred-write-wrappers.md`, and `docs/api/biz-purchase-order-readonly-compat.md`.
- Updated the API gap map and dashboard route-gap counts.

### Modified Files

- `app/controller/biz/PurchaseOrderController.php`
- `route/app.php`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/api/biz-purchase-order-readonly-compat.md`
- `docs/api/frontend-api-route-gap-smoke.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\biz\PurchaseOrderController.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list | Select-String -Pattern 'bizpurchaseorder/(add|edit|audit|warehouse|cancel|delete)'`: listed all seven wrappers.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed for 30 authenticated deferred wrappers and 7 no-token checks.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 493 route-path-covered frontend endpoints, 0 read-like missing endpoints, and 67 side-effect-like missing endpoints.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.

### Current Issues

- Real purchase-order add/delete and batch warehouse stock-in behavior remains deferred because it can mutate broader inventory, procurement state, finance records, workflow state, and rollback boundaries. Purchase-order cancel, normal edit, audit edit, and single-order warehouse one add were later moved to narrow implementations.

### Next Plan

- Use `.\scripts\frontend-api-route-gap-smoke.ps1 -ShowMissing` to choose the next controlled-deferred or module-planned write slice.
- Do not implement real purchase, inventory, finance, or workflow side effects without a transaction plan.

## 2026-06-16 12:55 +08:00 - test-agent/frontend-agent - Collection Receipt And Debit Note Controlled Deferred Wrappers

### Completed

- Added protected controlled-deferred wrappers for collection-receipt copied write controls:
  - `POST /biz/bizcollectionreceipt/add`
  - `POST /biz/bizcollectionreceipt/edit`
  - `POST /biz/bizcollectionreceipt/batchExpenditure/edit`
  - `POST /biz/bizcollectionreceipt/delete`
- Added protected controlled-deferred wrappers for debit-note copied write controls:
  - `POST /biz/bizdebitnote/add`
  - `POST /biz/bizdebitnote/edit`
  - `POST /biz/bizdebitnote/batchRepayment/edit`
  - `POST /biz/bizdebitnote/history/add`
  - `POST /biz/bizdebitnote/delete`
- The wrappers return authenticated `code=400` deferred responses and do not create expenditures, repayments, history rows, finance records, workflow actions, data-change events, schema changes, or Java source changes.
- Extended `scripts/frontend-deferred-write-wrapper-smoke.ps1` and `docs/api/frontend-controlled-deferred-write-wrappers.md`.
- Updated the API gap map and dashboard route-gap counts.

### Modified Files

- `app/controller/biz/CollectionReceiptController.php`
- `app/controller/biz/DebitNoteController.php`
- `route/app.php`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/api/frontend-api-route-gap-smoke.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\biz\CollectionReceiptController.php`: passed.
- `php -l app\controller\biz\DebitNoteController.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list | Select-String -Pattern 'bizcollectionreceipt/(add|edit|batchExpenditure|delete)|bizdebitnote/(add|edit|batchRepayment|history|delete)'`: listed all nine wrappers.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed for 23 authenticated deferred wrappers and 6 no-token checks.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 486 route-path-covered frontend endpoints, 0 read-like missing endpoints, and 74 side-effect-like missing endpoints.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.

### Current Issues

- Subsequent state: collection-receipt batch expenditure and debit-note batch repayment are covered by 2026-06-17 quick-settlement slices; debit-note history behavior remains deferred because it can create finance records, mutate settlement state, and require rollback semantics.

### Next Plan

- Use `.\scripts\frontend-api-route-gap-smoke.ps1 -ShowMissing` to choose the next controlled-deferred or module-planned write slice.
- Do not implement real receipt, repayment, payment, stock, or workflow side effects without a transaction plan.

## 2026-06-16 12:53 +08:00 - test-agent/frontend-agent - Expenditure Record Controlled Deferred Wrappers

### Completed

- Added protected controlled-deferred wrappers for copied expenditure-record write controls:
  - `POST /biz/bizexpenditurerecord/add`
  - `POST /biz/bizexpenditurerecord/edit`
  - `POST /biz/bizexpenditurerecord/edit/account`
  - `POST /biz/bizexpenditurerecord/delete`
- The wrappers return authenticated `code=400` deferred responses and do not call expenditure write services, mutate balances, write rows, start workflow, change schema, or touch Java source.
- Extended `scripts/frontend-deferred-write-wrapper-smoke.ps1` and `docs/api/frontend-controlled-deferred-write-wrappers.md`.
- Updated the API gap map and dashboard route-gap counts.

### Modified Files

- `app/controller/biz/ExpenditureRecordController.php`
- `route/app.php`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/api/frontend-api-route-gap-smoke.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\biz\ExpenditureRecordController.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list | Select-String -Pattern 'bizexpenditurerecord/(add|edit|delete)'`: listed all four wrappers.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed for 14 authenticated deferred wrappers and 4 no-token checks.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 477 route-path-covered frontend endpoints, 0 read-like missing endpoints, and 83 side-effect-like missing endpoints.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.

### Current Issues

- Real expenditure-record add/edit/delete/account-switch behavior remains deferred because it can affect settlement accounts, statements, payments, finance state, workflow, and rollback behavior.

### Next Plan

- Use `.\scripts\frontend-api-route-gap-smoke.ps1 -ShowMissing` to choose the next controlled-deferred or module-planned write slice.
- Do not implement real finance writes without a transaction and side-effect plan.

## 2026-06-16 12:51 +08:00 - test-agent/frontend-agent - Frontend API Route Gap Scanner

### Completed

- Added `scripts/frontend-api-route-gap-smoke.ps1` to compare copied frontend API wrapper request paths against `php think route:list`.
- The scanner handles `baseRequest` prefixes, `moduleRequest` prefixes, ternary request branches, query-only template fragments, and comment stripping.
- Added summary, `-ShowMissing`, `-Json`, and `-FailOnReadMissing` modes.
- Added `docs/api/frontend-api-route-gap-smoke.md`.
- Updated the API gap map and dashboard with the current scan: 560 unique frontend endpoints, 473 route-path-covered endpoints, 0 missing read-like endpoints, and 87 missing side-effect-like endpoints.
- Added problem-log row `P-034` and included the scanner in `scripts/project-progress.ps1` fast commands.

### Modified Files

- `scripts/frontend-api-route-gap-smoke.ps1`
- `scripts/project-progress.ps1`
- `docs/api/frontend-api-route-gap-smoke.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/problem-optimization-log.md`
- `STATUS.md`

### Test Results

- `.\scripts\frontend-api-route-gap-smoke.ps1`: passed.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -ShowMissing`: passed and listed only side-effect-like missing paths.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed.

### Current Issues

- The scanner proves route-path coverage only; it does not prove business semantics, transaction safety, permission parity, or frontend click safety.
- The remaining 87 gaps are side-effect-like and should stay behind module-specific plans or controlled-deferred wrappers.

### Next Plan

- Use `.\scripts\frontend-api-route-gap-smoke.ps1 -ShowMissing` before choosing the next route slice.
- Continue only with a focused side-effect group that has a module-specific plan, or add controlled-deferred wrappers when the intended behavior must remain intentionally unavailable.

## 2026-06-16 12:23 +08:00 - test-agent/frontend-agent - Workflow Home Team Guard Browser Smoke Refresh

### Completed

- Confirmed runtime, backend, and frontend readiness before browser smoke.
- Ran the upload/provider guard helper against workflow task/process pages: `/biz/biztask`, `/biz/historytask`, `/biz/biztask/mystarttask`, `/biz/biztask/allprocess`, `/biz/copytask`, and `/biz/biztask/processList`.
- Ran the same guard helper against `/index`, `/biz/bizleaveapplication`, `/biz/bizhistoryexcel`, and `/biz/bizteamproject`.
- Improved `scripts/browser-page-smoke.ps1` so object-shaped console errors are expanded before failing.
- Used that diagnostic improvement to distinguish an inaccessible arbitrary team-project id from a page defect, then reran `/biz/bizteamprojectdetails?id=<member-visible local sample>` successfully.
- Updated the upload/provider deferred plan, dashboard, and problem log with the commands and sample-id rule.

### Modified Files

- `scripts/browser-page-smoke.ps1`
- `docs/tasks/upload-provider-deferred-plan.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/problem-optimization-log.md`
- `STATUS.md`

### Test Results

- `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean`: passed.
- `.\scripts\browser-upload-provider-guard-smoke.ps1 -SkipDefaultTargets -TargetPath '/biz/biztask','/biz/historytask','/biz/biztask/mystarttask','/biz/biztask/allprocess','/biz/copytask','/biz/biztask/processList'`: passed.
- `.\scripts\browser-upload-provider-guard-smoke.ps1 -SkipDefaultTargets -TargetPath '/index','/biz/bizleaveapplication','/biz/bizhistoryexcel','/biz/bizteamproject'`: passed.
- `.\scripts\browser-upload-provider-guard-smoke.ps1 -SkipDefaultTargets -TargetPath '/biz/bizteamprojectdetails?id=<member-visible local sample>'`: passed.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed.
- `.\scripts\project-preflight.ps1`: passed, including runtime/web readiness, frontend API method smoke, role/user/business/inventory/finance/purchase/settlement/supplier/product/HR/team-project/datareport/resource/dev/gen/auth/directory/tenant/message-SSE/workflow HTTP smokes, and Git whitespace checks.
- `git diff --check`: passed with only normal LF/CRLF Git warnings.

### Current Issues

- This is browser smoke and diagnostic tooling only; no workflow, HR, team-project, provider, upload, schema, Java source, `.env`, production data, or Git history was changed.
- Team-project detail smoke must use a project visible to the current smoke account; arbitrary local project rows can correctly return `team project not found`.

### Next Plan

- Keep future browser expansion tied to login-menu-backed paths and the upload/provider guard plan, or move to a deliberate provider/file-cleanup/write-flow plan before touching side-effect behavior.

# STATUS.md

## 2026-05-28 17:35 +08:00

Agent: user-agent

### Completed Content

- Started user-agent Phase 1 after db-agent/auth-agent foundations.
- Confirmed `OA-user` worktree is clean before edits.
- Created long-term workflow files for user-agent.
- Analyzed Java user, user-center, org, and position controllers at API level.
- Analyzed primary database tables from `oa2026.sql`.
- Documented module boundaries, route risks, and next implementation order.
# workflow-agent Status

## 2026-05-28 - workflow-agent - Phase 1 Started

### Completed Content

- Read `AGENTS.md`.
- Confirmed `PLANS.md`, `IMPLEMENT.md`, and `STATUS.md` were missing and created them in the workflow worktree.
- Analyzed Java workflow/process source code in read-only mode.
- Analyzed BPMN process definitions.
- Analyzed workflow-related SQL tables in `oa2026.sql`.
- Generated workflow mapping and phase notes documents.
# STATUS.md

## 2026-05-28 18:05 +08:00

Agent: api-agent

### Completed Content

- Started api-agent Phase 1 after db-agent, auth-agent, and user-agent foundations.
- Confirmed `OA-api` worktree is clean before edits.
- Created long-term workflow files for api-agent.
- Inventoried Java Controller files at a project-wide level.
- Documented controller ownership boundaries and route integration risks.
- Kept Phase 1 documentation-only and avoided locked public files.
# STATUS.md

## 2026-05-28 - test-agent - Phase 1 Baseline

### Completed Content

- Read root agent rules from `AGENTS.md`.
- Confirmed missing local workflow files need to be created in the test-agent worktree only.
- Created test-agent workflow files for Plan -> Implement -> Test -> Commit -> Report.
- Created multi-worktree baseline test plan and merge risk list.
- Ran ThinkPHP baseline checks in `F:\AI\projects\testJava\OA-test`.

### Modified Files
# STATUS.md

## 2026-05-28 - docs-agent - Phase 1 Started

## Completed Content

- Confirmed docs-agent worktree path: `F:\AI\projects\testJava\OA-docs`.
- Confirmed current branch: `refactor/docs`.
- Confirmed Java source project exists and remains read-only: `F:\AI\projects\testJava\OA`.
- Confirmed updated SQL reference exists: `F:\AI\projects\testJava\OA\oa2026.sql`.
- Added docs-agent workflow files because `PLANS.md`, `IMPLEMENT.md`, and `STATUS.md` were missing.
- Added multi-agent parallel status, final merge checklist, and post-launch data sync reminder documents.

## Modified Files

- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Previously Added db-agent Files

- `docs/database/table-map.md`
- `docs/database/relation-map.md`
- `docs/database/index-analysis.md`
- `app/model/BaseModel.php`
- `app/model/SysUser.php`
- `app/model/SysRole.php`
- `app/model/SysResource.php`
- `app/model/SysRelation.php`
- `app/model/SysOrg.php`
- `app/model/SysPosition.php`
- `app/model/SysUserProcessConfig.php`
- `app/model/Tenant.php`
- `app/model/AuthThirdUser.php`
- `app/model/ClientUser.php`
- `app/model/ClientRelation.php`
- `app/model/MobileResource.php`

### Test Results

- `Get-ChildItem app\model -Filter *.php | ForEach-Object { php -l $_.FullName }`: passed.
- `composer install --no-interaction --prefer-dist`: passed in `OA-db`; `vendor` remains ignored.
- `composer dump-autoload`: passed.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed.

### Current Issues

- `F:\AI\projects\testJava\OA` currently shows an untracked `oa2026.sql` file. db-agent did not modify or commit Java source project files.
- `refactor/db` is ahead of `origin/refactor/db` by local commits and has not been pushed because remote push was not requested.

### Next Plan

- Wait for confirmation of the active plan in `PLANS.md`.
- After confirmation, start db-agent Phase 2 for high-priority OA business table analysis and passive Model generation.

## 2026-05-28 15:43 +08:00

Agent: db-agent

### Completed Content

- Recorded the updated SQL reference file provided by the user.
- Marked `F:\AI\projects\testJava\OA\oa2026.sql` as the primary read-only SQL source for future db-agent analysis.
- Added a final-stage reminder for online realtime data synchronization into the completed ThinkPHP OA project.
- `docs/tasks/user-agent-java-map.md`
- `docs/tasks/user-agent-phase1-notes.md`
- `docs/api/controller-inventory.md`
- `docs/tasks/api-agent-phase1-notes.md`

### Test Results

- Phase 1 is documentation only.
- `composer install --no-interaction --prefer-dist` restored the local `vendor` directory because it was missing in this worktree.
- `composer dump-autoload` passed.
- `php think` passed and reported ThinkPHP `8.1.4`.
- `php think route:list` passed with only baseline routes in this branch.
- PHP syntax lint passed for `app`, `config`, and `route`.
- `git diff --check` passed.

### Current Issues

- user-agent must not duplicate auth-agent's `GET /sys/userCenter/loginMenu` route.
- User grant role/resource/permission operations overlap auth-agent RBAC data and need a clear boundary.
- Import/export, avatar upload, and encrypted profile fields should be deferred.

### Next Plan

- Implement read-only organization and position tree/query services first.
- Then implement user page/detail selectors.
- Defer write operations, grants, import/export, and uploads until API routing ownership is confirmed.

## 2026-05-29 09:20 +08:00

Agent: user-agent

### Completed Content

- Added read-only user-agent service layer for organization, position, and user directory queries.
- Added a reusable tree builder for Java OA compatible organization trees.
- Kept Phase 2 route-free and controller-free to avoid locked public files.
- Documented db-agent model dependency for the final merge order.

### Current Issues

- `route/app.php` is a locked public file, so route registration must be handled through a public file change request or merge-agent integration step.
- Some Java controllers overlap module agents, especially auth, user, workflow, and database-backed CRUD modules.
- Upload, export, SSE, job, generator, and tenant APIs need separate decisions before implementation.

### Next Plan

- Turn the controller inventory into a route migration queue after module agents confirm service boundaries.
- Add public-file route change requests only when a concrete controller group is ready.
- Keep api-agent focused on controller adapters and API compatibility rather than domain service implementation.

## 2026-05-29 09:35 +08:00

Agent: api-agent

### Completed Content

- Added a read-only user directory route map for organization, position, user, and user-center endpoints.
- Added a public file change request for future `route/app.php` registration.
- Kept Phase 2 documentation-only and did not modify locked public files.
- Explicitly excluded `loginMenu` because auth-agent owns it.
- `docs/tasks/test-agent-baseline.md`
- `docs/tasks/test-agent-risk-list.md`

### Test Results

- Initial `composer dump-autoload` failed because the worktree had an incomplete `vendor` directory and `think\App` was missing.
- `composer install --no-interaction --prefer-dist`: passed after installing dependencies from `composer.lock`.
- `composer dump-autoload`: passed after dependency installation.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed, current routes are `think` and `hello/<name>`.
- PHP lint for `app`, `config`, and `route`: passed with no syntax errors.
- `php think test`: not run because the current console command list does not include `test`.

### Current Issues

- `PLANS.md`, `IMPLEMENT.md`, and `STATUS.md` were missing before this phase and were created.
- No project test runner is configured yet.
- Current test branch only has starter ThinkPHP routes; later module branch merges must rerun the same baseline checks.

### Next Plan

- Commit the test-agent baseline plan.
- After db/auth/user/workflow/api/frontend branches are merged, rerun Composer, ThinkPHP console, route list, and PHP lint checks after each merge.

## 2026-05-29 - test-agent - Phase 2 Integration Test Matrix

### Completed Content

- Added integration test matrix for merge-agent validation.
- Covered Composer, ThinkPHP console, route list, PHP lint, auth response shape, read-only user directory endpoints, and read-only workflow endpoints.
- Kept this phase documentation-only and did not modify locked public files or business code.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `docs/tasks/final-data-sync-reminder.md`
- `docs/tasks/integration-test-matrix.md`

### Test Results

- `composer dump-autoload`: passed.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed.
- `Get-ChildItem app\model -Filter *.php | ForEach-Object { php -l $_.FullName }`: passed.

### Current Issues

- The SQL file is inside the Java source project and must remain read-only.
- Online realtime data sync is a deferred final-stage task and must not be started without a confirmed plan, backup, and user approval.

### Next Plan

- Commit the documentation reminder.
- Continue to wait for confirmation before starting db-agent Phase 2 implementation.

## 2026-05-28 16:05 +08:00

Agent: db-agent

### Completed Content

- Completed db-agent Phase 2 for high-priority OA business tables.
- Used `F:\AI\projects\testJava\OA\oa2026.sql` as the primary read-only SQL reference.
- Analyzed Java `biz` entity/table mappings and generated business table mapping notes.
- Generated passive ThinkPHP Models for 15 dependency-heavy business tables.
- Kept all generated Models database-only, with no controller, service, route, workflow, auth, or frontend logic.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `docs/database/biz-table-map.md`
- `docs/database/biz-model-plan.md`
- `app/model/BizCcRecords.php`
- `app/model/BizFileRelation.php`
- `app/model/BizLeaveApplication.php`
- `app/model/BizPaymentRecord.php`
- `app/model/BizExpenditureRecord.php`
- `app/model/BizPurchaseOrder.php`
- `app/model/BizPurchaseOrderItem.php`
- `app/model/BizSaleProject.php`
- `app/model/BizSaleProjectProductItem.php`
- `app/model/BizTeamProject.php`
- `app/model/BizTeamProjectTask.php`
- `app/model/Customer.php`
- `app/model/Supplier.php`
- `app/model/Warehouses.php`
- `app/model/Inventory.php`

### Test Results

- `Get-ChildItem app\model -Filter *.php | ForEach-Object { php -l $_.FullName }`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed.
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/auth-agent-java-auth-map.md`

### Test Results

- `composer install --no-interaction --prefer-dist`: passed; `vendor` generated locally and remains untracked/ignored.
- `composer dump-autoload`: passed.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed.
- `Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }`: passed.
- `php think test`: not run because the current ThinkPHP console does not expose a `test` command.

### Current Issues

- Phase 2 intentionally did not cover every remaining business/support table; additional low-priority relation and document tables should be handled in a later db-agent slice.
- Online realtime production data synchronization remains a final-stage requirement and must wait until the merged ThinkPHP OA project is complete and a confirmed sync plan exists.
- `refactor/db` remains ahead of `origin/refactor/db`; no push has been performed.

### Next Plan

- Commit db-agent Phase 2.
- Prepare db-agent Phase 3 for remaining database coverage, or move to auth-agent after the user confirms the db-agent foundation is sufficient.

## 2026-05-28 16:28 +08:00

Agent: db-agent

### Completed Content

- Completed db-agent Phase 3 for sales project support tables.
- Used `F:\AI\projects\testJava\OA\oa2026.sql` as the primary read-only SQL reference.
- Analyzed Java sales/product/follow-up/return/warehouse entities and mapper package coverage.
- Generated a sales support table mapping document for later api-agent, workflow-agent, and frontend-agent work.
- Generated passive ThinkPHP Models for 15 sales support tables.
- Kept all generated Models database-only, with no controller, service, route, workflow, auth, or frontend logic.
- `route/app.php` is locked by project rules, but Java-compatible auth endpoints need explicit route declarations.
- `vendor` is not present in the `OA-auth` worktree yet; `composer install --no-interaction --prefer-dist` is required before baseline ThinkPHP checks.
- auth-agent should not implement route or business code until the route change request is confirmed.

### Next Plan

- Run baseline checks after installing Composer dependencies if needed.
- Commit auth-agent planning and public file request.
- Wait for confirmation to edit `route/app.php` before implementing auth endpoints.

## 2026-05-28 16:34 +08:00

Agent: auth-agent

### Completed Content

- Implemented the first Java-compatible auth code slice after the route public-file change was confirmed by the continued instruction.
- Added B-side auth routes for captcha, account login, phone-login placeholder, logout, current user, and safe password verification.
- Added unified JSON response helper matching the project API response convention.
- Added Token service using `Authorization: Bearer <token>` and Redis-compatible `oa:auth:` cache keys.
- Added RBAC service that reads `sys_relation`, `sys_role`, `sys_resource`, and `mobile_resource` to assemble role, permission, menu, button, and mobile button context.
- Added auth controller and middleware scaffolding for later protected routes.
- Documented auth-agent Phase 2 compatibility notes and deferred items.
- `docs/tasks/workflow-agent-java-map.md`
- `docs/tasks/workflow-agent-phase1-notes.md`
- `docs/tasks/workflow-table-map.md`

### Test Results

- `composer dump-autoload`: passed after running `composer install --no-interaction --prefer-dist` because the worktree vendor directory was incomplete.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed, current route baseline only contains `think` and `hello/<name>`.
- PHP lint for `app`, `config`, and `route`: passed.
- `git status --short --branch`: passed; only workflow-agent docs/status files are untracked before commit.
- `php think test`: not run because the current ThinkPHP console does not expose a `test` command.

### Current Problems

- Workflow runtime implementation needs a later decision: Camunda-compatible tables can be read, but Java delegates cannot run in PHP.
- Workflow side effects are spread across finance, sale project, warehouse, procurement, and leave modules.
- Public route/config files remain locked and were not modified.

### Next Plan

- Phase 2 should choose the ThinkPHP workflow runtime strategy before any Controller or Service implementation.

## 2026-05-29 - workflow-agent - Phase 2 Runtime Strategy

### Completed Content

- Documented the recommended workflow runtime strategy.
- Chose a transitional ThinkPHP runtime that keeps existing Camunda `act_*` tables read-compatible.
- Mapped first read-only workflow API batch, config batch, and deferred mutation batch.
- Mapped Java delegate side effects to future explicit PHP services.
- Kept Phase 2 documentation-only and did not modify routes, models, services, or Java source.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `docs/database/sales-support-table-map.md`
- `app/model/BizProduct.php`
- `app/model/ProductRelation.php`
- `app/model/BizSaleProjectInvoice.php`
- `app/model/BizSaleProjectInvoiceItem.php`
- `app/model/BizSaleProjectInvoicing.php`
- `app/model/BizSaleProjectProductInfo.php`
- `app/model/BizSaleProjectReissueOrder.php`
- `app/model/SaleProjectProductItemRelation.php`
- `app/model/SaleProjectFollowUp.php`
- `app/model/CustomerFollowUp.php`
- `app/model/SaleProjectRate.php`
- `app/model/SalesProjectFieldChangeLog.php`
- `app/model/ReturnOrder.php`
- `app/model/ReturnOrderItem.php`
- `app/model/DeliveryRecord.php`

### Test Results

- `Get-ChildItem app\model -Filter *.php | ForEach-Object { php -l $_.FullName }`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed.
- `route/app.php`
- `app/controller/auth/AuthController.php`
- `app/middleware/AuthMiddleware.php`
- `app/service/auth/AuthService.php`
- `app/service/auth/RbacService.php`
- `app/service/auth/TokenService.php`
- `app/support/ApiResponse.php`
- `docs/tasks/auth-agent-phase2-notes.md`

### Test Results

- `Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed and listed the new `/auth/b/*` routes.
- `php think test`: not run because the current ThinkPHP console does not expose a `test` command.

### Current Issues

- Java `ProductRelation` declares `PRODUCT_RELATION`, while the updated SQL dump contains `product_relation`; the ThinkPHP Model uses the SQL physical table name and documents the mismatch.
- Finance/settlement and team collaboration support tables are still deferred to a later db-agent slice.
- Online realtime production data synchronization remains a final-stage requirement and must wait until the merged ThinkPHP OA project is complete and a confirmed sync plan exists.
- `refactor/db` remains ahead of `origin/refactor/db`; no push has been performed.

### Next Plan

- Commit db-agent Phase 3.
- Continue with db-agent Phase 4 for finance and collaboration support tables, or pause db-agent and start auth-agent after user confirmation.

## 2026-05-28 16:45 +08:00

Agent: db-agent

### Completed Content

- Completed db-agent Phase 4 for finance and settlement support tables.
- Used `F:\AI\projects\testJava\OA\oa2026.sql` as the primary read-only SQL reference.
- Analyzed Java collection receipt, debit note, settlement account, and account statement entities.
- Generated a finance settlement table mapping document for later api-agent and workflow-agent work.
- Generated passive ThinkPHP Models for 4 finance/settlement tables.
- Kept all generated Models database-only, with no controller, service, route, workflow, auth, or frontend logic.
- Java password compatibility still needs a dedicated SM2 decrypt plus SM3 hash slice; Phase 2 only isolates password verification and supports direct, PHP password hash, and SHA-256 fallback checks.
- Redis connection/store configuration is not changed in this phase because config files are locked and credentials must not be committed.
- Runtime DB verification requires a configured database using the mapped OA schema.
- Phone-code login and web-push behavior remain deferred.

### Next Plan

- Add Java password compatibility analysis and implementation plan before claiming full login compatibility.
- Decide whether Redis cache store config should be handled by merge-agent/test-agent or through a separate public-file change request.
- Continue with auth-agent menu tree shaping only after frontend-agent confirms required frontend route schema.

## 2026-05-28 16:45 +08:00

Agent: auth-agent

### Completed Content

- Analyzed Java password flow from `CommonCryptogramUtil`, `AuthServiceImpl`, and the old frontend SM2 login code.
- Confirmed `oa2026.sql` stores Java-compatible 64-character SM3 hashes in `sys_user.PASSWORD`.
- Added pure PHP SM3 hashing without introducing a Composer dependency.
- Added `PasswordService` and wired login verification through SM3 so imported Java user passwords can be checked.
- Updated safe-password verification to compare the submitted password before opening the short-lived `oa:auth:safe:` cache marker.
- Documented the SM2 boundary without writing any private key or secret into the ThinkPHP project.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `docs/database/finance-table-map.md`
- `app/model/BizCollectionReceipt.php`
- `app/model/BizDebitNote.php`
- `app/model/SettlementAccount.php`
- `app/model/SettlementAccountStatement.php`

### Test Results

- `Get-ChildItem app\model -Filter *.php | ForEach-Object { php -l $_.FullName }`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed.
- `app/service/auth/AuthService.php`
- `app/service/auth/PasswordService.php`
- `app/service/auth/Sm3Hasher.php`
- `docs/tasks/auth-agent-phase3-password-compat.md`

### Test Results

- `php -r "require 'vendor/autoload.php'; echo app\\service\\auth\\Sm3Hasher::hash('abc');"`: passed, matched the standard SM3 test vector.
- `php -r "require 'vendor/autoload.php'; echo app\\service\\auth\\Sm3Hasher::hash('<sample-password>');"`: passed, matched the default password hash in `oa2026.sql`.
- `PasswordService::verify('<sample-password>', <SQL default hash>)`: passed.
- `Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed and auth routes remained registered.
- `php think test`: not run because the current ThinkPHP console does not expose a `test` command.

### Current Issues

- `settlement_account.org` is lower-case in the SQL dump; the ThinkPHP Model documents and preserves this spelling.
- Team collaboration support tables are still deferred to a later db-agent slice.
- Online realtime production data synchronization remains a final-stage requirement and must wait until the merged ThinkPHP OA project is complete and a confirmed sync plan exists.
- `refactor/db` remains ahead of `origin/refactor/db`; no push has been performed.

### Next Plan

- Commit db-agent Phase 4.
- Continue with db-agent Phase 5 for team collaboration support tables, or pause db-agent and start auth-agent after user confirmation.

## 2026-05-28 17:02 +08:00

Agent: db-agent

### Completed Content

- Completed db-agent Phase 5 for team collaboration support tables.
- Used `F:\AI\projects\testJava\OA\oa2026.sql` as the primary read-only SQL reference.
- Analyzed Java team project comment, reply, task comment, task category, team user, and task user relation entities.
- Generated a team collaboration table mapping document for later api-agent, auth-agent, and workflow-agent work.
- Generated passive ThinkPHP Models for 6 team collaboration tables.
- Kept all generated Models database-only, with no controller, service, route, workflow, auth, or frontend logic.
- Existing Java frontend SM2 ciphertext is detected but not decrypted yet, because no SM2 private key or secret material may be committed.
- Full legacy frontend compatibility needs a secure SM2 adapter or a frontend-agent login adaptation over HTTPS.
- Runtime DB verification still requires configured database/cache services.

### Next Plan

- Continue auth-agent with menu/tree permission shaping and endpoint response compatibility.
- Keep Redis store wiring as a later public-file/config decision unless explicitly approved.
- After auth-agent reaches a stable checkpoint, move to user-agent according to the staged order.

## 2026-05-28 16:52 +08:00

Agent: auth-agent

### Completed Content

- Analyzed the old frontend login flow after successful token creation.
- Confirmed the frontend calls `GET /auth/b/getLoginUser`, then `GET /sys/userCenter/loginMenu`.
- Confirmed Java builds the login menu from user/role resource relations and returns a tree for the frontend router.
- Identified a module-boundary issue: menu permission data belongs to auth-agent, but the compatibility route path belongs to user center.
- Updated the public-file change request with ownership options for `GET /sys/userCenter/loginMenu`.
- Added a pending Phase 4 plan and did not modify route or business code.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `docs/database/team-collaboration-table-map.md`
- `app/model/BizTeamProjectComment.php`
- `app/model/BizTeamProjectCommentReply.php`
- `app/model/BizTeamProjectTaskComment.php`
- `app/model/BizTeamProjectTaskCategory.php`
- `app/model/BizTeamProjectUser.php`
- `app/model/BizTeamProjectTaskUser.php`

### Test Results

- `Get-ChildItem app\model -Filter *.php | ForEach-Object { php -l $_.FullName }`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed.
- `php think test`: not run because the current ThinkPHP console does not expose a `test` command.

### Current Issues

- Role and permission interpretation for team collaboration rows is intentionally deferred to auth-agent/api-agent.
- Remaining lower-priority support tables still need a short audit before deciding whether db-agent is complete.
- Online realtime production data synchronization remains a final-stage requirement and must wait until the merged ThinkPHP OA project is complete and a confirmed sync plan exists.
- `refactor/db` remains ahead of `origin/refactor/db`; no push has been performed.

### Next Plan

- Commit db-agent Phase 5.
- Run db-agent Phase 6 remaining support table audit, then decide whether to stop db-agent and begin auth-agent.

## 2026-05-28 17:14 +08:00

Agent: db-agent

### Completed Content

- Completed db-agent Phase 6 remaining support table audit.
- Compared Java biz entities against generated ThinkPHP Models.
- Documented remaining low-priority or shared tables that are not required for auth-agent startup.
- Decided that db-agent can pause and hand over to auth-agent.
- Kept this phase documentation-only, with no controller, service, route, workflow, auth, or frontend logic.
- `docs/tasks/public-file-change-request.md`

### Test Results

- No code was changed in this boundary-planning step.
- Previous Phase 3 checks remain passing in this clean worktree state.
- `git status --short --branch`: will be checked before committing this planning update.

### Current Issues

- `GET /sys/userCenter/loginMenu` requires an ownership decision before implementation because it may overlap auth-agent and user-agent responsibilities.
- Implementing the compatibility route requires modifying locked `route/app.php`.

### Next Plan

- Wait for confirmation on the login menu route ownership option.
- If confirmed for auth-agent, implement only menu tree compatibility and avoid user profile/organization/workbench APIs.
- If deferred to user-agent or merge-agent, keep auth-agent code as login, token, password, RBAC, and permission foundation.

## 2026-05-28 17:13 +08:00

Agent: auth-agent

### Completed Content

- Started multi-agent parallel work with user-agent, test-agent, and docs-agent in separate worktrees.
- Implemented auth-owned compatibility for `GET /sys/userCenter/loginMenu`.
- Added menu tree building from `SYS_USER_HAS_RESOURCE` and `SYS_ROLE_HAS_RESOURCE`.
- Added parent menu/module inclusion and frontend-compatible `meta` fields for router loading.
- Kept user center ownership narrow: no user profile, organization, position, workbench, process config, or message APIs were implemented.
- Updated the public-file change request status for the single route addition.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `docs/database/remaining-table-audit.md`

### Test Results

- `Get-ChildItem app\model -Filter *.php | ForEach-Object { php -l $_.FullName }`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed.
- `route/app.php`
- `app/controller/auth/UserCenterAuthController.php`
- `app/service/auth/MenuService.php`
- `docs/tasks/auth-agent-phase4-menu-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed and listed `sys/userCenter/loginMenu`.
- `php think test`: not run because the current ThinkPHP console does not expose a `test` command.

### Current Issues

- Remaining unmapped low-priority tables include `biz_draft`, `biz_history_excel`, `biz_payroll`, `biz_user_vacation`, `BIZ_RELATION`, `DEV_FILE`, and `DEV_DICT`.
- These do not block auth-agent startup and can be handled by later agents or a small db-agent follow-up if a concrete dependency appears.
- Online realtime production data synchronization remains a final-stage requirement and must wait until the merged ThinkPHP OA project is complete and a confirmed sync plan exists.
- `refactor/db` remains ahead of `origin/refactor/db`; no push has been performed.

### Next Plan

- Commit db-agent Phase 6 audit.
- Start auth-agent next in `F:\AI\projects\testJava\OA-auth` after confirming branch/worktree status and syncing the latest db-agent foundation strategy into the handoff plan.

## 2026-05-29 09:55 +08:00

Agent: db-agent

### Completed Content

- Completed db-agent Phase 7 workflow engine table model coverage.
- Used `F:\AI\projects\testJava\OA\oa2026.sql` as the primary read-only SQL reference.
- Added passive ThinkPHP Models for Camunda-style `act_*` runtime, repository, and history tables.
- Added `ActBaseModel` with `ID_` as the primary key for Camunda-compatible tables.
- Documented workflow engine model coverage and final data sync implications.
- Kept this phase database/model-only with no controller, service, route, workflow runtime, or side-effect logic.
- Runtime DB verification still requires a configured OA database and cache store.
- The rest of `/sys/userCenter/*` remains for user-agent.
- If user-agent later implements a richer `loginMenu`, merge-agent must keep only one route and compare output compatibility.

### Next Plan

- Commit auth-agent Phase 4.
- Wait for user-agent/test-agent/docs-agent reports.
- Review parallel agent outputs before starting the next module slice.

## 2026-05-29 10:25 +08:00

Agent: auth-agent

### Completed Content

- Added frontend-compatible `msg` field to unified API responses.
- Preserved the existing `message` field and response shape.
- Kept the change limited to response compatibility with no auth/token/RBAC behavior changes.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `docs/database/workflow-engine-models.md`
- `app/model/ActBaseModel.php`
- `app/model/ActGeBytearray.php`
- `app/model/ActReDeployment.php`
- `app/model/ActReProcdef.php`
- `app/model/ActRuExecution.php`
- `app/model/ActRuTask.php`
- `app/model/ActRuVariable.php`
- `app/model/ActRuIdentitylink.php`
- `app/model/ActHiProcinst.php`
- `app/model/ActHiTaskinst.php`
- `app/model/ActHiVarinst.php`
- `app/model/ActHiActinst.php`
- `app/model/ActHiComment.php`
- `app/model/ActHiIdentitylink.php`

### Test Results

- `Get-ChildItem app\model -Filter *.php | ForEach-Object { php -l $_.FullName }`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed.
- `php think test`: not run because the current ThinkPHP console does not expose a `test` command.

### Current Issues

- These models are passive wrappers only; workflow runtime behavior remains workflow-agent scope.
- Active process/task data must be included in the final online realtime data synchronization plan after project completion.

### Next Plan

- Commit db-agent Phase 7.
- workflow-agent can later build read-only query services on these models after final merge order brings db-agent first.
- `app/support/ApiResponse.php`
- `docs/tasks/workflow-runtime-design.md`
- `docs/tasks/workflow-api-map.md`
- `docs/tasks/workflow-side-effect-map.md`

### Test Results

- `composer dump-autoload`: passed.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed and listed auth/login/menu routes.
- PHP lint for `app`, `config`, and `route`: passed.

### Current Issues

- Old frontend and new backend docs now both have a response message field, but later frontend-agent should decide whether to keep dual fields permanently or remove `msg` after frontend migration.

### Next Plan

- Run baseline checks.
- Commit auth-agent Phase 5.
- `app/service/user/TreeBuilder.php`
- `app/service/user/OrgService.php`
- `app/service/user/PositionService.php`
- `app/service/user/UserDirectoryService.php`
- `docs/tasks/user-agent-phase2-services.md`
- `docs/api/user-directory-route-map.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `composer dump-autoload` passed.
- `php think` passed and reported ThinkPHP `8.1.4`.
- `php think route:list` passed with only baseline routes in this branch.
- PHP syntax lint passed for `app`, `config`, and `route`.
- `TreeBuilder` smoke test passed with a root-child sample tree.

### Current Issues

- Runtime DB-backed service testing must wait until `refactor/db` is merged before `refactor/user`.
- Controller and route integration still requires a public file change request or merge-agent step.
- Write operations, grants, import/export, avatar/signature upload, and process config edits remain deferred.

### Next Plan

- Add route/controller change request for read-only user/org/position endpoints.
- After approval, let api-agent or merge-agent wire routes to these services.
- Keep auth/RBAC/menu behavior owned by auth-agent.
- `php think route:list`: passed, current route baseline only contains `think` and `hello/<name>`.
- PHP lint for `app`, `config`, and `route`: passed.

### Current Problems

- Runtime workflow code is still blocked until db/auth/user foundations are merged and tested.
- Route registration still requires a public file change request before modifying `route/app.php`.
- Approval mutation and side effects remain high risk and must be implemented process by process.

### Next Plan

- Start workflow code with read-only query services only after db-agent model coverage for `act_*` tables is confirmed.
- Defer approve/reject and process start routes until test-agent has baseline route/task checks.

## 2026-05-29 - workflow-agent - Phase 3 Query Services

### Completed Content

- Added read-only workflow query services.
- Added variable normalization for runtime and historic Camunda variables.
- Covered pending task count/list/page, historic task page, started process page, and process detail query shape.
- Kept Phase 3 free of routes, controllers, approve/reject/cancel/start behavior, and business side effects.
- Documented dependency on db-agent `Act*` models.

### Current Issues

- `route/app.php` remains locked; route registration is pending confirmation or merge-agent action.
- Controller implementation should wait until user-agent services are merged after db-agent and auth-agent.
- Response compatibility still needs one final decision for frontend `msg` versus backend `message`.

### Next Plan

- After route change approval, add thin controller adapters that delegate to user-agent services.
- Keep actual domain behavior inside user-agent services.
- Continue API mapping for workflow and business modules only after their service boundaries are stable.

## 2026-05-29 10:45 +08:00

Agent: api-agent

### Completed Content

- Added thin read-only Controller adapters for organization, position, user, and user-center directory endpoints.
- Kept controllers as delegation only; user service behavior remains user-agent scope.
- Did not modify `route/app.php`; route registration remains pending through the documented public file change request.
- Documented controller dependencies on auth-agent response helper and user-agent services.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/workflow/WorkflowVariableService.php`
- `app/service/workflow/WorkflowQueryService.php`
- `docs/tasks/workflow-query-services.md`
- `app/controller/sys/BaseSysController.php`
- `app/controller/sys/OrgController.php`
- `app/controller/sys/PositionController.php`
- `app/controller/sys/UserController.php`
- `app/controller/sys/UserCenterController.php`
- `docs/api/user-directory-controller-adapters.md`

### Test Results
- `docs/tasks/parallel-agent-status.md`
- `docs/tasks/final-merge-checklist.md`
- `docs/tasks/post-launch-data-sync-reminder.md`

## Test Results

- `git status --short --branch`: passed; only docs-agent documentation files are untracked before commit.
- `composer install --no-interaction --prefer-dist`: passed; dependencies installed because `vendor/autoload.php` was missing.
- `composer dump-autoload`: passed.
- `php think`: passed; ThinkPHP console starts and reports version 8.1.4.
- `php think route:list`: passed; default ThinkPHP routes are listed.

## Current Issues

- `composer dump-autoload`, `php think`, and `php think route:list` initially failed before dependencies were installed because `vendor/autoload.php` was missing.
- After `composer install --no-interaction --prefer-dist`, the checks passed.
- No business code or locked public files were modified.

## Next Plan

- Commit documentation changes without pushing.
- Continue docs-agent later with API/deployment documentation after module Agents provide stable outputs.

## 2026-05-29 - docs-agent - Phase 2 Autonomous Execution Rules

## Completed Content

- Confirmed all module worktrees are clean and synced with remote after push:
  - `refactor/db`
  - `refactor/auth`
  - `refactor/user`
  - `refactor/workflow`
  - `refactor/api`
  - `refactor/frontend`
  - `refactor/test`
  - `refactor/docs`
- Added autonomous execution rules for the main control Agent.
- Added copyable user authorization text for safe long-running autonomous work.

## Modified Files

- `PLANS.md`
- `STATUS.md`
- `docs/tasks/autonomous-execution-rules.md`
- `docs/tasks/parallel-agent-status.md`

## Test Results

- `composer dump-autoload`: passed.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed, current route baseline only contains `think` and `hello/<name>`.
- PHP lint for `app`, `config`, and `route`: passed.

### Current Problems

- Runtime DB query testing must wait until `refactor/db` is merged before `refactor/workflow`.
- API routes are still not registered and require a public file change request.
- Mutation behavior and Java delegate replacement remain deferred.

### Next Plan

- Add workflow public route change request for the read-only API batch.
- After merged model/service validation, add thin controller adapters that call these services.
- `php think route:list`: passed with only baseline routes in this branch.
- PHP lint for `app`, `config`, and `route`: passed.

### Current Issues

- Runtime validation must wait for final merge because this branch does not yet contain auth-agent `ApiResponse` or user-agent services.
- Route registration remains pending authorization because `route/app.php` is locked.

### Next Plan

- Wait for documented route authorization or merge-agent action before modifying `route/app.php`.
- Continue API mapping for workflow read-only endpoints after workflow route request is prepared.

## 2026-05-29 11:20 +08:00

Agent: api-agent

### Completed Content

- Added thin read-only Controller adapters for workflow task and process query endpoints.
- Added a public file change request section for workflow read-only route registration.
- Kept controllers as delegation only; workflow behavior remains workflow-agent scope.
- Did not modify `route/app.php`.
- Explicitly excluded approve, reject, cancel, process start, delegate side effects, SSE, and file operations.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/BaseWorkflowController.php`
- `app/controller/biz/TaskController.php`
- `app/controller/biz/ProcessController.php`
- `docs/api/workflow-readonly-controller-adapters.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `composer dump-autoload`: passed.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed with only baseline routes in this branch.
- PHP lint for `app`, `config`, and `route`: passed.

### Current Issues

- Runtime validation must wait for final merge because this branch does not yet contain auth-agent `ApiResponse` or workflow-agent services.
- Workflow route registration remains pending authorization because `route/app.php` is locked.

### Next Plan

- Continue with frontend-agent contract notes for newly prepared user/workflow endpoints.
- Leave actual route registration to documented approval or merge-agent.
- `php think route:list`: passed, current routes are `think` and `hello/<name>`.
- PHP lint for `app`, `config`, and `route`: passed with no syntax errors.

### Current Issues

- Endpoint runtime tests must wait until module branches are merged and routes are registered.
- Database/cache-backed checks require configured OA database and Redis/cache.

### Next Plan

- Rerun baseline checks.
- Commit test-agent Phase 2.

## Current Issues

- Route registration and final merge still need either explicit user authorization or documented approval scope.
- Destructive operations, Java source edits, database schema changes, secrets, and production data synchronization must remain stop conditions.

## Next Plan

- Commit docs-agent Phase 2.
- Continue implementation only inside approved agent scopes.

## 2026-05-29 - merge-agent - Runtime Verification Readiness

### Completed Content

- Checked merged `refactor/thinkphp-main` runtime prerequisites after final branch integration.
- Confirmed PHP has `pdo_mysql`, `mysqli`, and `redis` extensions.
- Confirmed `F:\AI\projects\testJava\OA\oa2026.sql` exists and remains read-only.
- Confirmed no `.env` file exists in `F:\AI\projects\testJava\OA-ThinkPHP`.
- Confirmed `mysql` and `redis-cli` are not available in the current PATH.
- Confirmed Windows service `MySQL80` exists but is stopped.
- Added Redis store support to `config/cache.php` while keeping the default cache driver as `file`.
- Added `docs/tasks/runtime-verification-plan.md` for safe local database import and smoke testing.

### Modified Files

- `config/cache.php`
- `docs/tasks/runtime-verification-plan.md`
- `STATUS.md`

### Test Results

- `composer dump-autoload`: passed.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed, 28 routes listed.
- PHP lint for `app`, `config`, and `route`: passed.
- `git diff --check`: passed, with Git line-ending normalization warnings only.

### Current Issues

- Runtime endpoint testing is blocked until a local `.env`, local MySQL database, and Redis runtime are configured.
- The SQL import is a database-modifying action and must not be executed automatically without explicit confirmation.
- Online realtime production data sync remains deferred until the project is complete and accepted.

### Next Plan

- Run non-destructive ThinkPHP checks after the Redis cache configuration update.
- Commit and push the runtime readiness update if checks pass.
- Wait for explicit confirmation before starting MySQL, creating a database, importing `oa2026.sql`, or writing `.env`.

## 2026-05-29 - merge-agent - Local Database And Redis Runtime

### Completed Content

- Accepted the user-designated long-term local runtime database configuration for this project.
- Confirmed actual secrets are stored only in ignored local `.env`; no password is committed.
- Confirmed `F:\project\socket\AI\testPhp\files\tools\mysql\bin\mysql.exe` is usable.
- Confirmed MySQL server version `8.0.45`.
- Created local database `phpoa20026`.
- Imported `F:\AI\projects\testJava\OA\oa2026.sql` into `phpoa20026`.
- Confirmed imported table count is 121.
- Confirmed key table counts:
  - `sys_user`: 121
  - `sys_org`: 55
  - `sys_position`: 79
  - `sys_role`: 32
  - `sys_resource`: 272
  - `sys_relation`: 3894
  - `act_ru_task`: 77
  - `act_hi_procinst`: 2915
- Confirmed Redis `PING` with authentication returns `PONG`.
- Confirmed ThinkPHP can read `sys_user` and write/read/delete a Redis cache probe.
- Started ThinkPHP dev server at `http://127.0.0.1:8000`.
- Ran HTTP smoke checks for captcha, organization tree, user page, task count/page, and process page.

### Modified Files

- `config/cache.php`
- `docs/tasks/runtime-verification-plan.md`
- `STATUS.md`
- `.env` local only, ignored by Git and not committed

### Test Results

- `composer dump-autoload`: passed.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed.
- `php runtime/probe-runtime.php`: passed with DB and Redis probes.
- HTTP smoke checks returned `code=200` for:
  - `GET /auth/b/getPicCaptcha`
  - `GET /sys/org/tree`
  - `GET /sys/user/page?pageNo=1&pageSize=2`
  - `GET /biz/task/count?userId=1894269031672111106`
  - `GET /biz/task/page?userId=1894269031672111106&pageNo=1&pageSize=2`
  - `GET /biz/process/page?userId=1894269031672111106&pageNo=1&pageSize=2`

### Current Issues

- `startServer1.bat` was not found under `F:\project\socket\AI\testPhp\files\tools\mysql`, but MySQL and Redis were already running and reachable.
- Real login flow still needs an explicit user-provided test account/password or explicit approval to test an imported account.
- Online realtime production data sync remains deferred until the project is complete and accepted.

### Next Plan

- Commit and push the non-secret runtime configuration/documentation update.
- Keep `.env`, import wrapper, probe scripts, and logs local and ignored.
- Continue with login/API compatibility only after a safe test account is confirmed.

## 2026-05-29 - merge-agent - Auth Token Smoke Test

### Completed Content

- Used the user-provided `bizAdmin` test account for local auth smoke testing.
- Confirmed the account exists in `sys_user`, is enabled, belongs to tenant `1`, and is not deleted.
- Verified login returns a 64-character token.
- Verified `GET /auth/b/getLoginUser` returns the current user and auth context.
- Verified `GET /sys/userCenter/loginMenu` returns authorized top-level menus.
- Verified `GET /auth/b/doLogout` revokes the token.
- Verified reusing the same token after logout returns `401 unauthenticated`.

### Modified Files

- `docs/tasks/runtime-verification-plan.md`
- `STATUS.md`

### Test Results

- `POST /auth/b/doLogin`: `code=200`.
- `GET /auth/b/getLoginUser`: `code=200`, account `bizAdmin`, roles `1`, permissions `205`.
- `GET /sys/userCenter/loginMenu`: `code=200`, top-level menus `2`.
- `GET /auth/b/doLogout`: `code=200`.
- `GET /auth/b/getLoginUser` after logout with the same token: `code=401`.

### Current Issues

- The user-provided login password was used only in local shell memory for the smoke test and was not written to tracked files.
- Full frontend login compatibility still needs browser/frontend-agent verification.

### Next Plan

- Commit and push the non-sensitive auth smoke test record.
- Continue with frontend/API compatibility checks against the running local backend.

## 2026-05-29 - merge-agent - Frontend Token Route Compatibility

### Completed Content

- Ran frontend-style API smoke checks with a valid bearer token.
- Found token-only requests failed with `missing userId` on current-user-dependent user center and workflow routes.
- Confirmed the cause: controllers expected `auth_payload` from middleware, but the route groups did not attach `AuthMiddleware`.
- Added `AuthMiddleware` to:
  - `sys/userCenter`
  - `biz/task`
  - `biz/process`
- Kept the fix limited to route middleware wiring; no Controller or Service business logic was changed.
- Documented the public route file change in `docs/tasks/public-file-change-request.md`.

### Modified Files

- `route/app.php`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/runtime-verification-plan.md`
- `STATUS.md`

### Test Results

- Before fix:
  - `GET /sys/userCenter/loginOrgTree` with token: `400 missing userId`.
  - `GET /sys/userCenter/loginPositionInfo` with token: `400 missing userId`.
  - `GET /biz/task/count` with token: `400 missing userId`.
  - `GET /biz/task/page` with token: `400 missing userId`.
  - `GET /biz/process/page` with token: `400 missing userId`.
- After fix:
  - `GET /sys/userCenter/loginOrgTree` with token: `code=200`.
  - `GET /sys/userCenter/loginPositionInfo` with token: `code=200`.
  - `GET /biz/task/count` with token: `code=200`.
  - `GET /biz/task/page` with token: `code=200`.
  - `GET /biz/process/page` with token: `code=200`.
  - Protected route checks without token return `code=401 unauthenticated`.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed.
- PHP lint for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with Git line-ending normalization warnings only.

### Current Issues

- Full browser-based old frontend verification is still pending.
- Mutation workflow endpoints are still intentionally deferred.

### Next Plan

- Commit and push this route middleware compatibility fix.
- Continue frontend-agent verification for old frontend request/response assumptions.

## 2026-05-29 - merge-agent - Frontend Read-Only Selector Compatibility

### Completed Content

- Compared old Vue frontend system API modules with current ThinkPHP routes.
- Added missing read-only selector/list aliases for user, organization, position, role selector, and user-center list-by-id helpers.
- Kept the change limited to compatibility endpoints and did not implement write, import/export, upload, grant, or workflow mutation behavior.
- Removed password hashes from user directory responses.
- Documented the locked `route/app.php` change as a public file change request.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/user/UserDirectoryService.php`
- `app/service/user/OrgService.php`
- `app/service/user/PositionService.php`
- `app/controller/sys/UserController.php`
- `app/controller/sys/OrgController.php`
- `app/controller/sys/PositionController.php`
- `app/controller/sys/UserCenterController.php`
- `route/app.php`
- `docs/api/frontend-readonly-selector-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the new read-only selector/list routes.
- PHP lint for `app`, `config`, and `route`: passed.
- HTTP smoke checks with a valid bearer token returned `code=200` for:
  - `GET /sys/user/orgTreeSelector`
  - `GET /sys/user/positionSelector`
  - `GET /sys/user/roleSelector`
  - `GET /sys/user/userSelector`
  - `GET /sys/org/page`
  - `GET /sys/org/list`
  - `GET /sys/org/userSelector`
  - `GET /sys/position/list`
  - `GET /sys/position/orgTreeSelector`
  - `POST /sys/userCenter/getOrgListByIdList`
  - `POST /sys/userCenter/getRoleListByIdList`
  - `GET /sys/userCenter/getAvatarById`
- `GET /sys/user/page?pageSize=1` omits the `PASSWORD` field.

### Current Issues

- Full browser-based frontend verification is still pending.
- Write endpoints, grants, uploads, imports, exports, process config, user-center workbench/message, and workflow mutations remain deferred.

### Next Plan

- Run Composer/ThinkPHP/PHP lint checks.
- Run token-based HTTP smoke checks for the newly added endpoints.
- Commit and push if checks pass.

## 2026-05-29 - merge-agent - Protect System Directory Routes

### Completed Content

- Added `AuthMiddleware` to read-only system directory route groups:
  - `sys/org`
  - `sys/position`
  - `sys/user`
- Kept the change limited to route protection. No Controller, Service, Model, database, Java source, or write endpoint behavior was changed.
- Documented the locked `route/app.php` change in the public file change request log.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime token/no-token smoke checks passed:
  - `GET /sys/org/tree`: token `200`, no token `401`
  - `GET /sys/position/page?pageSize=1`: token `200`, no token `401`
  - `GET /sys/user/page?pageSize=1`: token `200`, no token `401`

### Current Issues

- Existing unauthenticated smoke checks for system directory routes must now send a bearer token.

### Next Plan

- Verify token requests return `200` and no-token requests return `401`.
- Commit and push if checks pass.

## 2026-05-29 - merge-agent - RBAC Role Read-Only Compatibility

### Completed Content

- Analyzed the old Vue `sys/role` API module and Java `SysRoleController`.
- Added read-only role service/controller adapters for role page, detail, existing grants, and selector trees.
- Registered protected `/sys/role/*` GET routes behind `AuthMiddleware`.
- Kept all role write and grant mutation endpoints deferred.
- Documented the public route change and read-only compatibility scope.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/auth/RoleService.php`
- `app/controller/sys/RoleController.php`
- `route/app.php`
- `docs/api/rbac-role-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected `/sys/role/*` read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke checks with a valid bearer token returned `code=200` for:
  - `GET /sys/role/page`
  - `GET /sys/role/detail`
  - `GET /sys/role/ownResource`
  - `GET /sys/role/ownMobileMenu`
  - `GET /sys/role/ownPermission`
  - `GET /sys/role/ownUser`
  - `GET /sys/role/orgTreeSelector`
  - `GET /sys/role/resourceTreeSelector`
  - `GET /sys/role/mobileMenuTreeSelector`
  - `GET /sys/role/permissionTreeSelector`
  - `GET /sys/role/roleSelector`
  - `GET /sys/role/userSelector`
- `GET /sys/role/page` without a bearer token returned `code=401`.

### Current Issues

- `permissionTreeSelector` derives available API permission targets from existing `sys_relation` data until route-level permission metadata is modeled in ThinkPHP.
- Grant mutations still need a later dedicated implementation with validation and audit behavior.

### Next Plan

- Run Composer/ThinkPHP/PHP lint checks.
- Run token-based HTTP smoke checks for representative `/sys/role/*` routes.
- Commit and push if checks pass.

## 2026-05-29 - merge-agent - Auth SM2 Transport Compatibility

### Completed Content

- Analyzed old Vue login SM2 transport and Java decrypt-then-SM3 behavior.
- Added an optional pure PHP SM2 decrypt adapter for C1C3C2 ciphertext.
- Updated password verification flow so SM2-looking passwords are decrypted only when `AUTH_SM2_PRIVATE_KEY` is configured at runtime.
- Preserved plaintext local login support for smoke testing.
- Documented that private key material must stay out of Git and tracked docs.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/auth/Sm2Decryptor.php`
- `app/service/auth/PasswordService.php`
- `app/service/auth/AuthService.php`
- `docs/api/auth-sm2-compatibility.md`
- `docs/tasks/runtime-verification-plan.md`

### Test Results

- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed.
- PHP lint for `app`, `config`, and `route`: passed.
- Plaintext local login smoke still returns `code=200` and a 64-character token.
- SM2-looking ciphertext without `AUTH_SM2_PRIVATE_KEY` returns `code=400` with a clear runtime configuration message.

### Current Issues

- SM2 encrypted browser login still needs runtime testing with a local/deployment-only private key.
- The legacy Java key pair should be reviewed and likely rotated before production.

### Next Plan

- Run baseline checks and plaintext login smoke.
- Confirm no private key or password was committed.
- Commit and push if checks pass.

## 2026-05-29 - merge-agent - User Center Read-Only Compatibility

### Completed Content

- Analyzed Java `SysUserCenterController`, `SysUserServiceImpl`, `SysUserProcessConfigServiceImpl`, and `DevMessageServiceImpl`.
- Added read-only compatibility for login workbench, current user process config, login unread message page, and message detail lookup.
- Kept Java message detail mark-read behavior deferred so this phase remains read-only.
- Registered protected user-center routes and documented the locked route-file change.
- Documented old-frontend compatibility behavior and deferred write endpoints.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/user/UserDirectoryService.php`
- `app/controller/sys/UserCenterController.php`
- `route/app.php`
- `docs/api/user-center-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the new `/sys/userCenter/*` read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned:
  - `GET /sys/userCenter/loginWorkbench`: `code=200`
  - `POST /sys/userCenter/process/config`: `code=200`, 9 default process config items for the current test user
  - `GET /sys/userCenter/loginUnreadMessagePage`: `code=200`
  - `GET /sys/userCenter/loginUnreadMessageDetail?id=missing`: `code=200`, `data=null`
  - `GET /sys/userCenter/loginWorkbench` without a token: `code=401`
- Secret scan found no committed database password, superadmin password, SM2 private key, or SM2 public key in tracked project paths.

### Current Issues

- The current test superadmin has no login message records in `dev_relation`, so an existing-message detail smoke still needs a user account with message relations.
- Message detail is intentionally read-only and does not mark messages as read yet.

### Next Plan

- Commit and push this read-only user-center compatibility slice.
- Continue with the next small compatibility slice after reviewing old frontend API usage, likely index message/workbench shortcuts or safe user-center write endpoints with explicit validation.

## 2026-05-29 - merge-agent - Index Read-Only Compatibility

### Completed Content

- Analyzed old Vue `indexApi.js` and Java `SysIndexController` / `SysIndexServiceImpl`.
- Added read-only homepage schedule list, message list/page/detail, visit log list, and operation log list endpoints.
- Reused the user-center message lookup path so message detail remains read-only and does not mark messages as read.
- Deferred schedule add/delete, all-message-mark-read, and SSE routes.
- Documented the route-file change and endpoint behavior.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/user/UserDirectoryService.php`
- `app/service/sys/IndexService.php`
- `app/controller/sys/IndexController.php`
- `route/app.php`
- `docs/api/index-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected `/sys/index/*` read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /sys/index/schedule/list?scheduleDate=2026-05-29`
  - `GET /sys/index/message/list`
  - `GET /sys/index/message/page`
  - `GET /sys/index/message/detail?id=missing`
  - `GET /sys/index/visLog/list`
  - `GET /sys/index/opLog/list`
- `GET /sys/index/message/list` without a token returned `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, or SM2 public key in tracked project paths.

### Current Issues

- Current test superadmin message and schedule lists are empty in the imported SQL data.
- SSE and message mark-read behavior remain deferred because they are not read-only.

### Next Plan

- Commit and push this index read-only compatibility slice.
- Continue frontend compatibility by scanning remaining old API modules for read-only endpoints with high page-load impact.

## 2026-05-29 - merge-agent - System Resource Read-Only Compatibility

### Completed Content

- Analyzed old Vue module/menu/button API usage and Java `SysModuleController`, `SysMenuController`, and `SysButtonController`.
- Added read-only compatibility for module page/detail, menu page/tree/detail/selectors, and button page/detail.
- Registered protected `/sys/module/*`, `/sys/menu/*`, and `/sys/button/*` GET routes behind `AuthMiddleware`.
- Kept module/menu/button add, edit, delete, menu change-module, and grant mutations deferred.
- Documented the locked route-file change and endpoint behavior.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/sys/ResourceService.php`
- `app/controller/sys/ModuleController.php`
- `app/controller/sys/MenuController.php`
- `app/controller/sys/ButtonController.php`
- `route/app.php`
- `docs/api/resource-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected system resource read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /sys/module/page`
  - `GET /sys/module/detail`
  - `GET /sys/menu/page`
  - `GET /sys/menu/tree`
  - `GET /sys/menu/moduleSelector`
  - `GET /sys/menu/menuTreeSelector`
  - `GET /sys/button/page`
  - `GET /sys/button/detail`
- `GET /sys/module/page` without a token returned `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.

### Current Issues

- System resource write endpoints remain deferred because they mutate menu/button permission state and need validation/audit rules.
- The old field management API is not implemented yet; no matching Java controller was found in the scanned system resource package.

### Next Plan

- Commit and push this resource read-only compatibility slice.
- Continue frontend compatibility scanning for the next high-impact read-only API group before considering safe write endpoints.

## 2026-05-29 - merge-agent - Mobile Resource Read-Only Compatibility

### Completed Content

- Analyzed old Vue mobile resource API modules and Java `MobileModuleController`, `MobileMenuController`, and `MobileButtonController`.
- Added read-only compatibility for mobile module page/detail, mobile menu tree/detail/selectors, and mobile button page/detail.
- Registered protected `/mobile/module/*`, `/mobile/menu/*`, and `/mobile/button/*` GET routes behind `AuthMiddleware`.
- Preserved Java mobile menu tree descending `SORT_CODE` behavior.
- Kept mobile resource add, edit, delete, menu change-module, and grant mutations deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/mobile/MobileResourceService.php`
- `app/controller/mobile/ModuleController.php`
- `app/controller/mobile/MenuController.php`
- `app/controller/mobile/ButtonController.php`
- `route/app.php`
- `docs/api/mobile-resource-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected mobile resource read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /mobile/module/page`
  - `GET /mobile/module/detail`
  - `GET /mobile/menu/tree`
  - `GET /mobile/menu/moduleSelector`
  - `GET /mobile/menu/menuTreeSelector`
  - `GET /mobile/button/page`
  - `GET /mobile/button/detail`
- `GET /mobile/module/page` without a token returned `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.

### Current Issues

- Mobile resource write endpoints remain deferred because they mutate mobile menu permission state and must coordinate with role grant behavior.
- Mobile grant result shaping may need a later dedicated endpoint if the old role grant UI needs the Java `mobileMenuTreeSelector` aggregate format.

### Next Plan

- Commit and push this mobile resource read-only compatibility slice.
- Continue scanning development/support API modules, likely dev config/dict/message/log read-only endpoints next.

## 2026-05-29 - merge-agent - Dev Dict Read-Only Compatibility

### Completed Content

- Analyzed old Vue dictionary API usage and Java `DevDictController` / `DevDictServiceImpl`.
- Added read-only dictionary page, list, tree, and detail endpoints.
- Registered protected `/dev/dict/*` GET routes behind `AuthMiddleware`.
- Shaped dictionary tree nodes with `name`, `dictLabel`, and `dictValue` so the old frontend `DICT_TYPE_TREE_DATA` cache can drive select options.
- Kept dictionary add, edit, delete, and translation cache mutation behavior deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/dev/DictService.php`
- `app/controller/dev/DictController.php`
- `route/app.php`
- `docs/api/dev-dict-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected `/dev/dict/*` read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /dev/dict/tree`
  - `GET /dev/dict/page`
  - `GET /dev/dict/list`
  - `GET /dev/dict/detail`
- `GET /dev/dict/tree` returned nodes with `name` and `dictValue`.
- `GET /dev/dict/tree` without a token returned `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.

### Current Issues

- Dictionary mutation and translation cache refresh behavior remain deferred.
- Business dictionary tenant administration rules need a later write-endpoint plan before add/edit/delete are enabled.

### Next Plan

- Commit and push this dictionary read-only compatibility slice.
- Continue with dev config/log/message read-only endpoints, keeping sensitive config value exposure under review.

## 2026-05-29 - merge-agent - Dev Log Read-Only Compatibility

### Completed Content

- Analyzed old Vue log API usage and Java `DevLogController` / `DevLogServiceImpl`.
- Added read-only log page, detail, visit chart, and operation chart endpoints.
- Registered protected `/dev/log/*` GET routes behind `AuthMiddleware`.
- Kept log page responses lightweight by omitting large fields from page rows, matching Java behavior.
- Kept destructive `/dev/log/delete` behavior deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/dev/LogService.php`
- `app/controller/dev/LogController.php`
- `route/app.php`
- `docs/api/dev-log-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected `/dev/log/*` read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /dev/log/page`
  - `GET /dev/log/detail`
  - `GET /dev/log/vis/lineChartData`
  - `GET /dev/log/vis/pieChartData`
  - `GET /dev/log/op/barChartData`
  - `GET /dev/log/op/pieChartData`
- Page rows omit large log fields while detail returns the full row.
- `GET /dev/log/page` without a token returned `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.

### Current Issues

- Log delete/clear is intentionally not implemented.
- Log detail can expose historical request/response payloads to authorized users, so it must stay behind authenticated admin routes.

### Next Plan

- Commit and push this log read-only compatibility slice.
- Continue with dev message read-only endpoints or carefully scoped config reads after reviewing sensitive value exposure.

## 2026-05-29 - merge-agent - Dev Message Read-Only Compatibility

### Completed Content

- Analyzed Java `DevMessageController` / `DevMessageServiceImpl` and the `dev_message` / `dev_relation` tables.
- Added read-only station-message page and detail compatibility endpoints.
- Registered protected `/dev/message/*` GET routes behind `AuthMiddleware`.
- Added receiver read-status shaping through `receiveInfoList` without mutating `dev_relation`.
- Kept message send, delete, SSE push, and Java detail read-state update behavior deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/dev/MessageService.php`
- `app/controller/dev/MessageController.php`
- `route/app.php`
- `docs/api/dev-message-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\dev\MessageService.php`: passed.
- `php -l app\controller\dev\MessageController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected `/dev/message/*` read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /dev/message/page`
  - `GET /dev/message/detail`
- `GET /dev/message/detail` returned `receiveInfoList` when a message row existed.
- `GET /dev/message/page` without a token returned `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.

### Current Issues

- Message send/delete remain deferred because they mutate `dev_message` and `dev_relation`.
- Java detail marks unread messages as read and sends SSE notifications; this PHP slice intentionally stays read-only and does not reproduce that side effect yet.

### Next Plan

- Commit and push this message read-only compatibility slice.
- Continue with carefully scoped development support APIs, likely config reads after reviewing sensitive value exposure.

## 2026-05-29 - merge-agent - Dev Config Safe Read-Only Compatibility

### Completed Content

- Analyzed Java `DevConfigController` / `DevConfigServiceImpl`, frontend `configApi.js`, login-page usage, and `dev_config` SQL seed data.
- Added public read-only `/dev/config/sysBaseList` for login-page system base configuration.
- Added protected read-only `/dev/config/page`, `/dev/config/list`, and `/dev/config/detail` routes behind `AuthMiddleware`.
- Masked sensitive config values when `configKey` contains password, secret, token, private, access-key, or app-key markers.
- Kept config add, edit, delete, editBatch, and Redis config cache mutation behavior deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/dev/ConfigService.php`
- `app/controller/dev/ConfigController.php`
- `route/app.php`
- `docs/api/dev-config-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\dev\ConfigService.php`: passed.
- `php -l app\controller\dev\ConfigController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the public `/dev/config/sysBaseList` route plus protected `/dev/config/*` read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke returned `code=200` for public `GET /dev/config/sysBaseList` without a token.
- `GET /dev/config/sysBaseList` excluded `SNOWY_SYS_DEFAULT_PASSWORD`.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /dev/config/page`
  - `GET /dev/config/list`
  - `GET /dev/config/detail`
- Sensitive config rows returned masked `configValue`.
- Protected `GET /dev/config/page` without a token returned `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.

### Current Issues

- Config writes remain deferred because they need permission, audit, validation, and "keep existing secret" semantics.
- Full-value secret reads are intentionally not implemented; later write endpoints should avoid requiring the frontend to round-trip secret values.

### Next Plan

- Commit and push this config read-only compatibility slice.
- Continue scanning the old frontend for the next safe read-only API group before enabling any write endpoint.

## 2026-05-29 - merge-agent - Dev File Metadata Read-Only Compatibility

### Completed Content

- Analyzed Java `DevFileController` / `DevFileServiceImpl`, frontend `fileApi.js`, file management page usage, and the `dev_file` table from `oa2026.sql`.
- Added protected read-only file metadata page, list, and detail endpoints.
- Registered protected `/dev/file/*` GET routes behind `AuthMiddleware`.
- Kept file upload, delete, and actual file download streaming behavior deferred.
- Adjusted `/dev/file/list` to return at most 200 lightweight metadata rows without thumbnail payloads after smoke testing found the full list could trigger a 500 response due to large base64 thumbnail data.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/dev/FileService.php`
- `app/controller/dev/FileController.php`
- `route/app.php`
- `docs/api/dev-file-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\dev\FileService.php`: passed.
- `php -l app\controller\dev\FileController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected `/dev/file/*` read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /dev/file/page`
  - `GET /dev/file/list`
  - `GET /dev/file/detail`
- `/dev/file/page` returns thumbnail metadata for paginated table compatibility.
- `/dev/file/list` returns lightweight metadata without thumbnail payloads.
- `GET /dev/file/page` without a token returned `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.

### Current Issues

- File upload, delete, and download streaming remain deferred because they need a storage root, cloud credential, validation, permission, audit, and safe path plan.
- Existing `DOWNLOAD_PATH` values in imported data may point at the old Java backend domain; a later frontend/runtime compatibility step should decide whether to rewrite them at response time or migrate values.

### Next Plan

- Commit and push this file metadata read-only compatibility slice.
- Continue with another safe read-only support module, likely email/SMS metadata pages, before planning write endpoints.

## 2026-05-29 - merge-agent - Dev Email And Sms Read-Only Compatibility

### Completed Content

- Analyzed Java `DevEmailController` / `DevEmailServiceImpl`, Java `DevSmsController` / `DevSmsServiceImpl`, frontend `emailApi.js` / `smsApi.js`, and the `dev_email` / `dev_sms` tables from `oa2026.sql`.
- Added protected read-only email and SMS record page/detail endpoints.
- Registered protected `/dev/email/*` and `/dev/sms/*` GET routes behind `AuthMiddleware`.
- Kept email/SMS send and delete behavior deferred because those operations call external providers or mutate historical send records.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/dev/EmailService.php`
- `app/service/dev/SmsService.php`
- `app/controller/dev/EmailController.php`
- `app/controller/dev/SmsController.php`
- `route/app.php`
- `docs/api/dev-email-sms-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\dev\EmailService.php`: passed.
- `php -l app\service\dev\SmsService.php`: passed.
- `php -l app\controller\dev\EmailController.php`: passed.
- `php -l app\controller\dev\SmsController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected `/dev/email/*` and `/dev/sms/*` read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /dev/email/page`
  - `GET /dev/email/detail`
  - `GET /dev/sms/page`
  - `GET /dev/sms/detail`
- Protected `GET /dev/email/page` without a token returned `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.

### Current Issues

- Email/SMS send endpoints require provider credential handling, validation, rate limiting, permission checks, and audit logging before they can be safely enabled.
- Delete endpoints remain deferred because they mutate historical send records.

### Next Plan

- Commit and push this email/SMS read-only compatibility slice.
- Continue with another safe read-only support module before planning write endpoints.

## 2026-05-29 - merge-agent - Dev Job Read-Only Compatibility

### Completed Content

- Analyzed Java `DevJobController` / `DevJobServiceImpl`, frontend `jobApi.js`, job task classes, and the `dev_job` table from `oa2026.sql`.
- Added protected read-only scheduled-job page, list, detail, and action-class lookup endpoints.
- Registered protected `/dev/job/*` GET routes behind `AuthMiddleware`.
- Kept job add, edit, delete, stop, run, and run-now behavior deferred because those operations mutate scheduler/database state or execute task classes.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/dev/JobService.php`
- `app/controller/dev/JobController.php`
- `route/app.php`
- `docs/api/dev-job-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\dev\JobService.php`: passed.
- `php -l app\controller\dev\JobController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected `/dev/job/*` read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /dev/job/page`
  - `GET /dev/job/list`
  - `GET /dev/job/detail`
  - `GET /dev/job/getActionClass`
- Protected `GET /dev/job/page` without a token returned `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.

### Current Issues

- Java job action classes cannot run inside ThinkPHP; a later scheduler design must replace Java `CommonTimerTaskRunner` classes with explicit PHP jobs or external orchestration.
- `getActionClass` currently returns distinct stored active `ACTION_CLASS` values rather than scanning executable PHP job classes.

### Next Plan

- Commit and push this job read-only compatibility slice.
- Continue with another safe read-only support module before planning scheduler or write endpoints.

## 2026-05-29 - merge-agent - Sys Config Read-Only Compatibility

### Completed Content

- Analyzed Java `SysConfigController` / `SysConfigServiceImpl`, frontend `sysConfigApi.js`, login-flow usage, process config page usage, and the `sys_config` table from `oa2026.sql`.
- Added protected read-only `/sys/sysConfig/detail` compatibility endpoint.
- Decoded `CONFIG_JSON` into the old frontend's expected `processConfigMap` shape.
- Kept system config edit and generate-default behavior deferred because they mutate `sys_config` and tenant cache.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/sys/SysConfigService.php`
- `app/controller/sys/SysConfigController.php`
- `route/app.php`
- `docs/api/sys-config-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\sys\SysConfigService.php`: passed.
- `php -l app\controller\sys\SysConfigController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected `/sys/sysConfig/detail` read-only route.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for `GET /sys/sysConfig/detail`.
- Runtime HTTP smoke confirmed `processConfigMap` contains 11 process config keys.
- Protected `GET /sys/sysConfig/detail` without a token returned `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.

### Current Issues

- Missing or invalid config returns an in-memory default object and does not generate a database row.
- System config writes need workflow process validation and cache invalidation rules before they are enabled.

### Next Plan

- Commit and push this sys config read-only compatibility slice.
- Continue with `/dev/monitor/serverInfo` read-only compatibility, using explorer findings, while keeping `networkInfo` deferred.

## 2026-05-29 - merge-agent - Dev Monitor Server Info Read-Only Compatibility

### Completed Content

- Used multi-agent explorer output to confirm the safe monitor scope before implementation.
- Analyzed Java `DevMonitorController`, `DevMonitorServiceImpl`, `DevMonitorServerResult`, and frontend `monitorApi.js`.
- Added protected read-only `/dev/monitor/serverInfo` compatibility route.
- Returned Java monitor group keys for CPU, memory, storage, server, and JVM-shaped runtime data.
- Used only safe PHP built-ins and left `/dev/monitor/networkInfo` deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/dev/MonitorService.php`
- `app/controller/dev/MonitorController.php`
- `route/app.php`
- `docs/api/dev-monitor-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\dev\MonitorService.php`: passed.
- `php -l app\controller\dev\MonitorController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected `/dev/monitor/serverInfo` route.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for `GET /dev/monitor/serverInfo`.
- Runtime HTTP smoke confirmed monitor payload includes `devMonitorCpuInfo` and `devMonitorMemoryInfo`.
- Protected `GET /dev/monitor/serverInfo` without a token returned `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.

### Current Issues

- CPU usage, physical core count, JVM start time, and JVM run time are safe placeholders because PHP cannot provide the Java OSHI/JVM metrics without extensions or system commands.
- `/dev/monitor/networkInfo` remains deferred because the Java implementation uses platform commands and sampling delay.

### Next Plan

- Commit and push this monitor read-only compatibility slice.
- Continue with the next safe read-only compatibility group, likely generator metadata reads, using the previously completed explorer findings.

## 2026-05-29 - merge-agent - Gen Metadata Read-Only Compatibility

### Completed Content

- Used the earlier gen explorer findings to keep scope limited to safe metadata reads.
- Analyzed Java `GenBasicController`, `GenConfigController`, `GenBasicServiceImpl`, `GenConfigServiceImpl`, frontend generator API files, and `gen_basic` / `gen_config` SQL tables.
- Added protected read-only generator basic page/detail and mobile module selector endpoints.
- Added protected read-only generator config list/detail endpoints.
- Kept generator execution, code preview, table scanning, column scanning, and all write routes deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/gen/BasicService.php`
- `app/service/gen/ConfigService.php`
- `app/controller/gen/BasicController.php`
- `app/controller/gen/ConfigController.php`
- `route/app.php`
- `docs/api/gen-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\gen\BasicService.php`: passed.
- `php -l app\service\gen\ConfigService.php`: passed.
- `php -l app\controller\gen\BasicController.php`: passed.
- `php -l app\controller\gen\ConfigController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected generator read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /gen/basic/page`
  - `GET /gen/basic/detail`
  - `GET /gen/config/list`
  - `GET /gen/basic/mobileModuleSelector`
- `GET /gen/basic/page` without a token returned `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.

### Current Issues

- The imported `gen_config` table currently has no rows for the existing `gen_basic` seed row, so runtime smoke covered `config/list` returning an empty list.
- `/gen/basic/tables` and `/gen/basic/tableColumns` remain deferred because they expose schema metadata and need an allow-list design.
- Generator preview and execution remain deferred because they can render or write generated code.

### Next Plan

- Commit and push this generator metadata read-only compatibility slice.
- Continue scanning old frontend calls for the next safe read-only group, while leaving generator write/execution routes disabled.

## 2026-05-29 - merge-agent - Auth Session Current Token Read-Only Compatibility

### Completed Content

- Analyzed old frontend `auth/monitorApi.js` and Java `AuthSessionController` / `AuthSessionServiceImpl`.
- Added protected read-only session monitor endpoints for analysis, B-side page, and C-side page.
- Returned a current-token B-side session page row from the authenticated bearer token and `sys_user`.
- Returned an empty C-side page because client auth is not implemented yet.
- Kept all session exit and token exit routes deferred.
- Did not add a global token index or change login token write behavior.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/auth/SessionMonitorService.php`
- `app/controller/auth/SessionController.php`
- `route/app.php`
- `docs/api/auth-session-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\auth\SessionMonitorService.php`: passed.
- `php -l app\controller\auth\SessionController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected session monitor routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /auth/session/analysis`
  - `GET /auth/session/b/page`
  - `GET /auth/session/c/page`
- Runtime smoke confirmed analysis `currentSessionTotalCount=1`, B page `total=1`, and C page `total=0`.
- `GET /auth/session/analysis` without a token returned `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.

### Current Issues

- This slice cannot enumerate all online sessions because the current `TokenService` stores token payloads by hashed token key only and has no searchable index.
- `tokenSignList.tokenValue` is masked intentionally because token exit routes are not implemented and full token disclosure is unnecessary for this read-only slice.
- Full session management needs a later auth-agent token-index design.

### Next Plan

- Commit and push this auth session read-only compatibility slice.
- Continue scanning old frontend calls for another safe read-only group before planning any mutation endpoints.

## 2026-05-29 - merge-agent - Tenants Read-Only Compatibility

### Completed Content

- Analyzed old frontend `tenant/tenantsApi.js`, Java `TenantsController`, `TenantsServiceImpl`, and the `tenants` SQL table.
- Added protected read-only tenant page and detail endpoints.
- Preserved mixed-case physical column access for `Tenant_ID` and `Tenant_Name`.
- Returned Java-style camelCase tenant rows.
- Kept tenant add, edit, delete, default system data generation, and tenant cache/event mutation deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/tenant/TenantsService.php`
- `app/controller/tenant/TenantsController.php`
- `route/app.php`
- `docs/api/tenants-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\tenant\TenantsService.php`: passed.
- `php -l app\controller\tenant\TenantsController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected tenant read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /tenants/tenant/page`
  - `GET /tenants/tenant/detail`
- Runtime smoke confirmed tenant page `total=5` and detail lookup returned tenant id `0`.
- `GET /tenants/tenant/page` without a token returned `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.

### Current Issues

- Tenant add/edit/delete remain deferred because they mutate tenant data and can trigger default user, role, resource, and permission generation.
- Any later tenant write support must include system-tenant protection and safe-password verification.

### Next Plan

- Commit and push this tenant read-only compatibility slice.
- Continue with another safe read-only business or admin module after scanning old frontend calls.

## 2026-05-29 - merge-agent - Biz Product Read-Only Compatibility

### Completed Content

- Analyzed old frontend `bizProductApi.js`, Java `BizProductController` / `BizProductServiceImpl`, Java product entity/result classes, `biz_product`, and `product_relation`.
- Added protected read-only product master page, list, detail, and kit-product children endpoints.
- Returned lower-camel product rows compatible with Java JSON serialization while preserving the physical SQL columns, including lower-case `status`.
- Registered protected `/biz/bizproduct/*` read-only routes behind `AuthMiddleware`.
- Kept product add, edit, delete, reconciliation edit, status edit, product relation writes, and data-change events deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/ProductService.php`
- `app/controller/biz/ProductController.php`
- `route/app.php`
- `docs/api/biz-product-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\biz\ProductService.php`: passed.
- `php -l app\controller\biz\ProductController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected product read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /biz/bizproduct/page`
  - `GET /biz/bizproduct/list`
  - `GET /biz/bizproduct/detail`
  - `POST /biz/bizproduct/children`
- Runtime smoke confirmed product page total `3322`, product list search returned `348` rows, and one kit product returned `4` child rows.
- `GET /biz/bizproduct/page` without a token returned business `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.

### Current Issues

- Java applies a richer login-user data-scope fallback than the current token payload. This slice applies tenant filtering and token data-scope org ids when present, but does not force the Java `CREATE_USER = loginId` fallback yet.
- Product write endpoints need validation, permission, kit relation writes, audit, and data-change event behavior before they can be enabled.

### Next Plan

- Commit and push this product read-only compatibility slice.
- Continue with another foundational read-only business master-data module, likely customer or supplier, before enabling any product write endpoint.

## 2026-05-29 - merge-agent - Biz Supplier Read-Only Compatibility

### Completed Content

- Analyzed old frontend `supplierApi.js`, Java `SupplierController` / `SupplierServiceImpl`, Java supplier entity/params/enums, and the `supplier` SQL table.
- Added protected read-only supplier page, list, enabled name lookup, and detail endpoints.
- Returned lower-camel supplier rows compatible with Java JSON serialization while preserving the physical SQL columns, including lower-case `org`.
- Registered protected `/biz/supplier/*` read-only routes behind `AuthMiddleware`.
- Kept supplier add, edit, delete, and write validation deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/SupplierService.php`
- `app/controller/biz/SupplierController.php`
- `route/app.php`
- `docs/api/biz-supplier-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\biz\SupplierService.php`: passed.
- `php -l app\controller\biz\SupplierController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected supplier read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /biz/supplier/page`
  - `GET /biz/supplier/list`
  - `GET /biz/supplier/list/query/name`
  - `GET /biz/supplier/detail`
- Runtime smoke confirmed supplier page total `186`, supplier list search returned `22` rows, and name lookup returned `1` row.
- `GET /biz/supplier/page` without a token returned business `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.

### Current Issues

- Java applies a richer login-user data-scope fallback than the current token payload. This slice applies tenant filtering and token data-scope org ids when present, but does not force the Java `CREATE_USER = loginId` fallback yet.
- Supplier write endpoints need validation, permission, audit, and downstream purchase/settlement impact checks before they can be enabled.

### Next Plan

- Commit and push this supplier read-only compatibility slice.
- Revisit customer read-only migration after a safe SM4 encrypted-field strategy is documented, or continue with another non-encrypted master-data module such as warehouse/inventory read-only APIs.

## 2026-05-29 - merge-agent - Biz Warehouses Read-Only Compatibility

### Completed Content

- Analyzed old frontend `warehousesApi.js`, Java `WarehousesController` / `WarehousesServiceImpl`, Java warehouse entity and page params, and the `warehouses` SQL table.
- Added protected read-only warehouse page, list, and detail endpoints.
- Returned lower-camel warehouse rows compatible with Java JSON serialization while preserving physical SQL columns such as `SORT_CODE`, `USER`, and `ORG`.
- Resolved warehouse owner display name from `sys_user.NAME` and organization display name from `sys_org.NAME`.
- Registered protected `/biz/warehouses/*` read-only routes behind `AuthMiddleware`.
- Kept warehouse add, edit, delete, stock movement, and downstream inventory effects deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/WarehousesService.php`
- `app/controller/biz/WarehousesController.php`
- `route/app.php`
- `docs/api/biz-warehouses-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\biz\WarehousesService.php`: passed.
- `php -l app\controller\biz\WarehousesController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected warehouse read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /biz/warehouses/page`
  - `GET /biz/warehouses/list`
  - `GET /biz/warehouses/detail`
- Runtime smoke confirmed warehouse page total `4` for tenant `1`.
- `GET /biz/warehouses/page` without a token returned business `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.
- `git diff --check`: passed with existing CRLF normalization warnings only.

### Current Issues

- Java page reads apply richer login-user data scope through the warehouse owner user. This slice applies tenant filtering and token data-scope org ids when present, but does not force the Java `USER = loginId` fallback yet.
- Warehouse write endpoints need validation, permission checks, audit behavior, and inventory/purchase/sales impact checks before they can be enabled.

### Next Plan

- Commit and push this warehouse read-only compatibility slice.
- Continue with inventory read-only compatibility, because it depends on product and warehouse foundations and should remain separate from stock-changing write routes.

## 2026-05-29 - merge-agent - Biz Inventory Read-Only Compatibility

### Completed Content

- Analyzed old frontend `inventoryApi.js`, `views/biz/inventory/index.vue`, Java `InventoryController` / `InventoryServiceImpl`, Java `ProductInventory`, and the `inventory` SQL table.
- Added protected read-only inventory page, list, and detail endpoints.
- Implemented Java-compatible warehouse validation for page/list reads that require `warehousesId`.
- Joined enabled `biz_product` records to return product display fields used by the old inventory page.
- Registered protected `/biz/inventory/*` read-only routes behind `AuthMiddleware`.
- Kept inventory add, delete, stock in/out, batch stock movement, and data-change event behavior deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/InventoryService.php`
- `app/controller/biz/InventoryController.php`
- `route/app.php`
- `docs/api/biz-inventory-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\biz\InventoryService.php`: passed.
- `php -l app\controller\biz\InventoryController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected inventory read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /biz/inventory/page`
  - `GET /biz/inventory/list`
  - `GET /biz/inventory/detail`
- Runtime smoke selected the first tenant `1` warehouse, confirmed inventory page total `261`, list rows `261`, and detail product display data.
- `GET /biz/inventory/page` without a token returned business `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.
- `git diff --check`: passed with existing CRLF normalization warnings only.

### Current Issues

- Java stock-changing operations publish warehouse inventory data-change events. The ThinkPHP replacement for those events still needs a later write-endpoint design.
- Inventory writes need permission checks, validation, audit behavior, optimistic-lock handling, and downstream purchase/sales workflow impact checks before they can be enabled.

### Next Plan

- Commit and push this inventory read-only compatibility slice.
- Continue with the next safe read-only business module after scanning frontend usage, while leaving customer reads paused until the SM4 encrypted-field strategy is documented.

## 2026-05-29 - merge-agent - Biz Delivery Record Read-Only Compatibility

### Completed Content

- Analyzed old frontend `deliveryRecordApi.js`, product inventory history view, inventory export view, Java `DeliveryRecordController` / `DeliveryRecordServiceImpl`, Java delivery record params/entity, and the `delivery_record` SQL table.
- Added protected read-only warehouse delivery-record page, export-other-company-records list, and detail compatibility endpoints.
- Enriched delivery records with `warehousesName`, `productName`, and `operatorName` display fields.
- Supported frontend `completionTime` range and Java-style `deliveryStartTime` / `deliveryEndTime` filters for export reads.
- Registered protected `/biz/warehouses/delivery/*` read-only routes behind `AuthMiddleware`.
- Kept delivery record add, inventory stock changes, batch stock movement, and data-change event behavior deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/DeliveryRecordService.php`
- `app/controller/biz/DeliveryRecordController.php`
- `route/app.php`
- `docs/api/biz-delivery-record-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\biz\DeliveryRecordService.php`: passed.
- `php -l app\controller\biz\DeliveryRecordController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected delivery-record read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /biz/warehouses/delivery/page`
  - `GET /biz/warehouses/delivery/detail`
  - `GET /biz/warehouses/delivery/exportOtherCompanyRecordsList`
- Runtime smoke confirmed delivery page total `2582` and detail product display data.
- Export smoke returned `code=200`; the sampled warehouse/product-org combination currently returned `0` rows, which is valid for the read-only query shape.
- `GET /biz/warehouses/delivery/page` without a token returned business `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.
- `git diff --check`: passed with existing CRLF normalization warnings only.

### Current Issues

- Java delivery record `add` mutates inventory and publishes data-change events, so it needs a later write-endpoint design with permission, audit, optimistic locking, and stock consistency checks.
- Java controller does not expose a detail mapping in the analyzed source, but the old frontend API wrapper includes `deliveryRecordDetail`; this slice adds it as read-only compatibility.

### Next Plan

- Commit and push this delivery-record read-only compatibility slice.
- Continue scanning business frontend calls for another safe read-only module, with customer reads still deferred until the SM4 encrypted-field strategy is documented.

## 2026-05-29 - merge-agent - Biz Purchase Order Read-Only Compatibility

### Completed Content

- Analyzed old frontend `bizPurchaseOrderApi.js`, Java `BizPurchaseOrderController` / `BizPurchaseOrderServiceImpl`, Java purchase-order query/id/detail params, purchase-order item entity, and the SQL tables for purchase orders, order items, products, organizations, and expenditure records.
- Added protected read-only purchase-order page, list, detail-list, and detail compatibility endpoints.
- Decoded supplier display data from `EXT_JSON.supplier` and supported supplier-name filtering with JSON validity guards.
- Enriched purchase-order items with product display fields from `biz_product`.
- Returned Java-compatible detail wrapper data: `bizPurchaseOrder`, `bizPurchaseOrderItemList`, and `bizExpenditureRecordList`.
- Registered protected `/biz/bizpurchaseorder/*` read-only routes behind `AuthMiddleware`.
- Kept purchase-order add, edit, audit edit, delete, cancel, warehouse add, and warehouse one-add behavior deferred at that time; cancel, normal edit, and audit edit were later moved to narrow implementations.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/PurchaseOrderService.php`
- `app/controller/biz/PurchaseOrderController.php`
- `route/app.php`
- `docs/api/biz-purchase-order-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\biz\PurchaseOrderService.php`: passed.
- `php -l app\controller\biz\PurchaseOrderController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected purchase-order read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /biz/bizpurchaseorder/page`
  - `GET /biz/bizpurchaseorder/list`
  - `GET /biz/bizpurchaseorder/detail/list`
  - `GET /biz/bizpurchaseorder/detail`
- Runtime smoke confirmed purchase-order page total `417`, detail-list count `1`, detail item count `1`, and related goods expenditure count `1` for the sampled order.
- Supplier-name JSON filter smoke returned `code=200` and `61` rows for the sampled keyword.
- `GET /biz/bizpurchaseorder/page` without a token returned business `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.
- `git diff --check`: passed with existing CRLF normalization warnings only.

### Current Issues

- Java purchase-order write flows affect workflow/audit state, expenditure records, warehouse stock-in, inventory quantities, and optimistic-lock versions. Those routes need a later write-endpoint design before enabling them.
- Customer-related reads remain deferred until the SM4 encrypted-field strategy is documented.

### Next Plan

- Commit and push this purchase-order read-only compatibility slice.
- Continue scanning business frontend calls for another safe read-only module, likely settlement-account or sale-project reads depending on encrypted-field impact.

## 2026-05-29 - merge-agent - Biz Settlement Account Read-Only Compatibility

### Completed Content

- Analyzed old frontend `settlementAccountApi.js`, Java `SettlementAccountController` / `SettlementAccountServiceImpl`, Java settlement-account page/query params, entity, and the `settlement_account` SQL table.
- Added protected read-only settlement-account page, enabled-list, detail, and queryName compatibility endpoints.
- Preserved SQL lower-case `org` field and enriched rows with `orgName` from `sys_org`.
- Supported Java/old-frontend filters for account name, account number, account status, org id, search key, sorting, and pagination.
- Registered protected `/biz/settlementaccount/*` read-only routes behind `AuthMiddleware`.
- Kept account add, edit, delete, status change, expense correction, income correction, and transfer behavior deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/SettlementAccountService.php`
- `app/controller/biz/SettlementAccountController.php`
- `route/app.php`
- `docs/api/biz-settlement-account-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\biz\SettlementAccountService.php`: passed.
- `php -l app\controller\biz\SettlementAccountController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected settlement-account read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /biz/settlementaccount/page`
  - `GET /biz/settlementaccount/list`
  - `GET /biz/settlementaccount/detail`
  - `GET /biz/settlementaccount/queryName`
- Runtime smoke confirmed settlement-account page total `33`, enabled-list count `32`, detail name present, queryName present, and account-name filtered total `8`.
- `GET /biz/settlementaccount/page` without a token returned business `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.
- `git diff --check`: passed with existing CRLF normalization warnings only.

### Current Issues

- Settlement-account writes affect balances and related statement/payment/expenditure records. Those routes need a later write-endpoint design with transaction boundaries, optimistic locking, and audit behavior.
- Customer-related reads remain deferred until the SM4 encrypted-field strategy is documented.

### Next Plan

- Commit and push this settlement-account read-only compatibility slice.
- Continue scanning business frontend calls for another safe read-only module.

## 2026-05-29 - merge-agent - Biz Payment Record Read-Only Compatibility

### Completed Content

- Analyzed old frontend `bizPaymentRecordApi.js`, Java `BizPaymentRecordController` / `BizPaymentRecordServiceImpl`, Java payment-record page/query params, entity, and the `biz_payment_record` SQL table.
- Added protected read-only payment-record page, listdetails, list, and detail compatibility endpoints.
- Enriched payment-record rows with settlement account name/number from `settlement_account` and `orgName` from `sys_org`.
- Supported Java/old-frontend filters for object id, object ids, target id, serial id, process id, settlement category, payer time, create time, amount, account name, org id, search key, sorting, and pagination.
- Registered protected `/biz/bizpaymentrecord/*` read-only routes behind `AuthMiddleware`.
- Kept payment-record edit and account-switch behavior deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/PaymentRecordService.php`
- `app/controller/biz/PaymentRecordController.php`
- `route/app.php`
- `docs/api/biz-payment-record-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\biz\PaymentRecordService.php`: passed.
- `php -l app\controller\biz\PaymentRecordController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected payment-record read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /biz/bizpaymentrecord/page`
  - `GET /biz/bizpaymentrecord/listdetails`
  - `GET /biz/bizpaymentrecord/list`
  - `GET /biz/bizpaymentrecord/detail`
- Runtime smoke confirmed payment-record page total `535`, sampled listdetails count `44`, sampled list count `44`, detail account-name enrichment, and account-name filtered total `101`.
- `GET /biz/bizpaymentrecord/page` without a token returned business `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.
- `git diff --check`: passed with existing CRLF normalization warnings only.

### Current Issues

- Payment-record write flows affect settlement-account balances and settlement statements. Those routes need a later write-endpoint design with transactions, optimistic locking, audit behavior, and data-change events.
- Customer-related reads remain deferred until the SM4 encrypted-field strategy is documented.

### Next Plan

- Commit and push this payment-record read-only compatibility slice.
- Continue with the next safe read-only settlement/business module.

## 2026-05-29 - merge-agent - Biz Expenditure Record Read-Only Compatibility

### Completed Content

- Analyzed old frontend `bizExpenditureRecordApi.js`, Java `BizExpenditureRecordController` / `BizExpenditureRecordServiceImpl`, Java expenditure-record page/query params, entity, and the `biz_expenditure_record` SQL table.
- Added protected read-only expenditure-record page, listDetails, list, and detail compatibility endpoints.
- Enriched expenditure-record rows with settlement account name/number from `settlement_account` and `orgName` from `sys_org`.
- Supported Java/old-frontend filters for object id, object ids, target id, serial id, process id, settlement category, payer, bank, remark, payer time, create time, amount, account name, org id, search key, sorting, and pagination.
- Registered protected `/biz/bizexpenditurerecord/*` read-only routes behind `AuthMiddleware`.
- Kept expenditure-record add, edit, delete, and account-switch behavior deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/ExpenditureRecordService.php`
- `app/controller/biz/ExpenditureRecordController.php`
- `route/app.php`
- `docs/api/biz-expenditure-record-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\biz\ExpenditureRecordService.php`: passed.
- `php -l app\controller\biz\ExpenditureRecordController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected expenditure-record read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /biz/bizexpenditurerecord/page`
  - `GET /biz/bizexpenditurerecord/listDetails`
  - `GET /biz/bizexpenditurerecord/list`
  - `GET /biz/bizexpenditurerecord/detail`
- Runtime smoke confirmed expenditure-record page total `1535`, sampled listDetails count `207`, sampled list count `207`, detail account-name enrichment, and account-name filtered total `231`.
- `GET /biz/bizexpenditurerecord/page` without a token returned business `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.
- `git diff --check`: passed with existing CRLF normalization warnings only.

### Current Issues

- Expenditure-record write flows affect settlement-account balances and settlement statements. Those routes need a later write-endpoint design with transactions, optimistic locking, audit behavior, and data-change events.
- Customer-related reads remain deferred until the SM4 encrypted-field strategy is documented.

### Next Plan

- Commit and push this expenditure-record read-only compatibility slice.
- Continue with the next safe read-only settlement/business module.

## 2026-05-29 - merge-agent - Biz Collection Receipt Read-Only Compatibility

### Completed Content

- Analyzed old frontend `bizCollectionReceiptApi.js`, Java `BizCollectionReceiptController` / `BizCollectionReceiptServiceImpl`, Java collection-receipt page params, entity, mapper, and the `biz_collection_receipt` SQL table.
- Added protected read-only collection-receipt page, list, and detail compatibility endpoints.
- Enriched collection-receipt rows with linked payment-record payer time, settlement category, payer/bank fields, settlement account name/number, and organization name.
- Supported Java/old-frontend filters for play status, remark, account name, search key, sorting, pagination, payment record id, and tenant id.
- Registered protected `/biz/bizcollectionreceipt/*` read-only routes behind `AuthMiddleware`.
- Kept collection-receipt batch expenditure, mark success, add, edit, and delete behavior deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/CollectionReceiptService.php`
- `app/controller/biz/CollectionReceiptController.php`
- `route/app.php`
- `docs/api/biz-collection-receipt-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\biz\CollectionReceiptService.php`: passed.
- `php -l app\controller\biz\CollectionReceiptController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected collection-receipt read-only routes.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /biz/bizcollectionreceipt/page`
  - `GET /biz/bizcollectionreceipt/list`
  - `GET /biz/bizcollectionreceipt/detail`
- Runtime smoke confirmed collection-receipt page total `18`, `AlreadySettled` list count `16`, sampled detail account-name enrichment, and account-name filtered total `9`.
- `GET /biz/bizcollectionreceipt/page` without a token returned business `code=401`.

### Current Issues

- Collection-receipt mark-success is now covered as a single-table status update; batch-expenditure still mutates expenditure records and settlement-account side effects and needs a later transaction design.
- Customer-related reads remain deferred until the SM4 encrypted-field strategy is documented.

### Next Plan

- Commit and push this collection-receipt read-only compatibility slice.
- Continue with the next safe read-only business module, likely debit-note read endpoints.

## 2026-05-29 - merge-agent - Biz Debit Note Read-Only Compatibility

### Completed Content

- Analyzed old frontend `bizDebitNoteApi.js`, Java `BizDebitNoteController` / `BizDebitNoteServiceImpl`, Java debit-note page params, entity, and the `biz_debit_note` SQL table.
- Added protected read-only debit-note page, list, and detail compatibility endpoints.
- Enriched debit-note rows with linked expenditure-record payer time, settlement category, payer/bank fields, settlement account name/number, and organization name.
- Supported Java/old-frontend filters for play status, create time range, remark, account name, category, search key, sorting, pagination, expenditure record id, org id, amount, and tenant id.
- Registered protected `/biz/bizdebitnote/*` read-only routes behind `AuthMiddleware`.
- Kept debit-note history add, batch repayment, add, edit, and delete behavior deferred in that slice; subsequent state: batch repayment is covered by the 2026-06-17 loan-repayment quick-settlement slice, while history add/add/edit/delete remain deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/DebitNoteService.php`
- `app/controller/biz/DebitNoteController.php`
- `route/app.php`
- `docs/api/biz-debit-note-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\biz\DebitNoteService.php`: passed.
- `php -l app\controller\biz\DebitNoteController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected debit-note read-only routes.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /biz/bizdebitnote/page`
  - `GET /biz/bizdebitnote/list`
  - `GET /biz/bizdebitnote/detail`
- Runtime smoke confirmed debit-note page total `106`, `AlreadySettled` list count `84`, sampled detail organization/account enrichment, and account-name filtered total `2`.
- `GET /biz/bizdebitnote/page` without a token returned business `code=401`.
- PHP lint for `app`, `config`, and `route`: passed.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.
- `git diff --check`: passed with existing CRLF normalization warnings only.

### Current Issues

- Debit-note mark-success is now covered as a single-table status update; history add and batch-repayment still mutate payment records and settlement accounts and need a later transactional write design.
- Customer-related reads remain deferred until the SM4 encrypted-field strategy is documented.

### Next Plan

- Commit and push this debit-note read-only compatibility slice.
- Continue with the next safe read-only business module.

## 2026-05-29 - merge-agent - Biz File Relation Read-Only Compatibility

### Completed Content

- Analyzed old frontend `bizFileRelationApi.js`, Java `BizFileRelationController` / `BizFileRelationServiceImpl`, Java file-relation params, entity, mapper, `BizFile`, and the `biz_file_relation` / `dev_file` SQL tables.
- Added protected read-only file-relation page, list, and detail compatibility endpoints.
- Enriched file-relation rows with linked dev-file engine, bucket, name, suffix, size, object name, storage path, download path, thumbnail, and creator display fields.
- Supported Java/old-frontend filters for object id, target id, category, file name, creator, create time range, search key, sorting, pagination, suffix, and tenant id.
- Registered protected `/biz/bizfilerelation/*` read-only routes behind `AuthMiddleware`.
- Kept file-relation add, edit, delete, and project-case delete behavior deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/FileRelationService.php`
- `app/controller/biz/FileRelationController.php`
- `route/app.php`
- `docs/api/biz-file-relation-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\biz\FileRelationService.php`: passed.
- `php -l app\controller\biz\FileRelationController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected file-relation read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /biz/bizfilerelation/page`
  - `GET /biz/bizfilerelation/list`
  - `GET /biz/bizfilerelation/detail`
- Runtime smoke confirmed file-relation page total `716`, sampled category `Process_reimbursement`, sampled list count `1`, sampled detail file name enrichment, and download-path enrichment.
- `GET /biz/bizfilerelation/page` without a token returned business `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.
- `git diff --check`: passed with existing CRLF normalization warnings only.

### Current Issues

- File-relation write flows mutate attachment links and can affect process/project attachment views. Those routes need a later write-endpoint design before implementation.
- `dev_file` rows can contain large thumbnails; future frontend/API tuning may need a lightweight list mode if payload size becomes a problem.
- The local MySQL/Redis helper script exists at `F:\project\socket\AI\testPhp\files\startServer1.bat`; the originally provided mysql subdirectory did not contain the script.

### Next Plan

- Commit and push this file-relation read-only compatibility slice.
- Continue with the next safe read-only business module, likely sale-project or team-project attachment consumers.

## 2026-05-29 - merge-agent - Biz Team Project Read-Only Foundation

### Completed Content

- Analyzed old frontend `bizTeamProjectApi.js`, `bizTeamProjectUserApi.js`, team-project list/detail views, Java `BizTeamProjectController` / `BizTeamProjectServiceImpl`, Java `BizTeamProjectUserController` / `BizTeamProjectUserServiceImpl`, role-permission enum, and the `biz_team_project` / `biz_team_project_user` SQL tables.
- Added protected read-only project page and project detail compatibility endpoints.
- Added protected read-only team-project-user page, list, and detail compatibility endpoints.
- Preserved Java-style current-user membership filtering for project page/detail access.
- Enriched project and member rows with creator, owner, organization, avatar, role name, and permission-code fields needed by the old frontend detail screen.
- Kept project add, edit, delete, member add, member manage-add, member edit, and member delete flows deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/TeamProjectService.php`
- `app/controller/biz/TeamProjectController.php`
- `app/controller/biz/TeamProjectUserController.php`
- `route/app.php`
- `docs/api/biz-team-project-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\biz\TeamProjectService.php`: passed.
- `php -l app\controller\biz\TeamProjectController.php`: passed.
- `php -l app\controller\biz\TeamProjectUserController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed after rerunning with approved escalation because the sandbox could not unlink `runtime\route_list.php`.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /biz/bizteamproject/page`
  - `GET /biz/bizteamproject/detail`
  - `GET /biz/bizteamprojectuser/list`
  - `GET /biz/bizteamprojectuser/page`
  - `GET /biz/bizteamprojectuser/detail`
- Runtime smoke confirmed project total `1`, sampled project id `1903996479133360129`, current user role `LEADER`, member list count `27`, member page total `27`, and non-empty permission-code output.
- `GET /biz/bizteamproject/page` without a token returned business `code=401`.

### Current Issues

- Team project task category, task, comment, and attachment read APIs are still needed for a complete project detail page.
- Team project write flows mutate project membership and project state; those routes remain deferred for a later transactional write design.
- `php think route:list` may need elevated execution in this workspace when the sandbox blocks ThinkPHP route cache cleanup.

### Next Plan

- Commit and push this team-project read-only compatibility slice.
- Continue with team project task/category/comment read-only endpoints before implementing write flows.

## 2026-05-29 - merge-agent - Biz Team Project Task Read-Only Compatibility

### Completed Content

- Analyzed old frontend team-project task, task-category, project-comment, task-comment, and comment-reply API usage.
- Analyzed Java `BizTeamProjectTaskCategoryController` / service, `BizTeamProjectTaskController` / service, `BizTeamProjectCommentController` / service, `BizTeamProjectTaskCommentController` / service, and comment-reply service.
- Added protected read-only task-category page, list, and detail endpoints.
- Added protected read-only task page, list, and detail endpoints; task detail includes assigned task users.
- Added protected read-only project-comment page and list endpoints; list includes nested comment replies.
- Added protected read-only task-comment page, list, and detail endpoints.
- Added current-user project membership gating for project-scoped reads and direct task/comment id lookups.
- Kept all task, task-category, project-comment, comment-reply, and task-user write flows deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/TeamProjectTaskReadService.php`
- `app/controller/biz/TeamProjectTaskCategoryController.php`
- `app/controller/biz/TeamProjectTaskController.php`
- `app/controller/biz/TeamProjectCommentController.php`
- `app/controller/biz/TeamProjectTaskCommentController.php`
- `route/app.php`
- `docs/api/biz-team-project-task-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\biz\TeamProjectTaskReadService.php`: passed.
- `php -l app\controller\biz\TeamProjectTaskCategoryController.php`: passed.
- `php -l app\controller\biz\TeamProjectTaskController.php`: passed.
- `php -l app\controller\biz\TeamProjectCommentController.php`: passed.
- `php -l app\controller\biz\TeamProjectTaskCommentController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected team-project task/category/comment read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /biz/bizteamprojecttaskcategory/list`
  - `GET /biz/bizteamprojecttaskcategory/page`
  - `GET /biz/bizteamprojecttaskcategory/detail`
  - `GET /biz/bizteamprojecttask/list`
  - `GET /biz/bizteamprojecttask/page`
  - `GET /biz/bizteamprojecttask/detail`
  - `GET /biz/bizteamprojectcomment/list`
  - `GET /biz/bizteamprojectcomment/page`
  - `GET /biz/bizteamprojecttaskcomment/list`
  - `GET /biz/bizteamprojecttaskcomment/page`
  - `GET /biz/bizteamprojecttaskcomment/detail`
- Runtime smoke confirmed project `1903996479133360129`, category count `1`, task count `4`, first task id `2033724343755141122`, task detail user count `2`, project-comment count `2`, task-comment count `10`, and nested project-comment reply array presence.
- `GET /biz/bizteamprojecttask/list` without a token returned business `code=401`.

### Current Issues

- Team project task/category/comment write routes remain deferred because they mutate category order, task state, task users, comments, replies, and data-change event side effects.
- Standalone project-comment-reply read routes were not added because the Java controller does not expose them; project-comment list embeds replies instead.
- Some frontend interactions on the task board still call write routes (`edit`, `user/edit`, `sort/edit`, `add`, `delete`) and need later transactional implementation.

### Next Plan

- Commit and push this team-project task read-only compatibility slice.
- Continue with the next safe read-only business module or begin a separate write-flow design for team project tasks after review.

## 2026-06-01 - merge-agent - Biz Return Order Read-Only Compatibility

### Completed Content

- Analyzed old frontend `returnOrderApi.js`, sale-project return-order consumers, Java `ReturnOrderController` / `ReturnOrderServiceImpl`, Java return-order params/entities, and the `return_order` / `return_order_item` SQL tables.
- Added protected read-only return-order page, query, and detail compatibility endpoints.
- Enriched return-order rows with project name, warehouse name, current handler name, and organization name.
- Added `productList` child rows for `query` and `detail`, including project-product and product display fields.
- Preserved Java-style data-scope shape: explicit org filter, token data-scope org ids when present, then current user fallback.
- Registered protected `/biz/returnorder/*` read-only routes behind `AuthMiddleware`.
- Kept return-order add/edit/delete/status, warehouse delivery, inventory stock, refund, and workflow mutation behavior deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/ReturnOrderService.php`
- `app/controller/biz/ReturnOrderController.php`
- `route/app.php`
- `docs/api/biz-return-order-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\biz\ReturnOrderService.php`: passed.
- `php -l app\controller\biz\ReturnOrderController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected return-order read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /biz/returnorder/page`
  - `GET /biz/returnorder/query`
  - `GET /biz/returnorder/detail`
- Runtime smoke confirmed return-order page total `1`, sampled order id `2052251605190221825`, project id `2013520917029085185`, query count `1`, query `productList` count `1`, and detail `productList` count `1`.
- `GET /biz/returnorder/page` without a token returned business `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.
- `git diff --check`: passed with existing CRLF normalization warnings only.

### Current Issues

- Return-order write flows create warehouse delivery-in records, affect inventory stock, update settlement/refund state, and emit data-change events. Those routes need a later transactional write design before implementation.
- The current token payload does not always carry expanded Java data-scope org ids, so fallback behavior may be narrower than Java for users without populated `data_scope_org_ids`.
- Customer-related sale-project reads remain deferred until the SM4 encrypted-field strategy is documented.

### Next Plan

- Commit and push this return-order read-only compatibility slice.
- Continue with the next safe read-only business module, likely sale-project read endpoints after customer encryption strategy is handled, or another non-encrypted support module.

## 2026-06-01 - merge-agent - Progress Dashboard

### Completed Content

- Reviewed current project rules and repository status.
- Counted current ThinkPHP Models, Controllers, Services, API docs, database docs, and route entries.
- Counted Java original Controllers, frontend API files, and SQL table definitions as comparison baselines.
- Created a persistent progress dashboard for future real-time tracking.

### Modified Files

- `STATUS.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- Documentation-only update.
- `git status --short --branch`: checked before editing and was clean/synced.

### Current Issues

- Overall production-ready completion is estimated at about 45%; read-only API compatibility is further along than write/workflow/frontend completion.
- Business write flows, workflow side effects, frontend adaptation, deployment, and final online data sync remain the main work.

### Next Plan

- Keep updating `docs/tasks/refactor-progress-dashboard.md` after each completed slice.
- Generate an API gap map from remaining frontend API files before selecting the next read-only business endpoint.

## 2026-06-01 - merge-agent - Frontend Joint Test Workflow

### Completed Content

- Accepted the user requirement that frontend adaptation must proceed together with backend refactor work.
- Confirmed the original frontend exists at `F:\AI\projects\testJava\OA\snowy-admin-web` and remains read-only.
- Confirmed `F:\AI\projects\testJava\OA-ThinkPHP` does not yet contain the frontend source.
- Confirmed `F:\AI\projects\testJava\OA-frontend` currently contains the ThinkPHP worktree, not the imported Vue frontend.
- Documented the future backend plus frontend startup and smoke-test workflow.
- Updated the progress dashboard so frontend adaptation starts as a parallel track.

### Modified Files

- `STATUS.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/frontend-joint-test-workflow.md`

### Test Results

- Documentation-only update.
- `git status --short --branch`: checked before editing and was clean/synced.

### Current Issues

- The frontend has not been imported into the target repository yet, so browser testing against the adapted ThinkPHP project cannot start until frontend-agent performs the baseline import.
- The original frontend sends a legacy `token` header, while the ThinkPHP convention is `Authorization: Bearer <token>`.
- Browser login may need SM2 compatibility testing after frontend import.

### Next Plan

- Create an API gap map from the original frontend API files.
- Prepare a frontend-agent baseline import plan for `snowy-admin-web` without copying `node_modules`, `dist`, logs, or secrets.
- After the baseline import, start MySQL/Redis, ThinkPHP on port `82`, and Vue on port `83` for joint browser smoke tests.

## 2026-06-01 - frontend-agent - Frontend Baseline Import

### Completed Content

- Copied the original frontend from `F:\AI\projects\testJava\OA\snowy-admin-web` into `F:\AI\projects\testJava\OA-ThinkPHP\snowy-admin-web`.
- Kept the Java source frontend read-only and did not edit any file under `F:\AI\projects\testJava\OA`.
- Copied 908 frontend source/config/static files.
- Excluded IDE, dependency, build, coverage, log, and Vite timestamp artifacts.
- Verified the copied frontend includes `package.json`, Vite config, environment files, `public`, and `src`.
- Checked copied frontend environment keys without printing values.

### Modified Files

- `snowy-admin-web/**`
- `STATUS.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/frontend-joint-test-workflow.md`

### Test Results

- Target frontend file count: 908.
- Excluded directories/files were not present in the copied target.
- High-risk secret-marker scan found only frontend configuration form field names for `SECRET_KEY`; no committed credential values were printed or identified.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed.
- `git diff --cached --check`: passed after whitespace cleanup in the copied frontend files.
- Frontend dependency install and browser startup were not run in this import-only step.

### Current Issues

- `package-lock.json` exists in the copied frontend directory but is ignored by the original frontend `.gitignore`.
- The copied frontend still uses the original request/token behavior and must be adapted before full browser testing.
- The backend convention is `Authorization: Bearer <token>`, while the original frontend code uses a legacy token header.

### Next Plan

- Commit and push the frontend baseline import.
- Generate `docs/tasks/api-gap-map.md` from the copied frontend API files.
- Adapt request/token/menu behavior in small frontend-agent commits.
- Start MySQL/Redis, backend port `82`, and frontend port `83` for joint smoke testing after the first adaptation slice.

## 2026-06-01 - frontend-agent - Frontend Request Boundary Adaptation

### Completed Content

- Adapted the copied Vue frontend request boundary for ThinkPHP local joint testing.
- Switched browser calls to use a Vite proxy prefix instead of directly calling the backend URL from the browser.
- Removed the duplicated Axios `/api` base URL so Vite rewrites requests from `/api/...` to the backend route path correctly.
- Updated the frontend token convention to `Authorization: Bearer <token>` for normal requests, uploads, and SSE connections.
- Adapted the frontend first-menu selection helper to treat `children: []` as a leaf node.
- Moved SM2 public-key usage to `VITE_PUBLIC_KEY`; local development without a configured key now submits plaintext password values for the ThinkPHP compatibility path.
- Kept the original Java frontend source read-only and did not edit any file under `F:\AI\projects\testJava\OA`.

### Modified Files

- `snowy-admin-web/.env.development`
- `snowy-admin-web/.env.production`
- `snowy-admin-web/src/config/index.js`
- `snowy-admin-web/src/utils/smCrypto.js`
- `snowy-admin-web/src/components/XnUpload/index.vue`
- `snowy-admin-web/src/layout/components/message.vue`
- `snowy-admin-web/src/layout/components/panel-message/index.vue`
- `snowy-admin-web/src/utils/request.js`
- `snowy-admin-web/src/utils/routerUtil.js`
- `docs/tasks/frontend-adaptation-notes.md`
- `STATUS.md`

### Test Results

- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed.
- `npm ci --no-audit --no-fund`: passed.
- `npm run build`: passed with upstream warnings only.
- `git diff --check`: passed with CRLF conversion warnings only.
- Local MySQL and Redis startup: passed using the user-specified helper.
- ThinkPHP dev server on port `82`: started.
- Vue dev server on port `83`: started.
- Browser smoke: fresh-origin login succeeded and reached `/sys/org`.
- Browser smoke: `/sys/org` and `/sys/user` loaded with menus, tables, and pagination.

### Current Issues

- The frontend API gap map is still pending.
- Some frontend pages will still hit routes that are not implemented or are intentionally read-only.
- Org/user table rows show partially blank fields and missing dictionary labels, so response-field and dictionary compatibility still need a follow-up slice.
- Frontend SSE calls `/dev/message/createSseConnect`, which is not yet implemented in ThinkPHP and currently returns 404.

### Next Plan

- Generate `docs/tasks/api-gap-map.md` from the copied frontend API files.
- Add a small compatibility plan for org/user field names and dictionary labels.
- Add or defer a safe SSE compatibility route after reviewing the Java implementation.
- Commit and push this adaptation slice after the joint smoke result is recorded.

## 2026-06-01 - frontend-agent - Frontend API Gap Map

### Completed Content

- Generated the frontend API gap map from the copied Vue API wrappers under `snowy-admin-web/src/api`.
- Compared static frontend endpoint references with the current ThinkPHP route table.
- Classified gaps into already routed endpoints, missing read/selector/report candidates, and deferred write/side-effect candidates.
- Updated the progress dashboard with frontend endpoint metrics and next execution order.
- Kept this slice documentation-only; no Controller, Service, Model, route, database, or Java source file was modified.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- Static scan source: 76 frontend API wrapper files.
- Static scan result: 545 unique frontend endpoints.
- Current route baseline: 179 ThinkPHP route entries.
- Matched routes: 173 frontend endpoint paths.
- Missing read/selector/report candidates: 165.
- Deferred write/side-effect candidates: 207.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- The old frontend still has missing route consumers, especially sale-project, customer, biz org/user/position selectors, workflow query/runtime detail, upload, SSE, and report endpoints.
- Several existing visible pages load but still need response-field and dictionary-label compatibility cleanup.
- Write-heavy routes remain intentionally deferred.

### Next Plan

- Run a small frontend-agent/api-agent follow-up for visible org/user field display and dictionary labels.
- Review Java SSE behavior before deciding whether to add `/dev/message/createSseConnect`.
- Start api-agent read-only slices for `biz/saleproject` and `biz/customer`.
- Keep production online realtime data sync deferred until project completion and user confirmation.

## 2026-06-01 - user-agent - Sys Org/User Display Field Compatibility

### Completed Content

- Added camelCase display aliases to existing read-only system organization, user, and position service responses.
- Preserved uppercase SQL fields in responses for current backend compatibility.
- Added batched `orgName` and `positionName` enrichment to user rows and selectors to avoid N+1 lookups.
- Added `genderName` fallback from `dev_dict` where available.
- Added pagination aliases `current`, `size`, and `pages` on org/user/position page responses for copied frontend table compatibility.
- Documented the compatibility contract in `docs/api/sys-user-org-display-compat.md`.
- Kept this slice read-only; no route, Controller, database, Java source, or write endpoint was changed.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/user/OrgService.php`
- `app/service/user/UserDirectoryService.php`
- `app/service/user/PositionService.php`
- `docs/api/sys-user-org-display-compat.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\service\user\OrgService.php`: passed.
- `php -l app\service\user\UserDirectoryService.php`: passed.
- `php -l app\service\user\PositionService.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- `npm run build`: passed after rerunning with filesystem permission escalation; warnings are upstream bundle size, Browserslist age, CSS comment syntax, and `eval` in `docx-templates`.
- Direct backend API probes with a fresh token passed:
  - `/sys/org/page` returns `id`, `parentId`, `name`, `category`, and `sortCode`.
  - `/sys/org/tree` returns normalized tree nodes with `id`, `parentId`, `name`, `category`, `sortCode`, and `children`.
  - `/sys/user/page` returns `id`, `name`, `orgName`, `positionName`, `userStatus`, and `sortCode`.
- Browser smoke reached `/sys/org` and `/sys/user`; the remaining visible issue is still the known missing SSE route `/dev/message/createSseConnect`.

### Current Issues

- The browser session may still show empty table state until a fresh reload/login clears stale page state, but direct API probes confirm the response fields are now present.
- `/dev/message/createSseConnect` remains missing and still logs a frontend 404.
- Write actions on org/user/position pages remain intentionally deferred.

### Next Plan

- Review Java message/SSE behavior and decide whether to add a safe `/dev/message/createSseConnect` compatibility route.
- Start small api-agent read-only slices for `biz/saleproject` and `biz/customer`.
- Keep final online realtime data sync deferred until the full system is complete and the user confirms the plan.

## 2026-06-01 - api-agent/frontend-agent - Dev Message SSE Compatibility Review

### Completed Content

- Reviewed the Java SSE route behind `/dev/message/createSseConnect` from the read-only Java OA source.
- Confirmed the copied Vue frontend opens the same route from the layout message components with a bearer-token EventSource header.
- Documented the compatible first-slice behavior for ThinkPHP: authenticated `text/event-stream`, initial `code = 0` client id event, and lightweight heartbeat.
- Added a public-file change request because implementing the route requires editing locked file `route/app.php`.
- Kept this slice planning-only; no route, Controller, Service, frontend, database, Java source, Composer, or `.env` file was changed.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `docs/api/dev-message-sse-compat-plan.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; SSE route remains intentionally absent because this slice did not edit `route/app.php`.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- `/dev/message/createSseConnect` remains unimplemented until the public-file route request is approved or handled by merge-agent.
- Full realtime message/task push behavior remains deferred because it crosses message mutation, workflow, and later Redis/pub-sub design.

### Next Plan

- After approval, add the minimal SSE route/controller behavior and browser-smoke the layout console.
- In parallel-safe order, continue api-agent read-only slices for `biz/saleproject` and `biz/customer`.
- Keep final online realtime data sync deferred until the full system is complete and the user confirms the sync plan.

## 2026-06-01 - api-agent - Minimal Dev Message SSE Compatibility

### Completed Content

- Added the protected ThinkPHP route `GET /dev/message/createSseConnect` under the existing `dev/message` route group.
- Added `MessageController::createSseConnect` and delegated the response generation to a new `MessageSseService`.
- Added a minimal Java-compatible SSE response with:
  - `Content-Type: text/event-stream`
  - initial `code = 0` client id event
  - `code = 200` `FlushMessageNotice` compatibility event
  - heartbeat comment
- Kept the response short-lived to avoid blocking the local `php think run` development server.
- Updated the SSE compatibility doc and marked the public-file request as applied.
- Did not modify Java source, database schema, frontend files, Composer files, `.env`, message mutation routes, workflow side effects, Redis pub/sub, or production realtime sync.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/dev/MessageController.php`
- `app/service/dev/MessageSseService.php`
- `docs/api/dev-message-sse-compat-plan.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\dev\MessageController.php`: passed.
- `php -l app\service\dev\MessageSseService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; route table includes `dev/message/createSseConnect`.
- Unauthenticated HTTP probe: returned API `code = 401` from auth middleware.
- Authenticated HTTP probe: returned HTTP 200 with `text/event-stream` and initial `code = 0` client id event.
- Browser smoke on `http://localhost:83/index`: page loaded to the system home view and recent logs showed no new `createSseConnect` / EventSource 404.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- This is not full realtime push. It only removes the missing-route gap and returns compatible initial events.
- Full long-lived SSE, Redis pub/sub fanout, and workflow/message push side effects remain deferred.

### Next Plan

- Commit and push this small api-agent slice.
- Continue with read-only `biz/saleproject` and `biz/customer` API slices.

## 2026-06-02 - api-agent - Biz Sale Project Read-Only API Compatibility

### Completed Content

- Analyzed the Java sale-project Controller/Service and the `oa2026.sql` table structures for sale-project read flows.
- Added protected ThinkPHP read routes for:
  - `/biz/saleproject/page`
  - `/biz/saleproject/case/page`
  - `/biz/saleproject/operation/page`
  - `/biz/saleproject/public/page`
  - `/biz/saleproject/list/detail`
  - `/biz/saleproject/detail`
  - `/biz/saleproject/product`
- Added a thin `SaleProjectController` and read-only `SaleProjectService`.
- Returned Java/frontend-compatible fields for sale project list/detail/product-item display, including customer/user/org/account display names and aggregate detail lists.
- Preserved product child `extJson` compatibility for frontend parsing.
- Fixed the ThinkORM case-list join to use `join(..., 'INNER')` because `innerJoin()` is not available in this installed ORM version.
- Registered nested saleproject paths as explicit full routes to avoid stale local route-cache behavior during `php think run`.
- Confirmed and documented the corrected local MySQL/Redis helper path: `F:\project\socket\AI\testPhp\files\startServer1.bat`.
- Kept Java source, database schema, frontend files, Composer files, `.env`, and sale-project write endpoints unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `think`
- `app/controller/biz/SaleProjectController.php`
- `app/service/biz/SaleProjectService.php`
- `docs/api/biz-saleproject-readonly.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/runtime-verification-plan.md`

### Test Results

- `php -l app\controller\biz\SaleProjectController.php`: passed.
- `php -l app\service\biz\SaleProjectService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; all seven saleproject read routes are listed.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- MySQL and Redis local services are reachable.
- Backend smoke on `http://127.0.0.1:82`: passed.
- Frontend smoke on `http://127.0.0.1:83`: passed.
- Unauthenticated `/biz/saleproject/page`: returned API `code = 401`.
- Authenticated saleproject probes with a fresh local token:
  - `/biz/saleproject/page`: `code = 200`, total `8`.
  - `/biz/saleproject/detail`: `code = 200`.
  - `/biz/saleproject/product`: `code = 200`.
  - `/biz/saleproject/case/page`: `code = 200`.
  - `/biz/saleproject/operation/page`: `code = 200`.
  - `/biz/saleproject/public/page`: `code = 200`.
  - `/biz/saleproject/list/detail`: `code = 200`.

### Current Issues

- Sale-project write routes remain intentionally deferred.
- Weighted-average inventory cost endpoints remain intentionally deferred because they require a dedicated inventory/finance plan.
- Customer detail and other adjacent page APIs may still need the next read-only `biz/customer` slice.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this saleproject read-only slice.
- Continue with the next api-agent read-only slice for `biz/customer`, because sale-project pages depend on customer detail/follow-up endpoints.
- Keep frontend and backend services available for the user's continued local testing.

## 2026-06-02 - api-agent - Biz Customer Read-Only API Compatibility

### Completed Content

- Analyzed the Java customer and customer-follow-up Controller/Service/entity flow and the `oa2026.sql` table structures for customer read flows.
- Added protected ThinkPHP read routes for:
  - `/biz/customer/page`
  - `/biz/customer/detail`
  - `/biz/customer/detail/list`
  - `/biz/customerfollowup/page`
  - `/biz/customerfollowup/detail`
- Added thin `CustomerController` and `CustomerFollowUpController` adapters.
- Added read-only `CustomerService` and `CustomerFollowUpService` query services.
- Returned Java/frontend-compatible fields for customer display, including `headName`, `orgName`, `createUserName`, `downloadPath`, and `firstContactTime`.
- Returned Java/frontend-compatible follow-up display fields, including `customerName`, `createUserName`, `avatar`, `createUserOrgId`, and `createUserOrgName`.
- Documented the SM4 limitation for customer `PHONE` and `DETAILS_ADDRESS`: stored values are preserved, while plaintext decrypt/search remains deferred.
- Kept Java source, database schema, frontend files, Composer files, `.env`, and customer/follow-up write endpoints unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/CustomerController.php`
- `app/controller/biz/CustomerFollowUpController.php`
- `app/service/biz/CustomerService.php`
- `app/service/biz/CustomerFollowUpService.php`
- `docs/api/biz-customer-readonly.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\CustomerController.php`: passed.
- `php -l app\controller\biz\CustomerFollowUpController.php`: passed.
- `php -l app\service\biz\CustomerService.php`: passed.
- `php -l app\service\biz\CustomerFollowUpService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; all five customer and follow-up read routes are listed.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- MySQL, Redis, backend port `82`, and frontend port `83` are reachable.
- Frontend `/index` HTTP smoke on `http://127.0.0.1:83`: returned HTTP 200.
- Unauthenticated `/biz/customer/page`: returned API `code = 401`.
- Authenticated customer probes:
  - `/biz/customer/page`: `code = 200`, total `5020`.
  - `/biz/customer/detail`: `code = 200`.
  - `/biz/customer/detail/list`: `code = 200`, export-compatible rows returned.
  - `/biz/customerfollowup/page`: `code = 200`, total `53`.
  - `/biz/customerfollowup/detail`: `code = 200`.

### Current Issues

- Customer and customer-follow-up write routes remain intentionally deferred.
- Customer phone and detail-address plaintext decrypt/search remains deferred until an approved SM4 compatibility plan.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this customer read-only slice.
- Continue the api-agent read-only backlog with standalone invoice/invoicing/reissue/rating pages and remaining frontend-visible business reads.
- Keep backend and frontend services available for continued local testing.

## 2026-06-02 - api-agent - Sale Project Billing Read-Only API Compatibility

### Completed Content

- Analyzed Java sale-project invoicing, delivery invoice, reissue-order, and project-rate Controller/Service flow as read-only input.
- Added protected ThinkPHP read routes for:
  - `/biz/saleprojectinvoicing/page`
  - `/biz/saleprojectinvoicing/customer`
  - `/biz/saleprojectinvoicing/detail`
  - `/biz/saleprojectinvoice/page`
  - `/biz/saleprojectinvoice/list`
  - `/biz/saleprojectreissueorder/list/query`
  - `/biz/projectrate/page`
  - `/biz/projectrate/list`
- Added thin Controller adapters for invoicing, invoice, reissue-order, and project-rate reads.
- Added a read-only `SaleProjectBillingService` with Java/frontend-compatible page/list/detail structures.
- Preserved Java's invoiceable project state filter for invoicing pages: `PARTIALLY_SHIPPED`, `SHIPPED`, and `COMPLETED`.
- Returned invoice list entries with `bizSaleProjectInvoice` and `invoiceItems`.
- Returned reissue list entries with `order` and `productItemList`; product items include `children` and preserve relation `extJson`.
- Kept Java source, database schema, frontend files, Composer files, `.env`, and all billing/write/side-effect endpoints unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/SaleProjectInvoicingController.php`
- `app/controller/biz/SaleProjectInvoiceController.php`
- `app/controller/biz/SaleProjectReissueOrderController.php`
- `app/controller/biz/SaleProjectRateController.php`
- `app/service/biz/SaleProjectBillingService.php`
- `docs/api/biz-saleproject-billing-readonly.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l` for all new Controllers, the new Service, and `route/app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; all eight new routes are listed.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- MySQL, Redis, backend port `82`, and frontend port `83` are reachable.
- Unauthenticated `/biz/saleprojectinvoice/list`: returned API `code = 401`.
- Authenticated probes:
  - `/biz/saleprojectinvoicing/page`: `code = 200`, total `131`.
  - `/biz/saleprojectinvoicing/detail`: `code = 200`.
  - `/biz/saleprojectinvoice/page`: `code = 200`, total `236`.
  - `/biz/saleprojectinvoice/list`: `code = 200`.
  - `/biz/projectrate/page`: `code = 200`, total `62`.
  - `/biz/projectrate/list`: `code = 200`.
  - `/biz/saleprojectreissueorder/list/query`: `code = 200`; a known project with a reissue order returned the expected `order` and `productItemList` shape.

### Current Issues

- Billing, invoice, invoicing, reissue, project-rate, workflow, inventory, and finance write routes remain intentionally deferred.
- A one-off CLI DB probe hit a runtime log file permission lock while the local server was active; required framework and HTTP smoke checks passed, so no runtime files were modified.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this sale-project billing read-only slice.
- Continue the api-agent read-only backlog with remaining frontend-visible selectors/detail consumers.
- Keep backend and frontend services available for continued local testing.

## 2026-06-02 - user-agent - Biz Directory Alias Read-Only API Compatibility

### Completed Content

- Analyzed copied Vue wrappers for `/biz/org`, `/biz/user`, `/biz/position`, and `/biz/dict`.
- Analyzed Java `BizUserController` and service methods for `list/detail` and `ownRole` as read-only input.
- Added protected legacy business-side directory read routes for organization, user, position, and dictionary wrappers.
- Reused existing ThinkPHP `sys` and `dev` read controllers instead of duplicating user/org/position/dict business logic.
- Added `UserDirectoryService::listDetail()` for Java-compatible `/biz/user/list/detail` reads, including organization-child expansion for `orgId`.
- Added `UserDirectoryService::ownRole()` for Java-compatible `/biz/user/ownRole` reads from `sys_relation` category `SYS_USER_HAS_ROLE`.
- Added `DictService::treeAll()` for frontend-compatible `/biz/dict/treeAll` reads.
- Documented the route aliases and deferred write routes.
- Kept Java source, database schema, frontend files, Composer files, `.env`, and all user/org/position/dict write endpoints unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/sys/UserController.php`
- `app/controller/dev/DictController.php`
- `app/service/user/UserDirectoryService.php`
- `app/service/dev/DictService.php`
- `docs/api/biz-directory-alias-readonly.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\sys\UserController.php`: passed.
- `php -l app\controller\dev\DictController.php`: passed.
- `php -l app\service\user\UserDirectoryService.php`: passed.
- `php -l app\service\dev\DictService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; all twenty-two `/biz/org`, `/biz/user`, `/biz/position`, and `/biz/dict` read aliases are listed.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- MySQL, Redis, and frontend port `83` were reachable during this phase.
- Initial HTTP smoke before backend restart confirmed unauthenticated `/biz/org/tree` returned API `code = 401`; authenticated selector routes returned data.

### Current Issues

- After stopping a previously hung local backend process, repeated attempts to restart the ThinkPHP built-in server on port `82` from this sandbox did not produce a stable responding HTTP process, even though foreground `php think run` can start. This appears to be a local process-management issue, not a route or syntax failure.
- Because of the backend process-management issue, the final HTTP smoke matrix for all new aliases was not completed in this turn.
- User/org/position/dict write routes, role grant, user status/password actions, import/export, and profile writes remain intentionally deferred.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this directory alias read-only slice.
- When the user or next test slice starts the backend manually, re-run browser/HTTP smoke for `/biz/org`, `/biz/user`, `/biz/position`, and `/biz/dict`.
- Continue the read-only backlog with workflow query/detail reads and business report reads.

## 2026-06-02 - workflow-agent/api-agent - Workflow Read Alias Compatibility

### Completed Content

- Analyzed Java `BizProcessController`, `BizProcessProjectController`, and `BizTaskController` as read-only input.
- Analyzed copied Vue workflow wrappers for `/biz/process/*` and `/biz/task/*`.
- Added protected read-only workflow routes for:
  - `/biz/process/all/page`
  - `/biz/process/query`
  - `/biz/process/query/list`
  - `/biz/process/project/runtime/query/list`
  - `/biz/process/fileList`
  - `/biz/task/runtime/activity/detail`
- Added frontend-friendly workflow process row aliases including `id`, `instanceId`, `category`, `title`, `status`, and `variable`.
- Made process detail and variable reads accept either `processInstanceId` or Java/frontend `id`.
- Added workflow detail response compatibility fields: `userProcess`, `startUser`, `startOrgTree`, `userActivityList`, and `ccUser`.
- Added runtime activity detail reads from `act_ru_task` and normalized runtime variables.
- Updated API docs, API gap map, public-file route request, and progress dashboard.
- Kept Java source, database schema, frontend files, Composer files, `.env`, process starts, task approve/reject, task SSE, and workflow side effects unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/ProcessController.php`
- `app/controller/biz/TaskController.php`
- `app/service/workflow/WorkflowQueryService.php`
- `docs/api/biz-workflow-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\ProcessController.php`: passed.
- `php -l app\controller\biz\TaskController.php`: passed.
- `php -l app\service\workflow\WorkflowQueryService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; all six new workflow read routes are listed.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- CLI workflow smoke passed:
  - `allProcessPage`: returned 2 rows from total 2915.
  - `processDetail`: returned Java/frontend-compatible shape.
  - `projectRuntimeQueryList`: returned 1 row.
  - `runtimeActivityDetail`: skipped because current imported runtime data has no assigned pending task.

### Current Issues

- Task approve/reject, process start/cancel, task SSE, and workflow side effects remain intentionally deferred.
- Runtime ACL for ThinkPHP `runtime` was repaired for the current local Codex user so normal `php think route:list` can write generated route/log files.
- Full browser smoke for workflow pages still needs the backend dev server running stably on port `82`.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this workflow read alias slice.
- Continue with business report, payroll, leave, sale-project-product-info, and settlement-account-payment read-only slices.
- Keep frontend and backend joint smoke in the loop after the backend dev server is stable.

## 2026-06-03 - api-agent - Sale Project Product Info Read-Only API Compatibility

### Completed Content

- Analyzed Java `BizSaleProjectProductInfoController`, entity, param, and service implementation as read-only input.
- Analyzed copied Vue `bizSaleProjectProductInfoApi` wrapper and `saleprojectproductinfo` page usage.
- Added protected read-only routes for:
  - `/biz/saleprojectproductinfo/page`
  - `/biz/saleprojectproductinfo/list`
  - `/biz/saleprojectproductinfo/detail`
- Added a thin `SaleProjectProductInfoController` adapter.
- Added a read-only `SaleProjectProductInfoService` with Java/frontend-compatible fields.
- Preserved Java `targetIds` list behavior and accepted comma-separated frontend values.
- Added creator/updater and product display aliases for expanded frontend rows.
- Updated API docs, gap map, public-file route request, and progress dashboard.
- Kept Java source, database schema, frontend files, Composer files, `.env`, add/edit/delete, workflow, inventory, finance, and business write behavior unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/SaleProjectProductInfoController.php`
- `app/service/biz/SaleProjectProductInfoService.php`
- `docs/api/biz-saleproject-product-info-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\SaleProjectProductInfoController.php`: passed.
- `php -l app\service\biz\SaleProjectProductInfoService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; all three sale-project-product-info routes are listed.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- Redis port was reachable after the helper service start.
- MySQL initially rejected connections, then listened on port `3306` after starting the user-provided helper script.
- CLI read-only smoke passed:
  - `page`: returned 2 rows from total 9.
  - `detail`: returned row `1882232045490913281`.
  - `list`: returned 1 row for the sampled `targetId`.

### Current Issues

- Sale-project-product-info add/edit/delete routes remain intentionally deferred.
- The frontend page still has modal actions wired to write endpoints; those actions should be tested only after a dedicated write plan is approved.
- Reading CIM process details for local `mysqld.exe` was denied by Windows permissions, but port and database smoke checks passed.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this sale-project-product-info read-only slice.
- Continue with the remaining safe read-only backlog: business data reports, payroll, leave, and settlement-account-payment.
- Keep backend and frontend joint smoke in the loop for pages the user opens in browser testing.

## 2026-06-03 - api-agent - Biz Datareport Sale Project Details Read-Only API Compatibility

### Completed Content

- Analyzed Java `BizDataReportController`, `BizDataReportServiceImp`, and `BizDataReportQueryParam` as read-only input.
- Analyzed copied Vue `bizDataReportApi` and the sale-project-product-info page dependency on `saleProjectList/details`.
- Added protected read-only route:
  - `POST /biz/bizdatareport/saleProjectList/details`
- Added a thin `BizDataReportController` adapter.
- Added a read-only `BizDataReportService` that returns Java/frontend-compatible sale-project rows with nested `productList`, product item `children`, and `returnOrders`.
- Applied Java-compatible completion date, organization subtree, data-scope, and sale-project deal-state filters.
- Preserved long ID fields as strings and only normalized known amount/quantity fields.
- Updated API docs, gap map, public-file route request, and progress dashboard.
- Kept Java source, database schema, frontend files, Composer files, `.env`, report/profit/unpaid-payment/summary endpoints, workflow, inventory, finance, and business write behavior unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/BizDataReportController.php`
- `app/service/biz/BizDataReportService.php`
- `docs/api/biz-datareport-saleproject-details-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\BizDataReportController.php`: passed.
- `php -l app\service\biz\BizDataReportService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; `POST /biz/bizdatareport/saleProjectList/details` is listed.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- CLI read-only smoke passed:
  - `details`: returned 31 sale-project rows for the sampled January 2026 scope.
  - Response shape included `productList` and `returnOrders`.
  - First sampled project `id` remained a string.

### Current Issues

- The rest of `bizdatareport` remains intentionally deferred: sale profit, saleproject summary/list/report, unpaid payment, and summary statistics.
- The new route is used by the existing frontend sale-project-product-info page, but full browser smoke of that page should still be run with backend and frontend servers active.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this biz-datareport sale-project details read-only slice.
- Continue with the remaining safe read-only backlog: payroll, leave, settlement-account-payment, and remaining report reads in small slices.
- Keep backend and frontend joint smoke in the loop for visible pages.

## 2026-06-03 - api-agent - Biz Leave Application Read-Only API Compatibility

### Completed Content

- Analyzed Java `BizLeaveApplicationController`, `BizLeaveApplicationServiceImpl`, entity, and page param as read-only input.
- Analyzed copied Vue `bizLeaveApplicationApi`, leave list page, modal selector page, and detail component usage.
- Added protected read-only routes for:
  - `/biz/bizleaveapplication/page`
  - `/biz/bizleaveapplication/my/page`
  - `/biz/bizleaveapplication/detail`
- Added a thin `BizLeaveApplicationController` adapter.
- Added a read-only `BizLeaveApplicationService` with Java/frontend-compatible filters and fields.
- Preserved Java page behavior: data-scope organization filtering when available, current-user fallback otherwise.
- Preserved Java my-page behavior by always restricting records to the current user.
- Updated API docs, gap map, public-file route request, and progress dashboard.
- Kept Java source, database schema, frontend files, Composer files, `.env`, add/edit/delete, workflow start/approval/cancel, finance, inventory, and business write behavior unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/BizLeaveApplicationController.php`
- `app/service/biz/BizLeaveApplicationService.php`
- `docs/api/biz-leave-application-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\BizLeaveApplicationController.php`: passed.
- `php -l app\service\biz\BizLeaveApplicationService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; all three leave-application read routes are listed.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- CLI read-only smoke passed:
  - `page`: returned 2 rows from total 6 in the sampled data-scope organization.
  - `myPage`: returned 2 rows from total 4 for the sampled user.
  - `detail`: returned sampled row `2008808074807599105` with applicant name.
  - `filterPage`: returned 1 row from total 1 for sampled category and start-time filters.

### Current Issues

- Leave add/edit/delete and workflow start/approval/cancel routes remain intentionally deferred.
- Full browser smoke for the leave-application page should still be run with backend and frontend servers active.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this leave-application read-only slice.
- Continue with the remaining safe read-only backlog: payroll, settlement-account-payment, and remaining report reads in small slices.
- Keep backend and frontend joint smoke in the loop for visible pages.

## 2026-06-03 - api-agent - Settlement Account Payment Read-Only API Compatibility

### Completed Content

- Analyzed Java `SettlementAccountStatementController`, `SettlementAccountStatementServiceImpl`, entity, page param, and query param as read-only input.
- Analyzed copied Vue `settlementAccountPaymentApi` and the settlement-account detail statement tab usage.
- Added protected read-only routes for:
  - `/biz/settlementaccountpayment/page`
  - `/biz/settlementaccountpayment/list`
- Added a thin `SettlementAccountPaymentController` adapter.
- Added a read-only `SettlementAccountPaymentService` with Java/frontend-compatible filters and fields.
- Supported Java `startPlayTime/endPlayTime` filters and frontend `startPayerTime/endPayerTime` aliases.
- Added display aliases for account name, account number, organization name, creator name, and updater name.
- Updated API docs, gap map, public-file route request, and progress dashboard.
- Kept Java source, database schema, frontend files, Composer files, `.env`, settlement account payment/transfer/income/expense mutations, workflow side effects, and balance changes unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/SettlementAccountPaymentController.php`
- `app/service/biz/SettlementAccountPaymentService.php`
- `docs/api/biz-settlement-account-payment-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\SettlementAccountPaymentController.php`: passed.
- `php -l app\service\biz\SettlementAccountPaymentService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; both settlement-account-payment read routes are listed.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- CLI read-only smoke passed:
  - `page`: returned 2 rows from total 218 for the sampled account.
  - `list`: returned 218 rows for the sampled account with `payerTime` descending sort.
  - `filter`: returned 1 row for sampled `startPlayTime/endPlayTime`.

### Current Issues

- Settlement account payment creation, transfer, income/expense mutations, workflow side effects, and balance changes remain intentionally deferred.
- Full browser smoke for the settlement-account detail statement tab should still be run with backend and frontend servers active.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this settlement-account-payment read-only slice.
- Continue with the remaining safe read-only backlog: payroll and remaining report reads in small slices.
- Keep backend and frontend joint smoke in the loop for visible pages.

## 2026-06-03 - api-agent - Biz Payroll Read-Only API Compatibility

### Completed Content

- Analyzed Java `BizPayrollController`, `BizPayrollServiceImpl`, entity, page param, and `biz_payroll` SQL table as read-only input.
- Analyzed copied Vue payroll page and user payroll tab usage.
- Added protected read-only routes for:
  - `/biz/bizpayroll/page`
  - `/biz/bizpayroll/mypage`
  - `/biz/bizpayroll/detail`
- Added a thin `BizPayrollController` adapter.
- Added a read-only `BizPayrollService` with Java/frontend-compatible salary fields, salary month filters, organization subtree filtering, current-user my-page filtering, and data-scope guards.
- Added display aliases for `headName`, `name`, `userAccount`, `orgName`, creator name, and updater name.
- Updated API docs, gap map, public-file route request, and progress dashboard.
- Kept Java source, database schema, frontend files, Composer files, `.env`, payroll import/export/generate/add/edit/batch edit/delete behavior, workflow, finance, and business write behavior unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/BizPayrollController.php`
- `app/service/biz/BizPayrollService.php`
- `docs/api/biz-payroll-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\BizPayrollController.php`: passed.
- `php -l app\service\biz\BizPayrollService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; all three payroll read routes are listed.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- CLI read-only smoke passed:
  - `biz_payroll` currently has no imported rows in the configured database.
  - Empty page query returned `page=0` and `total=0` without SQL or service errors.

### Current Issues

- Payroll import, export, generate, add, edit, batch edit, and delete routes remain intentionally deferred.
- Current database has no `biz_payroll` records, so detail-row smoke should be repeated after payroll data is imported or created by a confirmed write flow.
- Full browser smoke for payroll pages should still be run with backend and frontend servers active.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this payroll read-only slice.
- Continue with the remaining safe read-only backlog: business report endpoints and remaining detail consumers in small slices.
- Keep backend and frontend joint smoke in the loop for visible pages.

## 2026-06-03 - api-agent - Biz Datareport Sale Project Summary Reads

### Completed Content

- Analyzed Java `BizDataReportController`, `BizDataReportServiceImp`, query params, and sale-project report result classes as read-only input.
- Analyzed copied Vue `bizDataReportApi` and data-report dashboard usage.
- Added protected read-only routes for:
  - `/biz/bizdatareport/saleproject`
  - `/biz/bizdatareport/saleproject/list`
  - `/biz/bizdatareport/saleproject/report`
- Extended the existing thin `BizDataReportController` adapter.
- Extended `BizDataReportService` with:
  - sale-project total amount aggregation;
  - sale-project amount row list;
  - sale-project status/time report list.
- Preserved Java filter behavior:
  - `saleproject` and `saleproject/list` filter by completion date and成交 project states;
  - `saleproject/report` filters by create time or completion date and returns status/time rows;
  - data scope uses token organization ids with current-user fallback.
- Updated API docs, gap map, public-file route request, and progress dashboard.
- Kept Java source, database schema, frontend files, Composer files, `.env`, sale profit, unpaid payment, settlement income/expenses, summary statistics, workflow, finance mutation, and business write behavior unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/BizDataReportController.php`
- `app/service/biz/BizDataReportService.php`
- `docs/api/biz-datareport-saleproject-summary-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\BizDataReportController.php`: passed.
- `php -l app\service\biz\BizDataReportService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; all three sale-project summary report routes are listed.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- CLI read-only smoke passed:
  - `saleProjectAmount`: returned sampled amount `0`.
  - `saleProjectList`: returned 1 row for the sampled organization and completion month.
  - `saleProjectReport`: returned 1 status/time row for the sampled organization and month.
  - The sampled row itself has `TOTAL_PRICE = 0.00`, matching the amount smoke result.

### Current Issues

- Sale profit, unpaid payment, settlement income/expenses, and summary statistics remain intentionally deferred.
- Full browser smoke for the data-report dashboard should still be run with backend and frontend servers active.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this biz-datareport sale-project summary read-only slice.
- Continue with remaining business report reads in small slices, likely unpaid payment first because it is close to the existing sale-project report query.
- Keep backend and frontend joint smoke in the loop for visible pages.

## 2026-06-03 - api-agent - Biz Datareport Sale Project Unpaid Payment Read

### Completed Content

- Analyzed Java `BizDataReportServiceImp#querySaleProjectUnpaidPayment` and `BizDataReportEnum` as read-only input.
- Confirmed copied Vue dashboard calls `bizSaleProjectDataReportUnpaidPayment` for the current-month unpaid card.
- Added protected read-only route:
  - `/biz/bizdatareport/saleproject/UnpaidPayment`
- Extended the existing thin `BizDataReportController` adapter.
- Extended `BizDataReportService` with Java-compatible unpaid amount aggregation.
- Preserved Java filter behavior:
  - completion-date range filter;
  -成交 project states;
  - `UNPAID` and `PARTIALLY_PAID` play states;
  - data-scope organization ids with current-user fallback;
  - org subtree expansion.
- Preserved Java calculation: `totalPrice - amountCollected + totalReturnAmount`.
- Updated API docs, gap map, public-file route request, and progress dashboard.
- Kept Java source, database schema, frontend files, Composer files, `.env`, sale profit, settlement income/expenses, summary statistics, workflow, finance mutation, and business write behavior unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/BizDataReportController.php`
- `app/service/biz/BizDataReportService.php`
- `docs/api/biz-datareport-saleproject-unpaid-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\BizDataReportController.php`: passed.
- `php -l app\service\biz\BizDataReportService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; the unpaid-payment report route is listed.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- CLI read-only smoke passed:
  - sampled project `2008016272152326146`;
  - formula check returned expected `6000`;
  - service returned `amount=6000`.

### Current Issues

- Sale profit, settlement income/expenses, and summary statistics remain intentionally deferred.
- Full browser smoke for the data-report dashboard should still be run with backend and frontend servers active.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this unpaid-payment read-only slice.
- Continue with remaining business report reads in small slices, likely settlement income/expenses next because they are pure read aggregations but touch payment/expenditure records.
- Keep backend and frontend joint smoke in the loop for visible pages.

## 2026-06-03 - api-agent - Biz Datareport Settlement Income And Expenses Reads

### Completed Content

- Analyzed Java `BizDataReportController` settlement report routes and `BizDataReportServiceImp#queryIncomeRecord/#queryExpenditureRecord` as read-only input.
- Confirmed copied Vue data-report pages call settlement income and expenses endpoints for frontend aggregation.
- Added protected read-only routes for:
  - `/biz/bizdatareport/settlement/income`
  - `/biz/bizdatareport/settlement/expenses`
- Extended the existing thin `BizDataReportController` adapter.
- Extended `BizDataReportService` with Java-compatible payment and expenditure record list reads.
- Preserved Java filter behavior:
  - selected organization plus child organizations;
  - token data-scope organization ids;
  - current-login-user fallback;
  - income category filter;
  - `startCreateTime/endCreateTime` applied to `PAYER_TIME`.
- Updated API docs, gap map, public-file route request, and progress dashboard.
- Kept Java source, database schema, frontend files, Composer files, `.env`, settlement mutations, account-balance updates, sale profit, summary statistics, workflow, and business write behavior unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/BizDataReportController.php`
- `app/service/biz/BizDataReportService.php`
- `docs/api/biz-datareport-settlement-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\BizDataReportController.php`: passed.
- `php -l app\service\biz\BizDataReportService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; both settlement report routes are listed.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- CLI read-only smoke passed:
  - `settlementIncome`: returned 1 row for sampled payment record `2053774062208327681`.
  - `settlementExpenses`: returned 1 row for sampled expenditure record `2054438640814563330`.

### Current Issues

- Sale profit and summary statistics remain intentionally deferred.
- Settlement account payment, transfer, income, expenses, and balance mutation write routes remain deferred.
- Full browser smoke for the data-report settlement page should still be run with backend and frontend servers active.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this settlement report read-only slice.
- Continue with remaining business report reads in small slices, likely sale profit or summary statistics next.
- Keep backend and frontend joint smoke in the loop for visible pages.

## 2026-06-03 - api-agent - Biz Datareport Sale Profit Read

### Completed Content

- Analyzed Java `BizDataReportController#querySaleProfitReport`, `BizDataReportServiceImp#getSaleProfitResult`, `SaleProfitResult`, and the copied Vue `saleProfit` page/WebWorker as read-only input.
- Confirmed the frontend expects raw Java-compatible collections and calculates profit in `saleProfit/webWork/calcProfit.js`.
- Added protected read-only route:
  - `/biz/bizdatareport/saleProfit`
- Extended the existing thin `BizDataReportController` adapter.
- Extended `BizDataReportService` with Java-compatible `projectlist`, `orderList`, and `bizProducts` output.
- Preserved Java sale-project filtering:
  - selected organization plus child organizations;
  - token data-scope organization ids;
  - current-login-user fallback;
  - completion-date range;
  -成交 project states.
- Preserved Java purchase-order filtering:
  - completed settlement status;
  - token data-scope organization ids;
  - current-login-user fallback through `CREATE_USER`.
- Added nested data needed by the frontend worker:
  - sale-project `productList`;
  - sale-project `returnOrders.productList`;
  - completed purchase-order `orderItems`;
  - product lookup rows.
- Omitted empty `children` arrays from sale-profit product rows so the frontend does not treat single products as kit products.
- Updated API docs, gap map, public-file route request, and progress dashboard.
- Kept Java source, database schema, frontend files, Composer files, `.env`, summary statistics, purchase/sale/return/inventory/settlement mutations, workflow, and business write behavior unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/BizDataReportController.php`
- `app/service/biz/BizDataReportService.php`
- `docs/api/biz-datareport-sale-profit-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\BizDataReportController.php`: passed.
- `php -l app\service\biz\BizDataReportService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; the sale-profit route is listed.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- CLI read-only smoke passed:
  - sample project `2054401155761872898` returned `projectlist=1`, `bizProducts=3324`, `productList=3`, and no empty `children` arrays.
  - sample completed purchase order `2053022436501659650` returned `orderList=114` and `orderItems=1` for the sampled order.

### Current Issues

- Summary statistics remains intentionally deferred.
- Full browser smoke for the sale-profit page should still be run with backend and frontend servers active.
- Purchase, sale, return, inventory, settlement, payment, workflow, and account-balance write behavior remains deferred.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this sale-profit read-only slice.
- Continue with the remaining `bizdatareport` read slice: `summary/statistics`.
- Keep backend and frontend joint smoke in the loop for visible pages.

## 2026-06-03 - api-agent - Biz Datareport Summary Statistics Read

### Completed Content

- Analyzed Java `BizDataReportController#querySummaryStatistics`, `BizDataReportServiceImp#querySummaryStatistics`, `BizQuerySummaryStatisticsResult`, and the copied Vue `summaryStatistics` page/WebWorker.
- Confirmed the frontend expects raw company-scoped collections and calculates annual/monthly finance values in `summaryStatistics/components/webWork/calcStatisics.js`.
- Added protected read-only route:
  - `/biz/bizdatareport/summary/statistics`
- Extended the existing thin `BizDataReportController` adapter.
- Extended `BizDataReportService` with Java-compatible summary output:
  - `org`
  - `settlementAccounts`
  - `paymentRecords`
  - `bizExpenditureRecords`
  - `bizSaleProjects`
  - `bizDebitNotes`
- Preserved the Java summary behavior of returning data grouped by company organization and bounded by selected-year end time.
- Kept the endpoint strictly read-only:
  - no settlement/account-balance mutation;
  - no workflow start/approval behavior;
  - no database schema change.
- Updated API docs, gap map, public-file route request, and progress dashboard.
- Kept Java source, database schema, frontend files, Composer files, `.env`, finance mutations, workflow writes, and business write behavior unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/BizDataReportController.php`
- `app/service/biz/BizDataReportService.php`
- `docs/api/biz-datareport-summary-statistics-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\BizDataReportController.php`: passed.
- `php -l app\service\biz\BizDataReportService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; the summary-statistics route is listed.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- CLI read-only smoke passed:
  - sample company scope returned 1 company summary object;
  - first summary object contains `org`, `settlementAccounts`, `paymentRecords`, `bizExpenditureRecords`, `bizSaleProjects`, and `bizDebitNotes`;
  - sample counts were: settlement accounts 19, payment records 263, expenditure records 731, sale projects 98, debit notes 52.

### Current Issues

- Full browser smoke for the summary-statistics page should still be run with backend and frontend servers active.
- Settlement account payment, transfer, income, expense, correction, and balance mutation write behavior remains deferred.
- Purchase, sale, return, inventory, workflow, and account-balance side effects remain deferred.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this summary-statistics read-only slice.
- Run backend plus frontend browser smoke for the summary statistics page when both services are active.
- Continue with the next safe read-only business detail/selector slice before opening write-heavy finance or workflow behavior.

## 2026-06-03 - test-agent - Summary Statistics Browser Smoke

### Completed Content

- Kept the Java project read-only and made no business-code changes.
- Confirmed local backend and frontend services were running:
  - ThinkPHP backend on `http://127.0.0.1:82`
  - Vue frontend on `http://127.0.0.1:83`
- Browser login smoke reached the authenticated layout.
- Opened `/biz/bizdatareport/summaryStatistics` through the copied Vue frontend.
- Confirmed browser title `汇总统计 - 福地科技`.
- Confirmed visible page content renders:
  - `汇总统计表`
  - month finance columns
  - company finance data rows
  - `未回款统计表`
- Checked ThinkPHP runtime log for new backend exceptions after the smoke.
- Recorded the frontend console observations in `docs/tasks/frontend-adaptation-notes.md`.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- Backend port `82`: open.
- Frontend port `83`: open.
- Browser smoke login: passed.
- Browser smoke `/biz/bizdatareport/summaryStatistics`: passed.
- Visible loading state after wait: not present.
- ThinkPHP runtime exception check: no new smoke-related runtime exception found.

### Current Issues

- Local WebPush permission failure still appears in browser console.
- Realtime message connection still logs disconnect errors from the layout message panel.
- Vite still reports upstream `docx-templates` Node built-in compatibility warnings.
- Screenshot capture timed out in the in-app browser on this heavy report page; DOM and visible text checks were used instead.
- Write-heavy finance, workflow, stock, and account-balance side effects remain deferred.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this browser-smoke documentation slice.
- Continue with the next safe read-only visible business page or detail API before opening finance/workflow writes.
- Add a later test-agent task for the realtime message/WebPush console noise.

## 2026-06-03 - api-agent - Sale Project Cost Read

### Completed Content

- Analyzed Java `BizSaleProjectController#cost`, `BizSaleProjectController#costDetails`, `BizSaleProjectServiceImpl#calculateSaleItemCostByWeightedAverageDetail`, `BizPurchaseOrderServiceImpl#calcProductCost`, and cost result classes.
- Added protected read-only routes:
  - `/biz/saleproject/cost`
  - `/biz/saleproject/cost/details`
- Extended `SaleProjectController` with thin guarded adapters for both routes.
- Extended `SaleProjectService` with read-only cost detail calculation:
  - verifies sale-project access through the existing data-scope-aware query;
  - reads sale-project product items;
  - expands combo-product child rows;
  - attaches return orders with `productList`;
  - reads completed purchase order item unit amounts;
  - returns `items`, `productItems`, and `returnOrders`.
- Added API documentation and public-file route change request.
- Updated the API gap map and progress dashboard.
- Ran a browser smoke for `/biz/saleproject` after the route slice.
- Kept Java source, database schema, frontend files, Composer files, `.env`, purchase/inventory/finance/workflow writes, and account-balance behavior unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/SaleProjectController.php`
- `app/service/biz/SaleProjectService.php`
- `docs/api/biz-saleproject-cost-readonly.md`
- `docs/api/biz-saleproject-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\SaleProjectController.php`: passed.
- `php -l app\service\biz\SaleProjectService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; both sale-project cost routes are listed.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- Direct service smoke passed:
  - sample project returned `items=11`, `productItems=2`, `returnOrders=0`, and a numeric cost.
- Authenticated route smoke passed:
  - local login `code=200`;
  - `/biz/saleproject/page` `code=200`;
  - `/biz/saleproject/cost/details` `code=200` for a visible sample project;
  - `/biz/saleproject/cost` `code=200` for a visible sample project;
  - unauthenticated `/biz/saleproject/cost/details` returned `code=401`.
- Browser smoke for `/biz/saleproject` passed for page load:
  - title `销售项目管理 - 福地科技`;
  - table header visible;
  - no loading state after wait.

### Current Issues

- The browser sale-project table showed `暂无数据`, while backend API smoke returned visible project records. This should be investigated later as frontend query/filter/display compatibility.
- The current browser-visible sale-project result did not expose a project with product items, so deep cost-tab browser smoke remains deferred.
- Local realtime message connection console noise still appears from the layout message panel.
- Vite still reports upstream `docx-templates` Node built-in compatibility warnings.
- Sale project writes, purchase/inventory mutations, finance writes, workflow side effects, and account-balance updates remain deferred.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this sale-project cost read-only slice.
- Continue with the remaining safe read-only candidates from the refreshed frontend scan:
  - sale-project follow-up reads;
  - sale-project product-item relation list;
  - draft/history/cc-record visible reads.
- Keep finance, inventory, workflow, and account-balance writes deferred until their dedicated plans are confirmed.

## 2026-06-03 - api-agent - Sale Project Follow-Up Read

### Completed Content

- Analyzed Java `SaleProjectFollowUpController`, `SaleProjectFollowUpServiceImpl`, `SaleProjectFollowUp`, page/id params, and the `sale_project_follow_up` SQL table from `oa2026.sql`.
- Analyzed copied Vue callers:
  - `snowy-admin-web/src/api/biz/saleProjectFollowUpApi.js`;
  - standalone `saleprojectfollowup/index.vue`;
  - sale-project detail follow-up tab.
- Added protected read-only routes:
  - `/biz/saleprojectfollowup/page`
  - `/biz/saleprojectfollowup/detail`
- Added a thin ThinkPHP controller and data-scope-aware read service.
- Returned Java/frontend-compatible fields including `projectName`, creator display fields, avatar, creator org display, and unchanged `extJson`.
- Kept follow-up add/edit/delete, upload, attachment persistence, workflow, sale-project writes, finance, account-balance behavior, frontend files, Java source, Composer files, `.env`, and database schema unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/SaleProjectFollowUpController.php`
- `app/service/biz/SaleProjectFollowUpService.php`
- `docs/api/biz-saleproject-followup-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\SaleProjectFollowUpController.php`: passed.
- `php -l app\service\biz\SaleProjectFollowUpService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; both sale-project follow-up routes are listed.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- Direct service smoke passed:
  - local sample returned `followup_total=836`, `followup_records=3`;
  - detail for the first sampled record matched the sampled id.
- Authenticated HTTP smoke passed:
  - local login `code=200`;
  - `/biz/saleprojectfollowup/page` `code=200`, `total=836`;
  - `/biz/saleprojectfollowup/detail` `code=200`;
  - unauthenticated `/biz/saleprojectfollowup/page` returned `code=401`.
- Browser smoke found `/biz/saleprojectfollowup` currently renders the copied Vue 404 page because the standalone route/menu entry is not exposed; browser was restored to `/biz/saleproject`.

### Current Issues

- Standalone sale-project follow-up page route/menu exposure remains a frontend adaptation task.
- Sale-project detail follow-up tab deep smoke remains tied to the existing sale-project table visibility mismatch.
- Follow-up add/edit/delete writes remain deferred.
- Local realtime message connection console noise and Vite upstream warnings remain non-blocking known issues.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this sale-project follow-up read-only slice.
- Continue with the next small read-only sale-project/detail consumer, likely sale-project product-item relation or visible history/cc-record reads.
- Keep write-heavy sale-project, workflow, inventory, finance, file upload, and account-balance behavior deferred until dedicated write plans are confirmed.

## 2026-06-03 - api-agent - Sale Project Product Item Relation List Read

### Completed Content

- Analyzed Java `SaleProjectProductItemRelationController`, `SaleProjectProductItemRelationServiceImpl`, `SaleProjectProductItemRelation`, and the `sale_project_product_item_relation` SQL table from `oa2026.sql`.
- Analyzed copied Vue API wrapper `snowy-admin-web/src/api/biz/saleProjectProductItemRelationApi.js` and sale-project delivery/invoice helper usage.
- Added protected read-only route:
  - `/biz/saleprojectproductitemrelation/list`
- Added a thin ThinkPHP controller and read-only service.
- Returned relation rows with Java/frontend-compatible camelCase fields, `productId` alias, joined product display fields, and `extJson` fallback when missing.
- Scoped relation reads through `biz_sale_project_product_item -> biz_sale_project`.
- Kept relation mark edits, product item mark edits, delivery/invoice writes, sale-project writes, inventory, workflow, finance, account-balance behavior, frontend files, Java source, Composer files, `.env`, and database schema unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/SaleProjectProductItemRelationController.php`
- `app/service/biz/SaleProjectProductItemRelationService.php`
- `docs/api/biz-saleproject-product-item-relation-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- Initial local syntax check passed:
  - `php -l app\controller\biz\SaleProjectProductItemRelationController.php`
  - `php -l app\service\biz\SaleProjectProductItemRelationService.php`
  - `php -l route\app.php`
- `php think route:list`: passed; `/biz/saleprojectproductitemrelation/list` is listed and route count is 253.
- Direct service smoke passed:
  - sample object id `2007746037931307010`;
  - returned 10 relation rows;
  - first sampled row included `productId` and non-empty `extJson`.
- Full baseline checks passed:
  - `composer dump-autoload`;
  - `php think`;
  - `php think route:list`;
  - PHP lint for `app`, `config`, and `route`;
  - `git diff --check` with CRLF conversion warnings only.
- Authenticated HTTP smoke passed:
  - local login returned a bearer token;
  - `/biz/saleprojectproductitemrelation/list` returned `code = 200`, 10 rows, `productId`, and non-empty `extJson`;
  - unauthenticated `/biz/saleprojectproductitemrelation/list` returned `code = 401`.

### Current Issues

- Relation/product item mark edit routes remain deferred because they mutate data.
- Deep browser smoke remains deferred until a visible sale-project delivery/invoice helper flow is available.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this sale-project product item relation read-only slice.
- Continue with the next small read-only sale-project/detail consumer or move to frontend-agent investigation of the sale-project table empty-state mismatch.

## 2026-06-03 - api-agent/frontend-agent - Sale Project Page Data Scope Smoke Fix

### Completed Content

- Investigated the copied Vue `/biz/saleproject` page while the in-app browser was on `http://127.0.0.1:83/biz/saleproject`.
- Confirmed the page title and table shell loaded, but the table previously showed `暂无数据`.
- Read the sale-project page source and confirmed it forces `projectState=FOLLOW` before calling `/biz/saleproject/page`, then calls `/biz/process/query` for workflow amount lookup.
- Compared Java sale-project page filtering and existing ThinkPHP customer/follow-up/billing data-scope patterns.
- Added admin-compatible data-scope bypass to `SaleProjectService` for accounts/roles `bizAdmin`, `superadmin`, `tenantadmin`, and `bizadmin`.
- Kept ordinary user data scope, org filters, tenant filters, frontend files, route files, Java source, database schema, Composer files, `.env`, sale-project writes, workflow writes, inventory, finance, and account-balance behavior unchanged.

### Modified Files

- `app/service/biz/SaleProjectService.php`
- `docs/api/biz-saleproject-readonly.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### Test Results

- `php -l app\service\biz\SaleProjectService.php`: passed.
- Authenticated frontend-shaped `/biz/saleproject/page?projectState=FOLLOW&showDiscard=false`: returned `code = 200`, `total = 254`, and 10 rows.
- `/biz/process/query` for those sale-project ids returned `code = 200` and 10 workflow lookup items.
- Browser reload of `/biz/saleproject`: page title remained `销售项目管理 - 福地科技` and pagination showed `1-10 共 254 条`.
- Full baseline checks passed:
  - `composer dump-autoload`;
  - `php think`;
  - `php think route:list`;
  - PHP lint for `app`, `config`, and `route`;
  - `git diff --check` with CRLF conversion warnings only.
- Unauthenticated `/biz/saleproject/page` returned `code = 401`.
- Browser screenshot captured the sale-project table with real rows and pagination.

### Current Issues

- Realtime message connection console noise still appears from the layout message panel.
- Vite `docx-templates` browser compatibility warnings still appear.
- Broader non-admin data-scope token alignment should be reviewed in a later auth/user-agent slice; this commit only fixes the admin smoke-account visibility gap.
- Sale-project write routes, workflow side effects, inventory/finance/account-balance behavior, and online realtime production data sync remain deferred.

### Next Plan

- Commit and push this sale-project page smoke fix.
- Continue with the next visible read-only page or a focused auth/user-agent data-scope review after this commit.

## 2026-06-03 - test-agent/frontend-agent - Sale Project Detail Tab Browser Smoke

### Completed Content

- Continued browser smoke from `http://127.0.0.1:83/biz/saleproject`.
- Opened the detail modal for visible project `赣州开放大学心理中心`.
- Confirmed the information tab rendered project and customer details.
- Confirmed the `项目跟进记录` tab rendered existing read data, including one follow-up record and its pagination.
- Confirmed the `项目案例` tab rendered its current empty/read state without a new backend runtime failure.
- Confirmed the `审核中的流程` tab rendered its current empty/read state without a new backend runtime failure.
- Avoided all visible write controls, including add, edit, discard, upload, and form submit actions.
- Kept Java source, database schema, frontend files, routes, services, controllers, models, Composer files, `.env`, workflow writes, finance behavior, inventory behavior, file upload, and account-balance behavior unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- Browser smoke `/biz/saleproject`: passed.
- Sale-project detail information tab: passed.
- Sale-project detail follow-up tab: passed.
- Sale-project detail case tab: passed with empty/read state.
- Sale-project detail pending-process tab: passed with empty/read state.
- Browser console still shows only known non-blocking realtime message disconnects and upstream `docx-templates` warnings during this smoke.

### Current Issues

- Follow-up add/edit/delete remains deferred.
- Case upload/add behavior remains deferred.
- Pending workflow action behavior remains deferred.
- Realtime message connection console noise remains a later test-agent task.
- Broader non-admin data-scope token alignment remains a later auth/user-agent task.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this sale-project detail smoke documentation slice.
- Continue with the next visible read-only page or start a focused auth/user-agent data-scope review.

## 2026-06-03 - api-agent/frontend-agent - Sale Project Cost Route Precedence Fix

### Completed Content

- Browser-smoked `/biz/saleproject/dealProjectList` and opened a completed historical project detail modal.
- Confirmed the completed-project cost tab initially rendered a 500 result.
- Reproduced the route issue with authenticated HTTP smoke:
  - `POST /biz/saleproject/cost/details` returned the numeric aggregate response because `cost` was registered before `cost/details`.
- Reordered the sale-project route group so `cost/details` is registered before `cost`.
- Documented the public-file route change request and cost API route precedence note.
- Kept Java source, database schema, frontend files, controllers, services, models, Composer files, `.env`, sale-project writes, delivery/invoice/return writes, workflow writes, inventory/finance behavior, file upload, and account-balance behavior unchanged.

### Modified Files

- `route/app.php`
- `docs/api/biz-saleproject-cost-readonly.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### Test Results

- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists `biz/saleproject/cost/details` before `biz/saleproject/cost`.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- Authenticated HTTP smoke passed:
  - `/biz/saleproject/cost/details` returned `code = 200` with `items`, `productItems`, and `returnOrders`.
  - `/biz/saleproject/cost` returned `code = 200` with numeric aggregate `0` for the sampled historical project.
- Browser smoke passed:
  - `/biz/saleproject/dealProjectList` completed-project cost tab no longer renders the 500 result;
  - the tab renders zero-value statistics and an empty product table for the sampled historical project.

### Current Issues

- Historical zero-amount completed projects can show `NaN%` for gross profit rate in the copied frontend cost component. This is a frontend display cleanup candidate for the next small frontend-agent slice.
- Realtime message connection console noise remains a later test-agent task.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this route precedence fix if verification passes.

## 2026-06-03 - frontend-agent - Sale Project Cost Zero-Revenue Display Fix

### Completed Content

- Fixed the copied Vue completed-project cost tab so historical zero-revenue projects no longer calculate gross profit rate by dividing by zero.
- Kept the existing Decimal.js formula for non-zero revenue projects.
- Kept backend cost data, routes, Java source, database schema, Composer files, `.env`, sale-project writes, workflow behavior, inventory behavior, finance behavior, file upload, and account-balance behavior unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `snowy-admin-web/src/views/biz/saleproject/saleProjectTab/cost/index.vue`

### Test Results

- `npm run build`: passed.
- Source verification confirmed zero or empty sales revenue returns gross profit rate `0`.
- Browser automation against the already-open local `/biz/saleproject/dealProjectList` tab was blocked by the browser URL policy, so no workaround was used and visual confirmation remains a manual/user smoke item.

### Current Issues

- Realtime message connection console noise remains a later test-agent task.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Run `git diff --check`, commit, and push this frontend display fix.
- Continue with the next safe read-only frontend/API compatibility slice.

## 2026-06-03 - test-agent/frontend-agent - Sale Project Detail Remaining Tab API Smoke

### Completed Content

- Verified additional read-only sale-project detail tab data paths after the completed-project cost tab fixes.
- Selected an imported sale project with payment, invoice, and file rows.
- Direct-smoked the existing read-only services used by the copied Vue payment, return-order, invoice, and file tabs.
- Kept Java source, database schema, backend business source, frontend component source, routes, Composer files, `.env`, sale-project writes, workflow behavior, inventory behavior, finance behavior, file upload writes, and account-balance behavior unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php think route:list`: passed.
- Direct authenticated service smoke used project `2007642126725550081`.
- `PaymentRecordService::page`: passed, `2/2` rows.
- `ReturnOrderService::page`: passed, `0/0` rows for the sampled project.
- `SaleProjectBillingService::invoiceList`: passed, `1` row.
- `FileRelationService::list`: passed, `2` rows.

### Current Issues

- Browser automation for the local sale-project page remains blocked by URL policy in this session; manual browser verification is still useful.
- Realtime message connection console noise remains a later test-agent/frontend-agent task.
- Sale-project write actions, file upload writes, workflow transitions, inventory mutations, finance mutations, and account-balance behavior remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this documentation-only smoke record.
- Continue with the next safe read-only visible page or the frontend SSE noise task.

## 2026-06-03 - frontend-agent - Message SSE Noise Fallback

### Completed Content

- Updated the copied layout message panel SSE client for the current ThinkPHP short-lived compatibility stream.
- Added SSE source and reconnect timer cleanup on component unmount.
- Changed reconnect behavior from unbounded 5-second retries to a bounded compatibility fallback:
  - retries at 30-second intervals;
  - stops after 3 short-lived disconnect retries;
  - resets the retry count only after a stable connection lasts longer than 60 seconds.
- Reconnect requests now read the latest stored `CLIENTID`.
- Kept backend SSE service, route files, Java source, database schema, message writes, workflow writes, Redis/queue behavior, Composer files, `.env`, and production data sync behavior unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `snowy-admin-web/src/layout/components/panel-message/index.vue`

### Test Results

- `npm run build`: passed with known Vite warnings only.
- `php think route:list`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- Browser smoke:
  - opened authenticated `/sys/org`;
  - page title was `组织管理 - 福地科技`;
  - observed browser logs for 42 seconds after reload;
  - no relevant SSE/message connection error or warning logs were captured during that observation window.

### Current Issues

- Full realtime message push is still deferred until Redis/queue/message workflow behavior is designed.
- Message send/delete/read-state writes remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this frontend-agent compatibility fix.
- Continue with the next safe visible page or read-only API compatibility slice.

## 2026-06-03 - user-agent/frontend-agent - Sys User Grant Echo Read-Only Compatibility

### Completed Content

- Added read-only user grant echo support for the copied `/sys/user` grant dialogs.
- Routed `/sys/user/list/detail`, `/sys/user/ownRole`, `/sys/user/ownResource`, and `/sys/user/ownPermission` behind token middleware.
- Preserved Java-compatible `sys_relation.EXT_JSON` grant payloads for resource and permission echoes.
- Kept user grant writes, user CRUD, enable/disable, reset password, import/export, Java source, database schema, Composer files, `.env`, and deployment configuration unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/sys/UserController.php`
- `app/service/user/UserDirectoryService.php`
- `route/app.php`
- `docs/api/frontend-readonly-selector-compat.md`
- `docs/api/sys-user-grant-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\service\user\UserDirectoryService.php`: passed.
- `php -l app\controller\sys\UserController.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; added `/sys/user/list/detail`, `/sys/user/ownRole`, `/sys/user/ownResource`, and `/sys/user/ownPermission`.
- Full PHP lint over `app`, `config`, and `route`: passed.
- Direct service smoke: passed on user `1543837863788879870`; role echo returned 1 row, resource/permission echoes returned stable empty lists, and `PASSWORD` was not returned.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- Grant save actions remain intentionally deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this read-only compatibility slice if verification passes.
- Continue the next safe visible read-only page/API slice.

## 2026-06-04 - api-agent/workflow-agent - Biz CC Records Read-Only Compatibility

### Completed Content

- Added read-only copy/CC record endpoints for the copied workflow copy-task page.
- Implemented current-user filtering to match Java `BizCcRecordsServiceImpl.page`.
- Returned `promoterName`, `userName`, and `instanceId` display/detail fields.
- Kept copy/CC delete, add/edit, workflow copy delegate writes, approval/reject/start/cancel, Java source, database schema, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/CcRecordsController.php`
- `app/service/biz/CcRecordsService.php`
- `route/app.php`
- `docs/api/biz-cc-records-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\CcRecordsController.php`: passed.
- `php -l app\service\biz\CcRecordsService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; `/biz/ccrecords/page` and `/biz/ccrecords/detail` are registered.
- Full PHP lint over `app`, `config`, and `route`: passed.
- Direct service smoke: passed on copied-data user `2007637932689985538`; total 18 rows, first page returned 2 rows, first detail matched `2007638333690613761`, current-user filter held, and `instanceId`, `promoterName`, and `userName` keys were present.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- The copied copy-task page still exposes delete controls, but delete is intentionally deferred.
- Full workflow write runtime remains deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this read-only compatibility slice.
- Continue the next safe visible read-only page/API slice.

## 2026-06-05 - api-agent/frontend-agent - Customer Follow-Up Write Compatibility

### Completed Content

- Added protected Java-compatible customer follow-up write endpoints: `/biz/customerfollowup/add`, `/edit`, and `/delete`.
- Reused the existing customer follow-up read service boundaries and added transaction-wrapped write methods.
- Added write permission checks against the owning customer row, matching the Java rule of data-scope org IDs first and customer owner fallback.
- Implemented logical delete through `DELETE_FLAG = DELETED` instead of physical deletion.
- Preserved optional `extJson` submitted by the copied frontend form without implementing file upload/storage cleanup.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Started local ThinkPHP service on `http://127.0.0.1:82/` and Vue frontend on `http://127.0.0.1:83/` for follow-up browser testing.
- Kept Java source, database schema, customer writes, attachment upload/storage, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/CustomerFollowUpController.php`
- `app/service/biz/CustomerFollowUpService.php`
- `route/app.php`
- `docs/api/biz-customer-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\CustomerFollowUpController.php`: passed.
- `php -l app\service\biz\CustomerFollowUpService.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list`: passed; route count is 284 and `/biz/customerfollowup/add`, `/edit`, and `/delete` are registered.
- Direct service write smoke: passed; created follow-up `1780626570481441402`, edited content, then logically deleted it with `DELETE_FLAG = DELETED`.
- MySQL `127.0.0.1:3306`: listening.
- Redis `127.0.0.1:6379`: listening.
- `composer dump-autoload`: passed.
- `php think`: passed.
- Full PHP lint over `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend reachability: `http://127.0.0.1:82/` returned HTTP 200.
- Frontend reachability: `http://127.0.0.1:83/` returned HTTP 200.

### Current Issues

- Customer add/edit/delete and head-owner reassignment remain deferred.
- Follow-up attachment upload/storage cleanup and notifications remain deferred.
- The service smoke leaves one logically deleted smoke row in `customer_follow_up`; no visible active data remains.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this customer follow-up write compatibility slice.
- Continue with the next low-risk write candidate after confirming the visible customer follow-up form in the browser.

## 2026-06-05 - api-agent/frontend-agent - Sale Project Follow-Up Write Compatibility

### Completed Content

- Added protected Java-compatible sale-project follow-up write endpoints: `/biz/saleprojectfollowup/add`, `/edit`, and `/delete`.
- Added transaction-wrapped write methods to the existing sale-project follow-up service.
- Preserved Java add behavior by storing submitted `fileList` under `EXT_JSON` as `{"fileList":[...]}`.
- Added write permission checks against the owning sale project row, using admin account/roles, data-scope org ids, or project owner fallback.
- Tightened edit safety by validating both the existing follow-up row's project and the submitted project when they differ.
- Implemented logical delete through `DELETE_FLAG = DELETED` instead of physical deletion.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, sale-project writes, upload/storage cleanup, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/SaleProjectFollowUpController.php`
- `app/service/biz/SaleProjectFollowUpService.php`
- `route/app.php`
- `docs/api/biz-saleproject-followup-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\SaleProjectFollowUpController.php`: passed.
- `php -l app\service\biz\SaleProjectFollowUpService.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list`: passed; route count is 287 and `/biz/saleprojectfollowup/add`, `/edit`, and `/delete` are registered.
- Direct service write smoke: passed; created follow-up `1780627713838248763`, verified `EXT_JSON.fileList[0].name = codex-smoke.txt`, edited content/category, then logically deleted it with `DELETE_FLAG = DELETED`.
- `composer dump-autoload`: passed.
- `php think`: passed.
- Full PHP lint over `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend reachability: `http://127.0.0.1:82/` returned HTTP 200.
- Frontend reachability: `http://127.0.0.1:83/` returned HTTP 200.

### Current Issues

- File upload/storage implementation and physical file cleanup remain deferred.
- Sale-project add/edit/delete, amount/status edits, workflow starts, finance, inventory, and notification side effects remain deferred.
- The service smoke leaves one logically deleted smoke row in `sale_project_follow_up`; no visible active data remains.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this sale-project follow-up write compatibility slice.
- Continue with another low-risk write slice or browser-smoke the sale-project detail follow-up tab before moving into heavier sale-project state changes.

## 2026-06-05 - api-agent/frontend-agent - Sale Project Product Info Write Compatibility

### Completed Content

- Added protected Java-compatible software package/version info write endpoints: `/biz/saleprojectproductinfo/add`, `/edit`, and `/delete`.
- Added transaction-wrapped add/edit/logical-delete methods to `SaleProjectProductInfoService`.
- Kept Java add validation shape by requiring `productId`, `targetId`, and `contentText`.
- Kept Java edit flexibility by requiring only `id` and updating submitted mutable fields.
- Implemented logical delete through `DELETE_FLAG = DELETED` instead of physical deletion.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, product master data, sale-project product-item data, inventory, delivery, workflow, finance, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/SaleProjectProductInfoController.php`
- `app/service/biz/SaleProjectProductInfoService.php`
- `route/app.php`
- `docs/api/biz-saleproject-product-info-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\SaleProjectProductInfoController.php`: passed.
- `php -l app\service\biz\SaleProjectProductInfoService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service write smoke: passed; created info `1780630026237839440`, edited `contentText` and `alias`, then logically deleted it with `DELETE_FLAG = DELETED`.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; route count is 290 and `/biz/saleprojectproductinfo/add`, `/edit`, and `/delete` are registered.
- Initial broad PHP lint emitted a local PHP startup/pagefile warning near the end; strict rerun over 232 PHP files passed with `STRICT_LINT_OK`.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend reachability: `http://127.0.0.1:82/` returned HTTP 200.
- Frontend reachability: `http://127.0.0.1:83/` returned HTTP 200.

### Current Issues

- Product master-data writes, sale-project product-item changes, import/export, report generation, inventory, delivery, workflow, and finance side effects remain deferred.
- The service smoke leaves one logically deleted smoke row in `biz_sale_project_product_info`; no visible active data remains.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this sale-project product-info write compatibility slice.
- Continue with another isolated low-risk write route, or browser-smoke `/biz/saleprojectproductinfo` add/edit/delete before entering heavier sale-project state writes.

## 2026-06-05 - api-agent/frontend-agent - Gen Basic Metadata Read-Only Compatibility

### Completed Content

- Added protected read-only generator database metadata endpoints for the copied generator form.
- Implemented `/gen/basic/tables` using MySQL `information_schema.TABLES`, returning Java-compatible `tableName` and `tableRemark`.
- Implemented `/gen/basic/tableColumns` using MySQL `information_schema.COLUMNS`, returning Java-compatible upper-case `columnName`, upper-case `typeName`, and `columnRemark`.
- Preserved the Java behavior that excludes `ACT_` workflow engine tables from generator table options.
- Updated API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, generator writes, code generation preview/execution, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/gen/BasicController.php`
- `app/service/gen/BasicService.php`
- `route/app.php`
- `docs/api/gen-basic-metadata-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\gen\BasicController.php`: passed.
- `php -l app\service\gen\BasicService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed; `tables(searchKey=sys_user)` returned `sys_user` metadata and `tableColumns(sys_user)` returned 71 columns.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; route count is 280 and `/gen/basic/tables` plus `/gen/basic/tableColumns` are registered.
- Full PHP lint over `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- Full secret scan found only pre-existing default-password compatibility references; this slice diff added no database, Redis, or super-admin secrets.

### Current Issues

- At that point, `/gen/basic/previewGen`, `/execGenZip`, and `/execGenPro` remained deferred; `/gen/basic/previewGen` was later covered as a safe metadata-only preview route, while `execGenZip` and `execGenPro` remain deferred.
- `/gen/basic/add`, `/edit`, and `/delete` remain deferred until the generator module is explicitly opened for write work.
- Generator metadata reads depend on the configured MySQL database being available.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this read-only compatibility slice.
- Continue the next safe visible read-only frontend/API gap, likely another selector/detail consumer before write endpoints.

## 2026-06-05 - auth-agent/frontend-agent - Auth Third User Page Read-Only Compatibility

### Completed Content

- Added protected read-only third-party user binding pagination endpoint.
- Implemented `/auth/third/page` against `auth_third_user` with Java-compatible filters: `category`, `searchKey`, pagination, and safe sort fields.
- Returned Java-compatible camelCase binding fields including `thirdId`, `userId`, `avatar`, `name`, `nickname`, `gender`, `category`, `extJson`, and audit fields.
- Re-scanned copied frontend API wrappers: 224 explicit safe page/list/detail/query/selector wrappers now have 0 missing backend routes.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, OAuth render/callback, user binding writes, user creation, token issuance, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/auth/ThirdController.php`
- `app/service/auth/ThirdService.php`
- `route/app.php`
- `docs/api/auth-third-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\auth\ThirdController.php`: passed.
- `php -l app\service\auth\ThirdService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed; imported local database has 0 `auth_third_user` rows and the endpoint service returned a stable empty page.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; route count is 281 and `/auth/third/page` is registered.
- Full PHP lint over `app`, `config`, and `route`: passed.
- Explicit safe frontend wrapper scan: passed; 224 wrappers scanned, 0 missing routes.
- `git diff --check`: passed with CRLF conversion warnings only.
- This slice diff added no database, Redis, or super-admin secrets.

### Current Issues

- `/auth/third/render` and `/auth/third/callback` remain deferred until OAuth provider configuration and security review are planned.
- Third-party login binding, user creation, and token issuing are not implemented in this slice.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this read-only compatibility slice.
- Move from read-only wrapper closure into a dedicated write-readiness plan before adding the first low-risk write endpoint.

## 2026-06-05 - main-control-agent - Runtime Database And Redis Target Confirmation

### Completed Content

- Accepted the user-confirmed runtime rule that this project must continue using the designated local MySQL database and Redis runtime.
- Verified local `.env` is ignored by Git and contains local-only runtime secrets.
- Verified the MySQL/Redis helper startup script exists at `F:\project\socket\AI\testPhp\files\startServer1.bat`.
- Verified MySQL is reachable on `127.0.0.1:3306`.
- Verified `phpoa20026` exists, creating it with `CREATE DATABASE IF NOT EXISTS` if it was missing.
- Verified Redis is reachable on `127.0.0.1:6379` and authenticated `PING` returns `PONG`.
- Updated runtime verification documentation without writing MySQL or Redis passwords to repository files.

### Modified Files

- `STATUS.md`
- `docs/tasks/runtime-verification-plan.md`

### Test Results

- `git status --short --branch`: clean before documentation update.
- MySQL port probe: passed.
- Startup script path probe: passed.
- MySQL database probe/create-if-missing: passed; `phpoa20026` returned from `INFORMATION_SCHEMA.SCHEMATA`.
- Redis port probe: passed.
- Redis authenticated `PING`: passed with `PONG`.

### Current Issues

- Do not commit local `.env` because it contains database and Redis passwords.
- If any later phase needs to change database name, account, password, Redis host, Redis port, Redis password, or Redis expiration, stop and ask the user to confirm.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Run documentation-scope baseline checks.
- Commit and push this runtime target confirmation.
- Continue the next planned safe read-only compatibility slice after the runtime rule is recorded.

## 2026-06-05 - api-agent/frontend-agent - Team Project Comment Reply Read-Only Compatibility

### Completed Content

- Added protected read-only project comment detail endpoint for copied team-project comment consumers.
- Added protected read-only project comment reply page and detail endpoints for copied comment-reply consumers.
- Reused `TeamProjectTaskReadService` and existing project comment/reply row normalization.
- Kept standalone reply reads within the current user team-project membership boundary by joining reply target comments, owning projects, and project members.
- Preserved nested `bizTeamProjectCommentReplies` on project comment detail.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, comment/reply add/edit/delete, notifications, data-change events, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/TeamProjectCommentController.php`
- `app/controller/biz/TeamProjectCommentReplyController.php`
- `app/service/biz/TeamProjectTaskReadService.php`
- `route/app.php`
- `docs/api/team-project-comment-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\TeamProjectCommentController.php`: passed.
- `php -l app\controller\biz\TeamProjectCommentReplyController.php`: passed.
- `php -l app\service\biz\TeamProjectTaskReadService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed on copied-data project comment `2038855658414485505`; page returned total 2 rows, detail matched the sample id, and nested `bizTeamProjectCommentReplies` key was present.
- Reply table data check: `biz_team_project_comment_reply` currently has 0 rows in the imported local database.
- Direct reply page smoke: passed with an empty page result containing `records`, `total=0`, and `count=0`.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; `/biz/bizteamprojectcomment/detail`, `/biz/bizteamprojectcommentreply/page`, and `/biz/bizteamprojectcommentreply/detail` are registered.
- Route count check: passed with 274 registered routes.
- Full PHP lint over `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- The imported local database has no project comment reply rows, so reply detail could not be smoke-tested against a real row; route, syntax, service page, and visibility query paths were verified.
- `/biz/bizteamprojectcomment/add`, `/delete`, `/biz/bizteamprojectcommentreply/add`, `/edit`, and `/delete` remain intentionally deferred.
- Notifications, data-change events, task/project writes, and file upload behavior remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this read-only compatibility slice.
- Continue the next safe frontend-visible read-only route gap, likely system field reads or generator metadata reads, before opening write APIs.

## 2026-06-05 - user-agent/api-agent/frontend-agent - Sys Field Read-Only Compatibility

### Completed Content

- Added protected read-only system field resource endpoints for the copied field drawer.
- Added `FieldController` with page, tree, detail, and menu tree selector reads.
- Extended `ResourceService` with `FIELD` category page/tree readers.
- Routed frontend-compatible `/sys/field/MenuTreeSelector` to existing menu tree selector data.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, field add/edit/delete, menu/button/module writes, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/sys/FieldController.php`
- `app/service/sys/ResourceService.php`
- `route/app.php`
- `docs/api/sys-field-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\sys\FieldController.php`: passed.
- `php -l app\service\sys\ResourceService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed; `fieldPageTotal=0`, `fieldPageCount=0`, `fieldTreeCount=0`, `menuSelectorCount=20`, and page result contains `records`.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; `/sys/field/page`, `/tree`, `/detail`, and `/MenuTreeSelector` are registered.
- Route count check: passed with 278 registered routes.
- Full PHP lint over `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- The imported local database currently has no `FIELD` rows in `sys_resource`, so field detail could not be smoke-tested against a real row; empty page/tree and menu selector behavior were verified.
- Java backend field controller was not found in the current source scan; this compatibility route is inferred from the copied Vue field wrapper and `sys_resource.CATEGORY = FIELD` convention.
- `/sys/field/add`, `/edit`, and `/delete` remain intentionally deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this read-only compatibility slice.
- Continue the next safe frontend-visible read-only route gap, likely generator metadata reads or system field/detail follow-up if FIELD data appears later.

## 2026-06-05 - workflow-agent/api-agent/frontend-agent - Biz User Vacation Page Read-Only Compatibility

### Completed Content

- Added protected read-only annual-leave/vacation balance page endpoint.
- Preserved existing `detail` behavior and kept the new page route behind token middleware.
- Page reads non-deleted `biz_user_vacation` rows, joins `sys_user` for `userName`, and supports pagination plus safe whitelisted sorting.
- Returned rows with `id`, `userId`, `userName`, `amount`, `usedAmount`, `category`, audit fields, tenant id, and version for copied frontend compatibility.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, vacation generation/reduction, leave approval deductions, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/BizUserVacationController.php`
- `app/service/biz/BizUserVacationService.php`
- `route/app.php`
- `docs/api/biz-user-vacation-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\BizUserVacationController.php`: passed.
- `php -l app\service\biz\BizUserVacationService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed; returned 3 copied-data rows, exposed `userName` and `amount`, and existing detail still returned the sample user/category.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; `/biz/bizuservacation/page` and `/biz/bizuservacation/detail` are registered.
- Full PHP lint over `app`, `config`, and `route`: passed.
- Route count check: passed with 271 registered routes.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- Java controller wires only `detail`, while Java service and copied frontend wrapper expose `page`; this ThinkPHP endpoint is intentionally read-only compatibility.
- `/biz/bizuservacation/add`, `/edit`, `/delete`, generation/reduction helpers, and approval-time vacation deductions remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this read-only compatibility slice.
- Continue the next safe visible read-only page/API slice.

## 2026-06-04 - api-agent - Biz Draft Detail Read-Only Compatibility

### Completed Content

- Added read-only sale-project draft detail endpoint for the copied sale-project draft flow.
- Matched Java `BizDraftServiceImpl.detail` behavior by querying `biz_draft.TARGET_ID`.
- Preserved raw `EXT_JSON` as `extJson` so frontend form/file draft parsing remains compatible.
- Kept draft save, sale-project add/edit, workflow start, file upload, Java source, database schema, Composer files, `.env`, and frontend source unchanged in this 2026-06-04 read-only slice. Draft save is now covered by the 2026-06-12 sale-project draft save slice.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/BizDraftController.php`
- `app/service/biz/BizDraftService.php`
- `route/app.php`
- `docs/api/biz-draft-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\BizDraftController.php`: passed.
- `php -l app\service\biz\BizDraftService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; `/biz/bizdraft/detail` is registered.
- Full PHP lint over `app`, `config`, and `route`: passed.
- Direct service smoke: passed on copied-data target id `2007642126725550081`; detail matched draft `2007721895165038593`, `targetId` matched, and `extJson` plus `category` were present.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- `/biz/bizdraft/saleproject/add` was intentionally deferred in this 2026-06-04 read-only slice and is now covered by the 2026-06-12 sale-project draft save slice.
- Sale-project add/edit, workflow start, and file upload side effects remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this read-only compatibility slice.
- Continue the next safe visible read-only page/API slice.

## 2026-06-04 - workflow-agent/api-agent - Biz User Vacation Detail Read-Only Compatibility

### Completed Content

- Added read-only annual-leave balance detail endpoint for copied leave-process pages.
- Matched Java `BizUserVacationServiceImpl.detail` defaults: current login user when `userId` is omitted, `annualLeave` when `category` is omitted, and current-year records by `CREATE_TIME`.
- Returned a zero-balance annual-leave object when no row exists, preserving copied frontend calculations.
- Kept vacation generation/reduction, leave approval deductions, workflow writes, Java source, database schema, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/BizUserVacationController.php`
- `app/service/biz/BizUserVacationService.php`
- `route/app.php`
- `docs/api/biz-user-vacation-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\BizUserVacationController.php`: passed.
- `php -l app\service\biz\BizUserVacationService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; `/biz/bizuservacation/detail` is registered.
- Full PHP lint over `app`, `config`, and `route`: passed.
- Direct service smoke: passed on copied-data user `1543837863788879870`; detail matched current-year annual-leave row `2006394917698801666`, `amount=5`, `usedAmount=0`, and missing-user fallback returned zero annual-leave balance.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- `/biz/bizuservacation/page`, `/add`, `/edit`, and `/delete` remain intentionally deferred.
- Vacation generation/reduction and leave approval balance deductions remain deferred until workflow write runtime is opened.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this read-only compatibility slice.
- Continue the next safe visible read-only page/API slice.

## 2026-06-04 - api-agent/frontend-agent - Biz History Excel Read-Only Compatibility

### Completed Content

- Added read-only historical EXCEL endpoints for the copied `/biz/bizhistoryexcel` page.
- Matched Java page/detail scope for `BizHistoryExcelController` with protected `page` and `detail` routes.
- Preserved raw `EXT_JSON` as `extJson` for spreadsheet display and kept audit/tenant fields.
- Kept Java source, database schema, Excel import/export, spreadsheet parsing, add/edit/delete routes, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/BizHistoryExcelController.php`
- `app/service/biz/BizHistoryExcelService.php`
- `route/app.php`
- `docs/api/biz-history-excel-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\BizHistoryExcelController.php`: passed.
- `php -l app\service\biz\BizHistoryExcelService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; `/biz/bizhistoryexcel/page` and `/biz/bizhistoryexcel/detail` are registered.
- Full PHP lint over `app`, `config`, and `route`: passed.
- Direct service smoke: passed; local `biz_history_excel` has 2 raw rows and 0 non-deleted visible rows, so page returns `total=0` under Java-compatible logical-delete filtering.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- The copied page still exposes add/edit/delete controls, but those write routes remain intentionally deferred.
- Local imported history Excel rows are currently logical deleted, so the page can validly show an empty list.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this read-only compatibility slice.
- Continue the next safe visible read-only page/API slice.

## 2026-06-04 - api-agent/frontend-agent - Sale Project Invoice Item Page Read-Only Compatibility

### Completed Content

- Added Java-compatible read-only delivery invoice item page endpoint for copied sale-project invoice/detail consumers.
- Matched Java `BizSaleProjectInvoiceItemServiceImpl.page` filters for `invoiceId` and `warehousesId`.
- Preserved Java's default `PROJECT_PRODUCT_ITEM_ID` ascending sort and added a safe sorting whitelist.
- Returned existing product and warehouse display aliases used by sale-project invoice detail reads.
- Kept Java source, database schema, invoice item writes, invoice/delivery/stock/project-state/finance side effects, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/SaleProjectInvoiceItemController.php`
- `app/service/biz/SaleProjectBillingService.php`
- `route/app.php`
- `docs/api/sale-project-invoice-item-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\SaleProjectInvoiceItemController.php`: passed.
- `php -l app\service\biz\SaleProjectBillingService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; `/biz/saleprojectinvoiceItem/page` is registered.
- Full PHP lint over `app`, `config`, and `route`: passed.
- Direct service smoke: passed on copied-data invoice `2008383542460407810`; total 1 row, first row `2008383542565265410`, and `productName` plus `warehousesName` keys were present.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- `biz_sale_project_product_item` read routes in Java are commented out, so they were not added even though wrappers mention them.
- Invoice item add/edit/delete and delivery/stock/finance side effects remain deferred.
- MySQL startup through `F:\project\socket\AI\testPhp\files\startServer1.bat` can take around 30 seconds before port 3306 listens.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this read-only compatibility slice.
- Continue the next safe visible read-only page/API slice.

## 2026-06-04 - api-agent/frontend-agent - Sales Project Field Change Log Read-Only Compatibility

### Completed Content

- Added Java-compatible read-only sale-project field change log page and detail endpoints.
- Matched Java `SalesProjectFieldChangeLogServiceImpl.page` default sorting by `ID` ascending and used a safe sorting whitelist for requested sort fields.
- Returned change fields, audit fields, tenant id, project display name, and creator display name for copied sale-project history/detail consumers.
- Kept Java source, database schema, change-log add/edit/delete, sale-project amount/change writes, workflow, finance, audit side effects, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/SalesProjectFieldChangeLogController.php`
- `app/service/biz/SalesProjectFieldChangeLogService.php`
- `route/app.php`
- `docs/api/sales-project-field-change-log-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\SalesProjectFieldChangeLogController.php`: passed.
- `php -l app\service\biz\SalesProjectFieldChangeLogService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; `/biz/salesprojectfieldchangelog/page` and `/biz/salesprojectfieldchangelog/detail` are registered.
- Full PHP lint over `app`, `config`, and `route`: passed.
- Direct service smoke: passed on copied-data change log `2016674049317908481`; page returned total 5 rows, detail matched the sample id and exposed `objectId`, `projectName`, and `createUserName`.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- Imported SQL uses mixed collations between `sales_project_field_change_log.OBJECT_ID` and `biz_sale_project.ID`; the read join uses explicit collation without changing schema.
- `/biz/salesprojectfieldchangelog/add`, `/edit`, and `/delete` remain intentionally deferred.
- Sale-project amount/change writes and audit side effects remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this read-only compatibility slice.
- Continue the next safe visible read-only page/API slice.

## 2026-06-04 - api-agent/frontend-agent - Team Project Task User Read-Only Compatibility

### Completed Content

- Added Java-compatible read-only team-project task user page and detail endpoints.
- Reused `TeamProjectTaskReadService` and existing `TASK_USER_FIELDS`/row normalization for task assignment rows.
- Kept existing ThinkPHP team-project visibility boundary by returning only task-user rows from projects where the current login user is a project member.
- Returned Java-compatible translated user aliases `headName` and `avatar`.
- Kept Java source, database schema, task-user add/edit/delete, task assignment writes, task status/progress writes, notifications, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/TeamProjectTaskUserController.php`
- `app/service/biz/TeamProjectTaskReadService.php`
- `route/app.php`
- `docs/api/team-project-task-user-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\TeamProjectTaskUserController.php`: passed.
- `php -l app\service\biz\TeamProjectTaskReadService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; `/biz/bizteamprojecttaskuser/page` and `/biz/bizteamprojecttaskuser/detail` are registered.
- Full PHP lint over `app`, `config`, and `route`: passed.
- Direct service smoke: passed on copied-data project `1903996479133360129` as member `2007699574773649410`; page returned total 7 rows, detail matched task-user `2033724343780306945`, and `headName` plus `avatar` keys were present.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- Some older imported `biz_team_project_task_user` rows point to deleted tasks or projects, so smoke tests must pick rows where task, project, and project membership are all visible.
- `/biz/bizteamprojecttaskuser/add`, `/edit`, and `/delete` remain intentionally deferred.
- Task assignment writes, task status/progress writes, and notifications remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this read-only compatibility slice.
- Continue the next safe visible read-only page/API slice.

## 2026-06-04 - api-agent/frontend-agent - Dev Monitor Network Info Read-Only Compatibility

### Completed Content

- Added Java-compatible read-only dev monitor network info endpoint.
- Matched Java `DevMonitorController.networkInfo` response shape with `devMonitorNetworkInfo.upLinkRate` and `devMonitorNetworkInfo.downLinkRate`.
- Sampled local OS network counters twice and formatted per-second upload/download rates.
- Added safe fallback to `0 B/s` when OS counters are unavailable.
- Kept Java source, database schema, monitor writes/server control, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/dev/MonitorController.php`
- `app/service/dev/MonitorService.php`
- `route/app.php`
- `docs/api/dev-monitor-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\dev\MonitorController.php`: passed.
- `php -l app\service\dev\MonitorService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed; returned `devMonitorNetworkInfo` with `upLinkRate` and `downLinkRate`.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; `/dev/monitor/networkInfo` is registered.
- Full PHP lint over `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- Network rate depends on local OS counter availability; unsupported counters intentionally degrade to `0 B/s`.
- Monitor writes, server process control, and metric persistence remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this read-only compatibility slice.
- Continue the next safe visible read-only page/API slice.

## 2026-06-05 - api-agent/frontend-agent - Sale Project Rate Detail Read-Only Compatibility

### Completed Content

- Added protected read-only sale-project customer rating detail endpoint.
- Reused existing `SaleProjectBillingService::rateQuery` so detail keeps the same tenant, delete-flag, and project-scope boundaries as rating page/list reads.
- Returned the same normalized rating shape used by page/list, including `projectName`, `customerName`, and raw `extJson`.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, rating add/edit/delete, rating image upload, sale-project writes, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/SaleProjectRateController.php`
- `app/service/biz/SaleProjectBillingService.php`
- `route/app.php`
- `docs/api/biz-saleproject-billing-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\SaleProjectRateController.php`: passed.
- `php -l app\service\biz\SaleProjectBillingService.php`: passed.
- `php -l route\app.php`: passed.
- MySQL was not listening at first; started it through `F:\project\socket\AI\testPhp\files\startServer1.bat`, then port 3306 listened.
- Direct service smoke: passed on copied-data rating `2009867439677366274`; detail matched the sample id and exposed `projectName`, `customerName`, and `extJson`.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; `/biz/projectrate/detail` is registered.
- Full PHP lint over `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- Java controller does not wire a concrete `/biz/projectrate/detail` mapping, but the Java service has `detail/queryEntity` and the copied frontend wrapper exposes `saleProjectRateDetail`; this ThinkPHP route is kept read-only for frontend compatibility.
- `/biz/projectrate/add`, `/edit`, and `/delete` remain intentionally deferred.
- Rating image upload, sale-project writes, file storage, workflow, finance, and project-state side effects remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this read-only compatibility slice.
- Continue the next safe visible read-only page/API slice.

## 2026-06-05 - api-agent/frontend-agent - Sale Project Field Change Log Write Compatibility

### Completed Content

- Added Java-compatible protected sale-project field change log `add`, `edit`, and `delete` endpoints.
- Matched Java validation requirements for `objectId`, `fieldName`, `fieldLabel`, `beforeValue`, `afterValue`, and `changeReason`.
- Kept existing `page` and `detail` read behavior, including project and creator display aliases.
- Added transactional write methods with audit fields and tenant preservation from the owning sale project.
- Used `DELETE_FLAG = DELETED` for delete safety instead of physical removal.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, sale-project main writes, workflow, finance, inventory, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/SalesProjectFieldChangeLogController.php`
- `app/service/biz/SalesProjectFieldChangeLogService.php`
- `route/app.php`
- `docs/api/sales-project-field-change-log-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\SalesProjectFieldChangeLogController.php`: passed.
- `php -l app\service\biz\SalesProjectFieldChangeLogService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; `/biz/salesprojectfieldchangelog/add`, `/edit`, and `/delete` are registered.
- Direct service smoke: passed on copied-data project `2007642126725550081`; add returned test row `1780634305327997228`, edit changed `afterValue`, and delete set `DELETE_FLAG=DELETED`.
- Strict full PHP lint over `app`, `config`, and `route`: passed; 232 PHP files checked.
- `git diff --check`: passed with CRLF conversion warnings only.
- MySQL started through `F:\project\socket\AI\testPhp\files\startServer1.bat`; backend `http://127.0.0.1:82/` returned 200; frontend `http://127.0.0.1:83/` returned 200.

### Current Issues

- Sale-project generated history creation from amount/change edit flows remains deferred.
- Workflow, finance, inventory, notifications, and audit side effects remain deferred.
- Delete intentionally leaves a logically deleted local test row for traceability instead of physically removing data.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this write compatibility slice.
- Continue the next isolated low-risk write endpoint before opening side-effect-heavy sale-project, finance, stock, or workflow flows.

## 2026-06-05 - api-agent/frontend-agent - Biz History Excel Write Compatibility

### Completed Content

- Added Java-compatible protected historical Excel data `add`, `edit`, and `delete` endpoints.
- Matched Java parameter shape: add stores `name` and `extJson`; edit requires `id` and updates submitted `extJson`.
- Kept existing `page` and `detail` read behavior and raw `EXT_JSON` payload preservation.
- Added transactional writes with audit fields and tenant id defaults.
- Used `DELETE_FLAG = DELETED` for delete safety instead of physical removal.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, `biz_history_excel_row`, frontend Excel parser, file storage/import/export, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/BizHistoryExcelController.php`
- `app/service/biz/BizHistoryExcelService.php`
- `route/app.php`
- `docs/api/biz-history-excel-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\BizHistoryExcelController.php`: passed.
- `php -l app\service\biz\BizHistoryExcelService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; `/biz/bizhistoryexcel/add`, `/edit`, and `/delete` are registered.
- Direct service smoke: passed; add returned test row `1780635064432452528`, edit changed `extJson`, and delete set `DELETE_FLAG=DELETED`.
- Strict full PHP lint over `app`, `config`, and `route`: passed; 232 PHP files checked.
- `git diff --check`: passed with CRLF conversion warnings only.
- MySQL was already listening; backend `http://127.0.0.1:82/` returned 200; frontend `http://127.0.0.1:83/` returned 200.

### Current Issues

- Frontend Excel parsing remains unchanged and still submits the whole parsed payload as `extJson`.
- `biz_history_excel_row` row-table parsing/writes remain deferred because Java controller does not use it in this CRUD flow.
- Import/export and physical file storage changes remain deferred.
- Delete intentionally leaves a logically deleted local test row for traceability instead of physically removing data.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this write compatibility slice.
- Continue another isolated low-risk write endpoint before opening finance, inventory, workflow, or sale-project state flows.

## 2026-06-05 - api-agent/frontend-agent - Sale Project Rate Write Compatibility

### Completed Content

- Added Java-compatible protected sale-project rating `add` and `delete` endpoints.
- Matched the Java-exposed controller surface: `/biz/projectrate/add` and `/delete`; `/edit` remains deferred because the Java controller does not expose it in the current reference.
- Preserved existing `page`, `list`, and `detail` read behavior.
- Stored submitted `imgList` under `EXT_JSON` as `{"imgList":[...]}` for the copied frontend parser.
- Added transactional writes with audit fields, project write-scope checks, tenant id defaults, and logical delete.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, frontend source, file upload/storage, sale-project state, workflow, finance, inventory, Composer files, and `.env` unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/SaleProjectRateController.php`
- `app/service/biz/SaleProjectBillingService.php`
- `route/app.php`
- `docs/api/biz-saleproject-billing-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\SaleProjectRateController.php`: passed.
- `php -l app\service\biz\SaleProjectBillingService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed on copied-data project `2007642126725550081`; add returned test row `1780638185496634189`, detail returned `imgList`, and delete set `DELETE_FLAG=DELETED`.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; `/biz/projectrate/add` and `/delete` are registered.
- Strict full PHP lint over `app`, `config`, and `route`: passed; 232 PHP files checked.
- `git diff --check`: passed with CRLF conversion warnings only.
- MySQL TCP check returned OK; backend `http://127.0.0.1:82/` returned 200; frontend `http://127.0.0.1:83/` returned 200.

### Current Issues

- `/biz/projectrate/edit` remains deferred.
- Image upload/storage cleanup, sale-project state, workflow, finance, inventory, and notifications remain deferred.
- Delete intentionally leaves a logically deleted local test row for traceability instead of physically removing data.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this write compatibility slice.
- Continue another isolated low-risk write endpoint before opening finance, inventory, workflow, or sale-project state flows.

## 2026-06-05 - api-agent/frontend-agent/workflow-agent - CC Records Delete Compatibility

### Completed Content

- Added Java-compatible protected workflow copy/CC record `delete` endpoint.
- Preserved existing `page` and `detail` read behavior.
- Matched Java's delete guard by requiring `USER` to equal the current token user id.
- Added optional tenant guard when the token payload includes `tenantId` or `tenant_id`.
- Used `DELETE_FLAG = DELETED` for delete safety instead of physical removal.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, `/biz/ccrecords/add`, `/edit`, workflow copy-user delegate writes, approval/reject/start/cancel flows, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/CcRecordsController.php`
- `app/service/biz/CcRecordsService.php`
- `route/app.php`
- `docs/api/biz-cc-records-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\CcRecordsController.php`: passed.
- `php -l app\service\biz\CcRecordsService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed; inserted local test row `1780638742848257170` for user `1543837863788879870`, detail read succeeded, and delete set `DELETE_FLAG=DELETED`.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; `/biz/ccrecords/delete` is registered.
- Strict full PHP lint over `app`, `config`, and `route`: passed; 232 PHP files checked.
- `git diff --check`: passed with CRLF conversion warnings only.
- MySQL TCP check returned OK; backend `http://127.0.0.1:82/` returned 200; frontend `http://127.0.0.1:83/` returned 200.

### Current Issues

- `/biz/ccrecords/add` and `/edit` remain deferred.
- Workflow copy-user delegate writes and approval/reject/start/cancel side effects remain deferred.
- Delete intentionally leaves a logically deleted local test row for traceability instead of physically removing data.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this write compatibility slice.
- Continue another isolated low-risk write endpoint before opening side-effect-heavy workflow, finance, inventory, or sale-project state flows.

## 2026-06-05 - api-agent/frontend-agent - Team Project Comment Add Compatibility

### Completed Content

- Added Java-compatible protected team-project timeline comment `add` endpoint.
- Added Java-compatible protected team-project comment-reply `add` endpoint.
- Preserved existing `page`, `list`, and `detail` read behavior.
- Required current-user membership of the owning team project before either write.
- Stored submitted `mentionableUsers` under `EXT_JSON` as `{"mentionableUsers":[...]}`.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, frontend source, comment delete, reply edit/delete, notification push, data-change events, task state/progress writes, Composer files, and `.env` unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/TeamProjectCommentController.php`
- `app/controller/biz/TeamProjectCommentReplyController.php`
- `app/service/biz/TeamProjectTaskReadService.php`
- `route/app.php`
- `docs/api/biz-team-project-task-readonly-compat.md`
- `docs/api/team-project-comment-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\TeamProjectCommentController.php`: passed.
- `php -l app\controller\biz\TeamProjectCommentReplyController.php`: passed.
- `php -l app\service\biz\TeamProjectTaskReadService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed on team project `1903996479133360129` for user `1543837863788879873`; comment `1780639737042353386` and reply `1780639737256204805` were inserted, read back, and then marked `DELETE_FLAG=DELETED`.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; route entry rows = 301 and `/biz/bizteamprojectcomment/add`, `/biz/bizteamprojectcommentreply/add` are registered.
- Strict full PHP lint over `app`, `config`, and `route`: passed; 232 PHP files checked.
- MySQL/Redis were started through `F:\project\socket\AI\testPhp\files\startServer1.bat`; `netstat` showed MySQL 3306 and Redis 6379 listening.
- Backend `http://127.0.0.1:82/` returned 200; frontend `http://127.0.0.1:83/` returned 200 after Vite startup finished.

### Current Issues

- `/biz/bizteamprojectcomment/delete` remains deferred.
- `/biz/bizteamprojectcommentreply/edit` and `/delete` remain deferred.
- Notification push, data-change events, team-project mutations, task mutations, and task state/progress writes remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this write compatibility slice.
- Continue another isolated low-risk frontend-visible write endpoint before opening side-effect-heavy workflow, finance, inventory, or sale-project state flows.

## 2026-06-05 - api-agent/frontend-agent - Team Project Comment Maintenance Compatibility

### Completed Content

- Added Java-compatible protected team-project timeline comment `delete` endpoint.
- Added Java-compatible protected team-project comment-reply `edit` and `delete` endpoints.
- Preserved existing comment/reply read and add behavior.
- Converted Java physical deletes to project-standard logical deletes with `DELETE_FLAG = DELETED`.
- Added `delComment` project resource permission validation from imported `biz_relation` records for comment maintenance.
- Allowed reply edit/delete for the reply creator or a project user with imported `delComment` permission.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, frontend source, team-project mutations, task/category/task-user writes, task state/progress writes, notification push, data-change events, Composer files, and `.env` unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/TeamProjectCommentController.php`
- `app/controller/biz/TeamProjectCommentReplyController.php`
- `app/service/biz/TeamProjectTaskReadService.php`
- `route/app.php`
- `docs/api/biz-team-project-task-readonly-compat.md`
- `docs/api/team-project-comment-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\TeamProjectCommentController.php`: passed.
- `php -l app\controller\biz\TeamProjectCommentReplyController.php`: passed.
- `php -l app\service\biz\TeamProjectTaskReadService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed on team project `1903996479133360129` for user `1543837863788879873`; comment `1780644969022144266` and reply `1780644969138213218` were inserted, reply edit was read back, reply delete hid the reply, and comment delete hid the comment.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; route entry rows = 304 and `/biz/bizteamprojectcomment/delete`, `/biz/bizteamprojectcommentreply/edit`, `/biz/bizteamprojectcommentreply/delete` are registered.
- Strict full PHP lint over `app`, `config`, and `route`: passed; 232 PHP files checked.
- `git diff --check`: passed with CRLF conversion warnings only.
- MySQL 3306, Redis 6379, backend 82, and frontend 83 were listening.
- Backend `http://127.0.0.1:82/` returned 200; frontend `http://127.0.0.1:83/` returned 200.

### Current Issues

- Notification push and data-change events remain deferred.
- Team-project add/edit/delete, task/category/task-user writes, task comment writes, and task state/progress writes remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this maintenance compatibility slice.
- Continue another isolated low-risk frontend-visible write endpoint before opening side-effect-heavy workflow, finance, inventory, or sale-project state flows.

## 2026-06-05 - api-agent/frontend-agent - Team Project Task User Edit Compatibility

### Completed Content

- Added Java-compatible protected `POST /biz/bizteamprojecttask/user/edit`.
- Preserved existing task `page`, `list`, and `detail` read behavior.
- Accepted frontend task-detail assignee payloads as id strings, comma-separated ids, or user objects with `id`, `userId`, or `value`.
- Required current-user membership of the owning team project plus imported `addUser` project permission or task-level `MANAGE` role.
- Required submitted assignees to already be non-deleted members of the same team project.
- Inserted new assignees as task-user `MEMBER` rows and logically deleted removed assignees with `DELETE_FLAG = DELETED`.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, frontend source, task add/edit/delete, category writes, task comments, task status/progress/content writes, notification push, data-change events, Composer files, and `.env` unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/TeamProjectTaskController.php`
- `app/service/biz/TeamProjectTaskReadService.php`
- `route/app.php`
- `docs/api/biz-team-project-task-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\TeamProjectTaskController.php`: passed.
- `php -l app\service\biz\TeamProjectTaskReadService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed on team project `1903996479133360129`, task `2033724343755141122`, current user `1543837863788879873`; candidate assignee `1543837863788879873` was added through object-shaped frontend payload and then restored to the original task-user list.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; route entry rows = 305 and `/biz/bizteamprojecttask/user/edit` is registered.
- Strict full PHP lint over `app`, `config`, and `route`: passed; 232 PHP files checked.
- `git diff --check`: passed with CRLF conversion warnings only.
- No-token HTTP check for `/biz/bizteamprojecttask/user/edit`: returned HTTP 200 envelope with `code=401`.
- MySQL 3306, Redis 6379, backend 82, and frontend 83 were listening.
- Backend `http://127.0.0.1:82/` returned 200; frontend `http://127.0.0.1:83/` returned 200.

### Current Issues

- The smoke test intentionally leaves logically deleted task-user test rows for traceability instead of physically deleting imported-style data.
- Task add/edit/delete, category writes, task comment writes, task status/progress/content writes, notification push, and data-change events remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this task assignee compatibility slice.
- Continue another isolated frontend-visible write/read gap, likely task comment maintenance or a low-risk customer/sale-project helper, before opening side-effect-heavy workflow, finance, inventory, or sale-project state flows.

## 2026-06-05 - api-agent/frontend-agent - Team Project Task Comment Add Compatibility

### Completed Content

- Added Java-compatible protected `POST /biz/bizteamprojecttaskcomment/add`.
- Preserved existing task-comment `page`, `list`, and `detail` read behavior.
- Required current-user membership of the owning team project before adding a task comment.
- Derived `TEAM_PROJECT_ID` and tenant id from the existing task/project instead of trusting the request body.
- Stored new task comments with `CATEGORY = COMMENT`, `DELETE_FLAG = NOT_DELETE`, current-user audit fields, and raw `CONTENT_TEXT`.
- Stored submitted `files` under `EXT_JSON` as `{"file":[...]}` for compatibility with the copied task detail drawer.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, frontend source, task comment edit/delete, task add/edit/delete, category writes, task status/progress/content writes, notification push, data-change events, Composer files, and `.env` unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/TeamProjectTaskCommentController.php`
- `app/service/biz/TeamProjectTaskReadService.php`
- `route/app.php`
- `docs/api/biz-team-project-task-readonly-compat.md`
- `docs/api/team-project-comment-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\TeamProjectTaskCommentController.php`: passed.
- `php -l app\service\biz\TeamProjectTaskReadService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed on team project `1903996479133360129`, task `2033724343755141122`, current user `1543837863788879873`; comment `1780647300714334323` was inserted with `CATEGORY = COMMENT`, `EXT_JSON.file[0].name = smoke.txt`, read back, and then logically marked `DELETE_FLAG = DELETED`.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; route entry rows = 306 and `/biz/bizteamprojecttaskcomment/add` is registered.
- Strict full PHP lint over `app`, `config`, and `route`: passed; 232 PHP files checked.
- `git diff --check`: passed with CRLF conversion warnings only.
- No-token HTTP check for `/biz/bizteamprojecttaskcomment/add`: returned HTTP 200 envelope with `code=401`.
- MySQL 3306, Redis 6379, backend 82, and frontend 83 were listening.
- Backend `http://127.0.0.1:82/` returned 200; frontend `http://127.0.0.1:83/` returned 200.

### Current Issues

- The smoke test intentionally leaves a logically deleted task-comment test row for traceability instead of physically deleting imported-style data.
- Task comment edit/delete, task add/edit/delete, category writes, task status/progress/content writes, notification push, and data-change events remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this task comment add compatibility slice.
- Continue another isolated frontend-visible write/read gap before opening side-effect-heavy workflow, finance, inventory, or sale-project state flows.

## 2026-06-05 - api-agent/frontend-agent - Team Project Task Comment Maintenance Compatibility

### Completed Content

- Added Java-compatible protected `POST /biz/bizteamprojecttaskcomment/edit`.
- Added Java-compatible protected `POST /biz/bizteamprojecttaskcomment/delete`.
- Preserved existing task-comment `page`, `list`, `detail`, and `add` behavior.
- Restricted maintenance to user comments with `CATEGORY = COMMENT`.
- Kept generated task logs with `CATEGORY = LOG` read-only.
- Allowed maintenance for the comment creator, a project user with imported `delComment`, or a task-level `MANAGE` user.
- Edit updates only `CONTENT_TEXT`, `EXT_JSON`, `UPDATE_TIME`, and `UPDATE_USER`.
- Delete uses logical deletion through `DELETE_FLAG = DELETED`.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, frontend source, task add/edit/delete, category writes, task status/progress/content writes, notification push, data-change events, Composer files, and `.env` unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/TeamProjectTaskCommentController.php`
- `app/service/biz/TeamProjectTaskReadService.php`
- `route/app.php`
- `docs/api/biz-team-project-task-readonly-compat.md`
- `docs/api/team-project-comment-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\TeamProjectTaskCommentController.php`: passed.
- `php -l app\service\biz\TeamProjectTaskReadService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed on team project `1903996479133360129`, task `2033724343755141122`, current user `1543837863788879873`; comment `1780647795941342103` was inserted, edited with `EXT_JSON.file[0].name = edit.txt`, logically deleted with `DELETE_FLAG = DELETED`, and an existing `CATEGORY = LOG` row was rejected as read-only.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; route entry rows = 308 and `/biz/bizteamprojecttaskcomment/edit` plus `/delete` are registered.
- Strict full PHP lint over `app`, `config`, and `route`: passed; 232 PHP files checked.
- `git diff --check`: passed with CRLF conversion warnings only.
- No-token HTTP check for `/biz/bizteamprojecttaskcomment/edit`: returned HTTP 200 envelope with `code=401`.
- MySQL 3306, Redis 6379, backend 82, and frontend 83 were listening.
- Backend `http://127.0.0.1:82/` returned 200; frontend `http://127.0.0.1:83/` returned 200.

### Current Issues

- The smoke test intentionally leaves a logically deleted task-comment test row for traceability instead of physically deleting imported-style data.
- Generated task-log edit/delete remains intentionally blocked.
- Task add/edit/delete, category writes, task status/progress/content writes, notification push, and data-change events remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this task comment maintenance compatibility slice.
- Continue another isolated frontend-visible write/read gap before opening side-effect-heavy workflow, finance, inventory, or sale-project state flows.

## 2026-06-05 - api-agent/frontend-agent - Team Project Task Category Maintenance Compatibility

### Completed Content

- Added Java-compatible protected `POST /biz/bizteamprojecttaskcategory/add`.
- Added Java-compatible protected `POST /biz/bizteamprojecttaskcategory/edit`.
- Added Java-compatible protected `POST /biz/bizteamprojecttaskcategory/sort/edit`.
- Added Java-compatible protected `POST /biz/bizteamprojecttaskcategory/delete`.
- Preserved existing task-category `page`, `list`, and `detail` read behavior.
- Required project maintainer permission for category maintenance: team-project `LEADER`, team-project `MANAGE`, or imported `addUser` project resource permission.
- Defaulted new category `SORT_CODE` to `99`.
- Allowed category edit to update only `TITLE`, optional `EXT_JSON`, optional `SORT_CODE`, and audit fields.
- Reordered submitted categories by Java-style ordered `[{id: ...}]` payloads.
- Rejected deletion of categories that still contain active tasks.
- Used logical deletion through `DELETE_FLAG = DELETED` instead of physical removal.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, frontend source, task add/edit/delete, task drag-to-category, task status/progress/content writes, notification push, data-change events, Composer files, and `.env` unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/TeamProjectTaskCategoryController.php`
- `app/service/biz/TeamProjectTaskReadService.php`
- `route/app.php`
- `docs/api/biz-team-project-task-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\TeamProjectTaskCategoryController.php`: passed.
- `php -l app\service\biz\TeamProjectTaskReadService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed on team project `1903996479133360129`, current user `1543837863788879873`; category `1780648771828319110` was added with default `SORT_CODE = 99`, edited, sorted to `SORT_CODE = 0`, and logically deleted with `DELETE_FLAG = DELETED`.
- Direct service negative smoke: passed; existing non-empty category `2032372934740733953` with 4 active tasks was rejected for deletion.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; route entry rows = 312 and the four task-category maintenance routes are registered.
- Strict full PHP lint over `app`, `config`, and `route`: passed; 232 PHP files checked.
- `git diff --check`: passed with CRLF conversion warnings only.
- No-token HTTP check for `/biz/bizteamprojecttaskcategory/add`: returned HTTP 200 envelope with `code=401`.
- Backend `http://127.0.0.1:82/` returned 200; frontend `http://127.0.0.1:83/` returned 200.

### Current Issues

- The smoke test intentionally leaves a logically deleted task-category test row for traceability instead of physically deleting imported-style data.
- Task add/edit/delete, task drag-to-category, task status/progress/content writes, notification push, and data-change events remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this task category maintenance compatibility slice.
- Continue another isolated frontend-visible gap before opening side-effect-heavy workflow, finance, inventory, or sale-project state flows.

## 2026-06-05 - api-agent/frontend-agent - Team Project Task Base Maintenance Compatibility

### Completed Content

- Added Java-compatible protected `POST /biz/bizteamprojecttask/add`.
- Added Java-compatible protected `POST /biz/bizteamprojecttask/edit`.
- Added Java-compatible protected `POST /biz/bizteamprojecttask/delete`.
- Preserved existing task `page`, `list`, `detail`, and `user/edit` behavior.
- Add now validates current-user project membership and category/project match.
- Add stores new tasks with `STATUS = TODO`, `PROGRESS = 0`, `DELETE_FLAG = NOT_DELETE`, `VERSION = 0`, current-user audit fields, and tenant id.
- Add creates the current token user as task `MANAGE`, and submitted project users as task `MEMBER`.
- Edit updates only submitted base task fields: `TITLE`, `STATUS`, `CONTENT_TEXT`, `PROGRESS`, `TEAM_PROJECT_TASK_CATEGORY_ID`, `SORT_CODE`, `EXT_JSON`, audit fields, and `VERSION`.
- Edit validates task status values against `TODO`, `CANCEL`, and `COMPLETE`.
- Edit/delete are allowed for the task creator, a task-level `MANAGE` user, or a project maintainer.
- Delete uses logical deletion through `DELETE_FLAG = DELETED` for the task and active task-user rows.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, frontend source, standalone task-user CRUD, generated task `LOG` comments, notification push, data-change events, Composer files, and `.env` unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/TeamProjectTaskController.php`
- `app/service/biz/TeamProjectTaskReadService.php`
- `route/app.php`
- `docs/api/biz-team-project-task-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\TeamProjectTaskController.php`: passed.
- `php -l app\service\biz\TeamProjectTaskReadService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed on team project `1903996479133360129`, category `2032372934740733953`, current user `1543837863788879873`; task `1780649358908519769` was added, assigned current-user `MANAGE` plus submitted member `2007632954432819201`, edited to `STATUS = COMPLETE`, `PROGRESS = 55`, `SORT_CODE = 12`, `VERSION = 1`, rejected invalid status `BROKEN`, and logically deleted with active task-user rows also deleted.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; route entry rows = 315 and `/biz/bizteamprojecttask/add`, `/edit`, and `/delete` are registered.
- Strict full PHP lint over `app`, `config`, and `route`: passed; 232 PHP files checked.
- `git diff --check`: passed with CRLF conversion warnings only.
- No-token HTTP check for `/biz/bizteamprojecttask/add`: returned HTTP 200 envelope with `code=401`.
- Backend `http://127.0.0.1:82/` returned 200; frontend `http://127.0.0.1:83/` returned 200.

### Current Issues

- The smoke test intentionally leaves a logically deleted task and task-user rows for traceability instead of physically deleting imported-style data.
- Java-generated task `LOG` comments, notification push, data-change events, workflow actions, and full drag ordering remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this task base maintenance compatibility slice.
- Continue another isolated frontend-visible gap, likely team-project member maintenance or a low-risk profile/selector helper, before opening workflow, finance, inventory, or sale-project state flows.

## 2026-06-05 17:20 +08:00 - api-agent/frontend-agent - Team Project Member Maintenance Compatibility

### Completed

- Added protected compatibility routes for team-project member add, manager add, and delete.
- Added `TeamProjectUserController` POST handlers with JSON/form body compatibility.
- Added `TeamProjectService` member maintenance logic for active duplicate detection, deleted-row restore, project permission checks, relation permission JSON sync, and logical deletion.
- Updated team-project API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, and active plan status.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/TeamProjectUserController.php`
- `app/service/biz/TeamProjectService.php`
- `route/app.php`
- `docs/api/biz-team-project-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\TeamProjectUserController.php`: passed.
- `php -l app\service\biz\TeamProjectService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed, 318 route entries; member `add`, `manage/add`, and `delete` listed.
- Strict PHP lint for `app`, `config`, and `route`: passed, 232 files.
- `git diff --check`: passed.
- Service smoke: passed for add member, duplicate rejection, delete, restore as manager, relation permission sync, final delete, and leader-delete rejection.
- No-token HTTP smoke for `POST /biz/bizteamprojectuser/add`: returned `code=401`.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.

### Current Issues

- Java notification push and data-change event side effects remain deferred by design.
- `/biz/bizteamprojectuser/edit` remains deferred.
- The local service smoke leaves a logically deleted test member row in the local database, preserving imported data by avoiding physical cleanup.

### Next Plan

- Continue the next small frontend-visible business compatibility slice.
- Candidate next slice: team-project member role edit only if the copied frontend exposes it during browser testing; otherwise return to remaining sale/customer/finance write gaps.

## 2026-06-05 17:33 +08:00 - api-agent/frontend-agent - Customer Base Maintenance Compatibility

### Completed

- Added protected compatibility routes for customer add, edit, and delete.
- Added `CustomerController` POST handlers with JSON/form body and Java-style delete payload compatibility.
- Added `CustomerService` base customer maintenance logic for whitelisted field mapping, owner/org defaults, write-scope validation, audit fields, version increments, and logical deletion.
- Updated customer API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/CustomerController.php`
- `app/service/biz/CustomerService.php`
- `route/app.php`
- `docs/api/biz-customer-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\CustomerController.php`: passed.
- `php -l app\service\biz\CustomerService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed; customer `1780652225237444593` was added, edited, version-incremented, and logically deleted with `DELETE_FLAG = DELETED`.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed, 322 route entries; customer `add`, `edit`, and `delete` listed.
- Strict PHP lint for `app`, `config`, and `route`: passed, 232 files.
- `git diff --check`: passed with CRLF conversion warnings only.
- No-token HTTP smoke for `POST /biz/customer/add`: returned `code=401`.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.

### Current Issues

- `/biz/customer/head/edit` remains deferred.
- SM4 plaintext phone/detail-address compatibility remains deferred pending an approved crypto compatibility plan.
- File upload/storage cleanup, Java data-change events, sale-project/customer side effects, and customer ownership reassignment remain deferred.
- The smoke test intentionally leaves a logically deleted customer row in the local database for traceability instead of physically deleting data.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this customer base maintenance compatibility slice.
- Continue the next isolated business compatibility slice, likely a safe Java-exposed frontend write with limited side effects, before opening sale-project state, finance, inventory, or workflow transition writes.

## 2026-06-05 17:48 +08:00 - api-agent/frontend-agent - Supplier Base Maintenance Compatibility

### Completed

- Added protected compatibility routes for supplier add, edit, and delete.
- Added `SupplierController` POST handlers with JSON/form body and Java-style delete payload compatibility.
- Added `SupplierService` base supplier maintenance logic for Java-required validation, whitelisted field mapping, lower-case `org` column preservation, write-scope validation, audit fields, default `ENABLE` status, and logical deletion.
- Updated supplier API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/SupplierController.php`
- `app/service/biz/SupplierService.php`
- `route/app.php`
- `docs/api/biz-supplier-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\SupplierController.php`: passed.
- `php -l app\service\biz\SupplierService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed; supplier `1780652856134702052` was added with default `ENABLE`, edited, and logically deleted with `DELETE_FLAG = DELETED`.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed, 325 route entries; supplier `add`, `edit`, and `delete` listed.
- Strict PHP lint for `app`, `config`, and `route`: passed, 232 files.
- `git diff --check`: passed with CRLF conversion warnings only.
- No-token HTTP smoke for `POST /biz/supplier/add`: returned `code=401`.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.

### Current Issues

- Supplier import/export remains deferred.
- Purchase, payment, procurement, inventory, workflow, and other supplier side effects remain deferred.
- The smoke test intentionally leaves a logically deleted supplier row in the local database for traceability instead of physically deleting data.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this supplier base maintenance compatibility slice.
- Continue another isolated low-risk master-data or page-local write endpoint before opening sale-project state, finance, inventory, or workflow transition writes.

## 2026-06-06 09:05 +08:00 - api-agent/frontend-agent - Warehouse Base Maintenance Compatibility

### Completed

- Added protected compatibility routes for warehouse add, edit, and delete.
- Added `WarehousesController` POST handlers with JSON/form body parsing and Java-style delete payload compatibility.
- Added `WarehousesService` base warehouse maintenance logic for SQL-required `name`/`code` validation, whitelisted field mapping, token owner/org defaults, admin/scoped-org/owner write-scope validation, audit fields, and logical deletion.
- Updated warehouse API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.
- Started the local backend on `http://127.0.0.1:82/` and the copied Vue frontend on `http://127.0.0.1:83/` for joint smoke testing.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/WarehousesController.php`
- `app/service/biz/WarehousesService.php`
- `route/app.php`
- `docs/api/biz-warehouses-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\WarehousesController.php`: passed.
- `php -l app\service\biz\WarehousesService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed; warehouse `1780707586896778747` was added, edited, and logically deleted with `DELETE_FLAG = DELETED`; one earlier smoke row with an overlong test `CODE` was also logically deleted.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed, 327 route entries; warehouse `add`, `edit`, and `delete` listed.
- Strict PHP lint for `app`, `config`, and `route`: passed, 232 files.
- `git diff --check`: passed with CRLF conversion warnings only.
- No-token HTTP smoke for `POST /biz/warehouses/add`: returned business `code=401`.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.

### Current Issues

- Inventory stock updates, delivery records, purchase-order writes, sale-project invoice writes, and workflow side effects remain deferred by design.
- File upload/storage, notifications, and Java data-change event side effects remain deferred.
- The smoke test intentionally leaves logically deleted warehouse rows in the local database for traceability instead of physically deleting data.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this warehouse base maintenance compatibility slice.
- Continue the next isolated low-risk frontend-visible write or route cleanup slice before opening stock, finance, workflow, or sale-project state-transition writes.

## 2026-06-06 09:25 +08:00 - api-agent/frontend-agent - Product Status And Reconciliation Compatibility

### Completed

- Added protected compatibility routes for product status toggling and selected-product reconciliation edits.
- Added `ProductController` POST handlers with JSON/form body parsing.
- Added `ProductService` lightweight product write logic for `status`, `RECONCILIATION_TYPE`, `RECONCILIATION_AMOUNT`, write-scope validation, non-negative amount validation, and update audit fields.
- Updated product API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.
- Kept the local backend on `http://127.0.0.1:82/` and the copied Vue frontend on `http://127.0.0.1:83/` for joint smoke testing.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/ProductController.php`
- `app/service/biz/ProductService.php`
- `route/app.php`
- `docs/api/biz-product-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\ProductController.php`: passed.
- `php -l app\service\biz\ProductService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed; smoke product `1780709036278310490` was inserted for testing, status was changed to `DISABLE`, reconciliation fields were updated to `ENABLE` and `12.34`, then the smoke product was logically deleted with `DELETE_FLAG = DELETED`.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed, 329 route entries; product `edit/status` and `reconciliation/edit` listed.
- Strict PHP lint for `app`, `config`, and `route`: passed, 232 files.
- `git diff --check`: passed with CRLF conversion warnings only.
- No-token HTTP smoke for `POST /biz/bizproduct/edit/status`: returned business `code=401`.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.

### Current Issues

- Product add, edit, delete, and kit product relation writes remain deferred by design.
- Inventory, purchase, sale-project, finance transaction, workflow, file upload/storage, and Java data-change/cache event behavior remain deferred.
- The smoke test intentionally leaves one logically deleted product row in the local database for traceability instead of physically deleting data.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this product status/reconciliation compatibility slice.
- Continue with another isolated low-risk frontend-visible route, or split product base add/edit/delete plus kit relation writes into a separate plan before touching it.

## 2026-06-06 09:41 +08:00 - api-agent/frontend-agent - Product Base Maintenance Compatibility

### Completed

- Added protected compatibility routes for product add, edit, and delete.
- Added `ProductController` POST handlers with JSON/form body parsing and Java-style delete payload compatibility.
- Added `ProductService` base product maintenance logic for Java-required field validation, status/default audit fields, tenant/org defaults, write-scope validation, kit-product child validation, `product_relation.CATEGORY = KIT_PRODUCT_DATA` clear-and-replace, referenced-child delete blocking, and logical deletion.
- Updated product API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.
- Kept the local backend on `http://127.0.0.1:82/` and the copied Vue frontend on `http://127.0.0.1:83/` for joint smoke testing.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/ProductController.php`
- `app/service/biz/ProductService.php`
- `route/app.php`
- `docs/api/biz-product-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\ProductController.php`: passed.
- `php -l app\service\biz\ProductService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed; single product `1780709947044271606` was added, edited, and logically deleted; kit product `1780709947533366689` was added with two child relations, rejected deletion of referenced child product `1843547479813316610`, replaced kit relations with one quantity-3 child relation, then was logically deleted. The generated smoke kit relation rows were physically cleaned because they belonged only to this temporary test product object.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed, 332 route entries; product `add`, `edit`, `delete`, `edit/status`, and `reconciliation/edit` are listed.
- Strict PHP lint for `app`, `config`, and `route`: passed, 232 files.
- `git diff --check`: passed with CRLF conversion warnings only.
- No-token HTTP smoke for `POST /biz/bizproduct/add`: returned business `code=401`.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.

### Current Issues

- Inventory stock updates, purchase-order writes, sale-project item writes, finance transaction writes, workflow actions, file upload/storage implementation, and Java data-change/cache events remain deferred by design.
- The smoke test intentionally leaves logically deleted product rows in the local database for traceability instead of physically deleting imported-style data.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this product base maintenance compatibility slice.
- Continue the next isolated frontend-visible write/read compatibility slice, avoiding stock, finance, workflow, and sale-project state side effects until their module-specific plans are opened.

## 2026-06-06 10:01 +08:00 - api-agent/frontend-agent - Sale Project Product Mark Compatibility

### Completed

- Added protected compatibility route `POST /biz/saleprojectproductitemrelation/mark/edit`.
- Added protected compatibility route `POST /biz/saleprojectproductitem/mark/edit`.
- Added `SaleProjectProductItemRelationController.editMark` and `SaleProjectProductItemRelationService.editMark`.
- Added tiny `SaleProjectProductItemController` and `SaleProjectProductItemService` for product-item `MARK` writes only.
- Validated both writes through the owning active sale project with admin-compatible, data-scope org, or project-user visibility.
- Updated API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/SaleProjectProductItemRelationController.php`
- `app/controller/biz/SaleProjectProductItemController.php`
- `app/service/biz/SaleProjectProductItemRelationService.php`
- `app/service/biz/SaleProjectProductItemService.php`
- `route/app.php`
- `docs/api/biz-saleproject-product-item-relation-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\SaleProjectProductItemRelationController.php`: passed.
- `php -l app\controller\biz\SaleProjectProductItemController.php`: passed.
- `php -l app\service\biz\SaleProjectProductItemRelationService.php`: passed.
- `php -l app\service\biz\SaleProjectProductItemService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed; sampled product item `2007746037914529793` and relation `2007746037960667138` were updated and then restored to their original `MARK` values.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed, 334 route entries; both mark-edit routes are listed.
- Strict PHP lint for `app`, `config`, and `route`: passed, 234 files.
- `git diff --check`: passed with CRLF conversion warnings only.
- No-token HTTP smoke for both mark-edit routes returned business `code=401`.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.

### Current Issues

- Product item add/edit/delete, delivery, invoice, return, inventory, finance, workflow, sale-project state changes, and Java data-change/cache events remain deferred by design.
- The smoke test restored sampled imported rows to their original `MARK` values and did not leave test data behind.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this sale-project product mark compatibility slice.
- Continue another isolated frontend-visible endpoint, still avoiding stock, finance, workflow, and state-transition side effects until their module plans are explicitly opened.

## 2026-06-06 10:09 +08:00 - api-agent/frontend-agent - Customer Head Reassignment Compatibility

### Completed

- Added protected compatibility route `POST /biz/customer/head/edit`.
- Added `CustomerController.headEdit`.
- Added `CustomerService.headEdit` for Java-compatible customer owner reassignment.
- Validated current-token customer write scope before reassignment.
- Validated target users through admin-compatible roles, data-scope organization ids, or current-user fallback.
- Updated customer API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/CustomerController.php`
- `app/service/biz/CustomerService.php`
- `route/app.php`
- `docs/api/biz-customer-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\CustomerController.php`: passed.
- `php -l app\service\biz\CustomerService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed; sampled customer `2007641838392315905` was reassigned to user `1543837863788879871` and org `1543842934270394368`, then restored to its original `USER`, `ORG`, `VERSION`, `UPDATE_TIME`, and `UPDATE_USER` values.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed, 335 route entries; `/biz/customer/head/edit` is listed.
- Strict PHP lint for `app`, `config`, and `route`: passed, 234 files.
- `git diff --check`: passed with CRLF conversion warnings only.
- No-token HTTP smoke for `/biz/customer/head/edit`: returned business `code=401`.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.

### Current Issues

- Customer import/export, file upload/storage cleanup, SM4 plaintext search, sale-project/customer side effects, notifications, and Java data-change events remain deferred by design.
- The smoke test restored sampled imported customer ownership fields and did not leave test data behind.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this customer head reassignment compatibility slice.
- Continue another isolated frontend-visible endpoint while keeping workflow, stock, finance, and sale-project state transitions behind explicit module plans.

## 2026-06-06 10:55 +08:00 - user-agent/frontend-agent - User Center Self-Service Writes

### Completed

- Added protected compatibility routes for current-user personal-center writes.
- Added `UserCenterWriteService` for password, avatar, signature, profile, workbench, and process-config edits.
- Added `/biz/user/center/edit` as a self-profile alias matching Java `BizUserController.editUser` behavior by forcing the current token user id.
- Updated user-center API docs, business directory alias docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/sys/UserCenterController.php`
- `app/service/user/UserCenterWriteService.php`
- `route/app.php`
- `docs/api/user-center-readonly-compat.md`
- `docs/api/biz-directory-alias-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\sys\UserCenterController.php`: passed.
- `php -l app\service\user\UserCenterWriteService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed, 342 route entries; all new user-center routes are listed.
- Strict PHP lint for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.
- No-token HTTP smoke for `/sys/userCenter/updateSignature` and `/biz/user/center/edit`: returned business `code=401`.
- Authenticated wrong-password smoke for `/sys/userCenter/updatePassword`: login returned `code=200`; password update returned `code=401` and did not modify the password.

### Current Issues

- Avatar compatibility stores a bounded base64 data URI; full file-provider storage and cleanup remain deferred.
- Java SM4 encrypted-field migration for phone and identity fields remains deferred.
- Admin-side user CRUD, grants, reset-password-by-admin, import/export, and enable/disable remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit this user-center self-service compatibility slice.
- Continue with another isolated frontend-visible slice, or start data-scope/permission tightening before heavy finance, stock, workflow, and sale-project state writes.

## 2026-06-06 11:12 +08:00 - user-agent/frontend-agent - User Message Detail Mark-Read Compatibility

### Completed

- Added Java-compatible read-state behavior to `GET /sys/userCenter/loginUnreadMessageDetail`.
- Kept the existing route unchanged and protected by current auth middleware.
- Marked only the current token user's `dev_relation` receiver row for `CATEGORY = MSG_TO_USER` as `read = true`.
- Preserved `dev_message` and all other recipients' relations.
- Updated user-center API docs, frontend adaptation notes, API gap map, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/service/user/UserDirectoryService.php`
- `docs/api/user-center-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\service\user\UserDirectoryService.php`: passed.
- Direct service smoke: passed; sampled unread message relation was marked read in the returned detail and receiver info, database `EXT_JSON` changed to `read=true`, then the original `EXT_JSON` was restored.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed, 342 route entries; no route changes were made.
- Strict PHP lint for `app`, `config`, and `route`: passed, 235 files.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.
- No-token HTTP smoke for `/sys/userCenter/loginUnreadMessageDetail`: returned business `code=401`.

### Current Issues

- Message send/delete, all-mark-read, WebPush, and full realtime push remain deferred.
- Admin-side user CRUD, grants, reset-password-by-admin, import/export, encrypted profile fields, and full file-provider storage remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit this user message detail mark-read compatibility slice.
- Continue with another isolated frontend-visible user/system compatibility endpoint, or move to data-scope and permission tightening before heavier finance, stock, workflow, and sale-project state writes.

## 2026-06-06 11:21 +08:00 - user-agent/frontend-agent - Index Message All-Mark-Read Compatibility

### Completed

- Added protected compatibility route `POST /sys/index/message/allMessageMarkRead`.
- Added homepage index controller and service handlers for all-message mark-read.
- Added `UserDirectoryService.markAllMessagesRead` for current-user `MSG_TO_USER` relation updates.
- Preserved existing valid `EXT_JSON` keys while setting `read = true`.
- Updated index API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/sys/IndexController.php`
- `app/service/sys/IndexService.php`
- `app/service/user/UserDirectoryService.php`
- `route/app.php`
- `docs/api/index-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\sys\IndexController.php`: passed.
- `php -l app\service\sys\IndexService.php`: passed.
- `php -l app\service\user\UserDirectoryService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed; sampled current-user unread relation changed to read, then original `EXT_JSON` was restored.
- Initial broader smoke with a larger sample timed out before completion, so a smaller deterministic sample was used and restored successfully.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed, 343 route entries; `/sys/index/message/allMessageMarkRead` is listed.
- Strict PHP lint for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.
- No-token HTTP smoke for `/sys/index/message/allMessageMarkRead`: returned business `code=401`.

### Current Issues

- Message send/delete, WebPush, and full realtime push remain deferred.
- Schedule add/delete remains deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit this index message all-mark-read compatibility slice.
- Continue with another isolated frontend-visible endpoint or start targeted data-scope/permission tightening before side-effect-heavy workflow, finance, stock, and sale-project state writes.

## 2026-06-06 11:34 +08:00 - user-agent/frontend-agent - Index Schedule Self-Service Compatibility

### Completed

- Added protected compatibility routes `POST /sys/index/schedule/add` and `POST /sys/index/schedule/deleteSchedule`.
- Added homepage index controller and service handlers for current-user schedule add/delete.
- Stored schedule rows in `sys_relation` with `CATEGORY = SYS_USER_SCHEDULE_DATA`, `OBJECT_ID = current user`, and `TARGET_ID = scheduleDate`.
- Delete accepts Java-style array bodies, `idList`, `ids`, or a single `id`, and is constrained to current-user schedule rows.
- Updated index API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/sys/IndexController.php`
- `app/service/sys/IndexService.php`
- `route/app.php`
- `docs/api/index-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\sys\IndexController.php`: passed.
- `php -l app\service\sys\IndexService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed; one temporary current-user schedule row was added, listed, deleted, and confirmed with zero residual rows.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed, 345 route entries; both schedule write routes are listed.
- Strict PHP lint for `app`, `config`, and `route`: passed, 235 files.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.
- No-token HTTP smoke for `/sys/index/schedule/add` and `/sys/index/schedule/deleteSchedule`: returned business `code=401`.

### Current Issues

- Shared calendars, schedule editing, schedule notifications, and cross-user schedule management remain deferred.
- Message send/delete, WebPush, and full realtime push remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit this index schedule self-service compatibility slice.
- Continue with another low-risk frontend-visible route or begin targeted data-scope/permission tightening before side-effect-heavy workflow, finance, stock, and sale-project state writes.

## 2026-06-06 12:08 +08:00 - auth-agent/frontend-agent - Session And Token Exit Compatibility

### Completed

- Added protected Java-compatible auth monitor exit routes:
  - `POST /auth/session/b/exit`
  - `POST /auth/session/c/exit`
  - `POST /auth/token/b/exit`
  - `POST /auth/token/c/exit`
- Added cache-backed B-side token indexing in `TokenService` for tokens created after this slice.
- Added B-side session exit by user id and token exit by token value in `SessionMonitorService`.
- Kept C-side exit endpoints as success-compatible no-op responses until C-side client auth is implemented.
- Limited ordinary users to their own user id/token while allowing admin-compatible accounts or roles to manage indexed B-side sessions.
- Updated auth API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/auth/SessionController.php`
- `app/service/auth/SessionMonitorService.php`
- `app/service/auth/TokenService.php`
- `route/app.php`
- `docs/api/auth-session-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\auth\SessionController.php`: passed.
- `php -l app\service\auth\SessionMonitorService.php`: passed.
- `php -l app\service\auth\TokenService.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list`: passed; the four exit routes are listed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- TokenService smoke: passed; temporary cache tokens were created, revoked by token value, revoked by user id, and confirmed removed.
- SessionMonitorService smoke: passed; B-side token exit, B-side session exit, and C-side deferred no-op responses behaved as expected.
- No-token HTTP smoke for all four exit routes: returned business `code=401`.
- Strict PHP lint for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.

### Current Issues

- Existing tokens created before this slice are not globally indexed; they can still revoke themselves through logout or direct bearer token handling.
- C-side client auth/login/token storage remains deferred.
- Fine-grained route permission middleware for auth monitor access remains deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit this auth session/token exit compatibility slice.
- Continue with another isolated frontend-visible route or start targeted permission/data-scope hardening before heavier workflow, finance, stock, and sale-project state writes.

## 2026-06-06 12:31 +08:00 - api-agent/frontend-agent - Dev Message Delete Compatibility

### Completed

- Added protected Java-compatible route `POST /dev/message/delete`.
- Added request body parsing for Java-style arrays of `{ id }`, `idList`, `ids`, or single `id`.
- Added `MessageService::delete` to remove `MSG_TO_USER` receiver relations and then delete selected `dev_message` rows.
- Added conservative delete scope: admin-compatible accounts/roles may delete tenant messages; ordinary users may delete only messages they created.
- Updated dev-message API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/dev/MessageController.php`
- `app/service/dev/MessageService.php`
- `route/app.php`
- `docs/api/dev-message-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\dev\MessageController.php`: passed.
- `php -l app\service\dev\MessageService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed; one temporary `dev_message` row and one temporary `dev_relation` row were inserted, deleted, and confirmed with zero residual rows.
- `php think route:list`: passed; route entries now count 350 and `/dev/message/delete` is listed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- Strict PHP lint for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.
- No-token HTTP smoke for `/dev/message/delete`: returned business `code=401`.

### Current Issues

- `/dev/message/send` remains deferred.
- SSE/WebPush realtime push behavior remains minimal and deferred for full parity.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit this dev-message delete compatibility slice.
- Continue with another small browser-visible compatibility endpoint or move into targeted permission/data-scope hardening before heavier workflow, finance, stock, and sale-project state writes.

## 2026-06-06 13:05 +08:00 - api-agent/frontend-agent - Dev Message Send Compatibility

### Completed

- Added protected Java-compatible route `POST /dev/message/send`.
- Added request body parsing for copied frontend JSON/body payloads.
- Added `MessageService::send` to create one station-message row and receiver relations.
- Added receiver parsing for string ids and selector objects containing `id`, `userId`, `value`, or `key`.
- Defaulted blank `content` to `subject` and blank `category` to `SYS`.
- Limited send access to admin-compatible accounts or roles until fine-grained route permission middleware is complete.
- Updated dev-message API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/dev/MessageController.php`
- `app/service/dev/MessageService.php`
- `route/app.php`
- `docs/api/dev-message-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\dev\MessageController.php`: passed.
- `php -l app\service\dev\MessageService.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list`: passed; `/dev/message/send` and `/dev/message/delete` are listed.
- Direct service smoke: passed; one temporary `dev_message` row and one temporary `dev_relation` row were inserted, verified, deleted, and confirmed with zero residual rows.
- No-token HTTP smoke for `/dev/message/send`: returned business `code=401`.
- `composer dump-autoload`: passed.
- `php think`: passed.
- Strict PHP lint for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.

### Current Issues

- Full SSE/WebPush realtime push behavior remains deferred for parity with Java notification side effects.
- Fine-grained route permission middleware for dev-message send remains deferred; current guard uses admin-compatible account/role detection.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit this dev-message send compatibility slice.
- Continue with targeted permission/data-scope tightening or the next isolated browser-visible compatibility endpoint before side-effect-heavy workflow, finance, stock, and sale-project state writes.

## 2026-06-06 13:36 +08:00 - api-agent/frontend-agent - Dev Message Detail Mark-Read Compatibility

### Completed

- Aligned `GET /dev/message/detail` with Java detail read-state behavior.
- Passed the current auth payload into `MessageService::detail`.
- Marked only the current token user's `MSG_TO_USER` receiver relation as read when viewing message detail.
- Preserved existing relation `EXT_JSON` keys while setting `read = true`.
- Kept the existing route and response shape unchanged.
- Updated dev-message API docs, frontend adaptation notes, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/dev/MessageController.php`
- `app/service/dev/MessageService.php`
- `docs/api/dev-message-readonly-compat.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\dev\MessageController.php`: passed.
- `php -l app\service\dev\MessageService.php`: passed.
- `php think route:list`: passed; `/dev/message/detail`, `/send`, and `/delete` are listed.
- Direct service smoke: passed; one temporary message relation changed from `read=false` to `read=true` on detail read, then all temporary rows were removed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- Strict PHP lint for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend `http://127.0.0.1:82/think`: HTTP 200 after restarting the ThinkPHP local server.
- Frontend `http://127.0.0.1:83/`: HTTP 200 after restarting the Vite local server.

### Current Issues

- Full SSE/WebPush realtime push behavior remains deferred for parity with Java notification side effects.
- Fine-grained route permission middleware remains deferred.
- Vite generated an untracked temporary config file during local frontend startup; it was not committed or deleted.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit this dev-message detail mark-read compatibility slice.
- Continue with targeted permission/data-scope tightening or the next isolated browser-visible compatibility endpoint before side-effect-heavy workflow, finance, stock, and sale-project state writes.

## 2026-06-06 12:55 +08:00 - user-agent/frontend-agent - User Role Grant Save Compatibility

### Completed

- Added protected Java-compatible role grant save routes:
  - `POST /sys/user/grantRole`
  - `POST /biz/user/grantRole`
- Added controller handlers for system and business user role grant saves.
- Added `UserDirectoryService::grantRole` to clear and rewrite `SYS_USER_HAS_ROLE` relations for a target user.
- Validated active users, active tenant-compatible role ids, admin-compatible payloads, route/button permission payloads, and business data-scope/self fallback.
- Kept empty `roleIdList` as a supported clear operation.
- Updated grant API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/sys/UserController.php`
- `app/service/user/UserDirectoryService.php`
- `route/app.php`
- `docs/api/sys-user-grant-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\sys\UserController.php`: passed.
- `php -l app\service\user\UserDirectoryService.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list`: passed; `/sys/user/grantRole` and `/biz/user/grantRole` are listed.
- Direct service smoke: passed; one active user's roles were replaced with one active tenant-compatible role, then cleared through the business-scope path, and the original `SYS_USER_HAS_ROLE` relation rows were restored.
- `composer dump-autoload`: passed.
- `php think`: passed.
- Strict PHP lint for `app`, `config`, and `route`: passed, 235 files.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.
- No-token HTTP smoke for both new POST routes: returned business `code=401`.

### Current Issues

- Resource and permission grant save endpoints remain deferred.
- Admin-side user CRUD, enable/disable, reset-password-by-admin, import/export, and encrypted profile-field migration remain deferred.
- Fine-grained route permission middleware remains deferred; this slice uses payload-based admin/route/button guards.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit this user role grant save compatibility slice.
- Continue with another small frontend-visible compatibility route or begin targeted permission/data-scope tightening before side-effect-heavy workflow, finance, stock, and sale-project state writes.

## 2026-06-06 13:20 +08:00 - user-agent/frontend-agent - User Resource Grant Save Compatibility

### Completed

- Added protected Java-compatible resource grant save route:
  - `POST /sys/user/grantResource`
- Added controller handler for system user resource grant saves.
- Added `UserDirectoryService::grantResource` to clear and rewrite `SYS_USER_HAS_RESOURCE` relations for a target user.
- Preserved Java-compatible `EXT_JSON` with `menuId` and `buttonInfo`.
- Validated active users, active menu/button resources, admin-compatible payloads or route/button permission payloads, and Java's system-module/super-admin target safeguard.
- Kept empty `grantInfoList` as a supported clear operation.
- Updated grant API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/sys/UserController.php`
- `app/service/user/UserDirectoryService.php`
- `route/app.php`
- `docs/api/sys-user-grant-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\sys\UserController.php`: passed.
- `php -l app\service\user\UserDirectoryService.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list`: passed; `/sys/user/grantResource` is listed.
- Direct service smoke: passed; one active user's resource grants were replaced with one non-system menu grant and button info, then the original `SYS_USER_HAS_RESOURCE` relation rows were restored.
- `composer dump-autoload`: passed.
- `php think`: passed.
- Strict PHP lint for `app`, `config`, and `route`: passed, 235 files.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.
- No-token HTTP smoke for the new POST route: returned business `code=401`.

### Current Issues

- Permission grant save endpoint remains deferred.
- Role resource grants, mobile resource grants, admin-side user CRUD, enable/disable, reset-password-by-admin, import/export, and encrypted profile-field migration remain deferred.
- Fine-grained route permission middleware remains deferred; this slice uses payload-based admin/route/button guards.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit this user resource grant save compatibility slice.
- Continue with user permission grant save compatibility, or pause user grants and move to targeted permission/data-scope hardening before side-effect-heavy workflow, finance, stock, and sale-project state writes.

## 2026-06-06 13:45 +08:00 - user-agent/frontend-agent - User Permission Grant Save Compatibility

### Completed

- Added protected Java-compatible permission grant save route:
  - `POST /sys/user/grantPermission`
- Added controller handler for system user permission grant saves.
- Added `UserDirectoryService::grantPermission` to clear and rewrite `SYS_USER_HAS_PERMISSION` relations for a target user.
- Preserved Java-compatible `EXT_JSON` with `apiUrl`, `scopeCategory`, and `scopeDefineOrgIdList`.
- Validated active users, supported data-scope categories, custom organization ids, and admin-compatible payloads or route/button permission payloads.
- Kept empty `grantInfoList` as a supported clear operation.
- Updated grant API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/sys/UserController.php`
- `app/service/user/UserDirectoryService.php`
- `route/app.php`
- `docs/api/sys-user-grant-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\sys\UserController.php`: passed.
- `php -l app\service\user\UserDirectoryService.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list`: passed; `/sys/user/grantPermission` is listed.
- Direct service smoke: passed; one active user's permission grants were replaced with one API/data-scope grant, then the original `SYS_USER_HAS_PERMISSION` relation rows were restored.
- `composer dump-autoload`: passed.
- `php think`: passed.
- Strict PHP lint for `app`, `config`, and `route`: passed, 235 files.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.
- No-token HTTP smoke for the new POST route: returned business `code=401`.

### Current Issues

- Route-permission middleware remains deferred; this slice uses payload-based admin/route/button guards.
- Role permission grants, admin-side user CRUD, enable/disable, reset-password-by-admin, import/export, and encrypted profile-field migration remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit this user permission grant save compatibility slice.
- Continue with targeted permission/data-scope hardening or move to the next low-risk frontend-visible write route before side-effect-heavy workflow, finance, stock, and sale-project state writes.

## 2026-06-06 14:10 +08:00 - user-agent/frontend-agent - User Enable Disable Compatibility

### Completed

- Added protected Java-compatible user status routes:
  - `POST /sys/user/disableUser`
  - `POST /sys/user/enableUser`
  - `POST /biz/user/disableUser`
  - `POST /biz/user/enableUser`
- Added controller handlers for system and business user status switches.
- Added `UserDirectoryService::setUserStatus` to update only `sys_user.USER_STATUS`.
- Preserved business user data-scope guarding with organization scope or current-user fallback.
- Updated status API docs, biz directory alias docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/sys/UserController.php`
- `app/service/user/UserDirectoryService.php`
- `route/app.php`
- `docs/api/biz-directory-alias-readonly.md`
- `docs/api/user-status-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\sys\UserController.php`: passed.
- `php -l app\service\user\UserDirectoryService.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list`: passed; all four status routes are listed, route rows count is 360.
- Direct service smoke: passed; one active user's status was changed through the system path, restored through the business path, and then confirmed restored to the original value.
- `composer dump-autoload`: passed.
- `php think`: passed.
- Strict PHP lint for `app`, `config`, and `route`: passed, 235 files.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.
- No-token HTTP smoke for all four new POST routes: returned business `code=401`.

### Current Issues

- User add/edit/delete, reset-password-by-admin, import/export, token/session invalidation on status change, and route-permission middleware remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit this user enable/disable compatibility slice.
- Continue with the next low-risk frontend-visible user write route, or move to targeted permission/data-scope hardening before side-effect-heavy workflow, finance, stock, and sale-project state writes.

## 2026-06-06 14:35 +08:00 - user-agent/frontend-agent - User Reset Password Compatibility

### Completed

- Added protected Java-compatible reset-password routes:
  - `POST /sys/user/resetPassword`
  - `POST /biz/user/resetPassword`
- Added controller handlers for system and business user reset-password actions.
- Added `UserDirectoryService::resetPassword` to update only `sys_user.PASSWORD`.
- Reused the existing Java-compatible SM3 hasher for default password hashing.
- Preserved business user data-scope guarding with organization scope or current-user fallback.
- Kept default password value and generated hash out of API responses, test output, and documentation.
- Updated reset-password API docs, biz directory alias docs, user grant/status docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/sys/UserController.php`
- `app/service/user/UserDirectoryService.php`
- `route/app.php`
- `docs/api/biz-directory-alias-readonly.md`
- `docs/api/sys-user-grant-readonly.md`
- `docs/api/user-reset-password-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\sys\UserController.php`: passed.
- `php -l app\service\user\UserDirectoryService.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list`: passed; both reset-password routes are listed, route rows count is 362.
- Direct service smoke: passed; the configured default password record exists, one sampled active user's password was reset through both system and business paths, and the original password hash was restored after each path.
- `composer dump-autoload`: passed.
- `php think`: passed.
- Strict PHP lint for `app`, `config`, and `route`: passed, 235 files.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend `http://127.0.0.1:82/`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.
- No-token HTTP smoke for both new POST routes on the backend root path: returned business `code=401`.

### Current Issues

- User add/edit/delete, import/export, token/session invalidation after reset, route-permission middleware, and encrypted profile-field migration remain deferred.
- Direct backend test path is the current PHP server root path; `/think/...` returns a ThinkPHP 404 in this local server mode, while the frontend proxy can still apply its own prefix behavior.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit this user reset-password compatibility slice.
- Continue with a focused user CRUD planning slice or targeted permission/data-scope hardening before side-effect-heavy workflow, finance, stock, and sale-project state writes.

## 2026-06-06 15:05 +08:00 - user-agent/frontend-agent - User Delete Compatibility

### Completed

- Added protected Java-compatible user delete routes:
  - `POST /sys/user/delete`
  - `POST /biz/user/delete`
- Added controller handlers for system and business user row-delete/batch-delete actions.
- Added `UserDirectoryService::deleteUsers` to logically delete only `sys_user` rows by setting `DELETE_FLAG = DELETED`.
- Added payload compatibility for copied frontend array deletes and common `id`, `ids`, `idList`, and `userIds` forms.
- Added Java-compatible cleanup for `sys_user.DIRECTOR_ID`, `sys_user.POSITION_JSON[*].directorId`, and `sys_org.DIRECTOR_ID`.
- Preserved business user data-scope guarding with organization scope or current-user fallback.
- Rejected built-in/admin-compatible accounts from deletion.
- Updated user-delete API docs, biz directory alias docs, user grant/status docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/sys/UserController.php`
- `app/service/user/UserDirectoryService.php`
- `route/app.php`
- `docs/api/biz-directory-alias-readonly.md`
- `docs/api/sys-user-grant-readonly.md`
- `docs/api/user-delete-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\sys\UserController.php`: passed.
- `php -l app\service\user\UserDirectoryService.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list`: passed; both delete routes are listed, route rows count is 364.
- Direct service smoke: passed; one sampled non-admin active user was logically deleted through both system and business paths, affected user and organization director references were cleared, `POSITION_JSON` supervisor data was cleaned, and all touched values were restored.
- `composer dump-autoload`: passed.
- `php think`: passed.
- Strict PHP lint for `app`, `config`, and `route`: passed, 235 files.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend `http://127.0.0.1:82/`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.
- No-token HTTP smoke for both new POST routes on the backend root path: returned business `code=401`.

### Current Issues

- User add/edit, import/export, token/session invalidation after delete, Java data-change event publishing, route-permission middleware, and encrypted profile-field migration remain deferred.
- Direct backend test path is the current PHP server root path; `/think/...` returns a ThinkPHP 404 in this local server mode, while the frontend proxy can still apply its own prefix behavior.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit this user delete compatibility slice.
- Continue with a focused user add/edit planning slice, because that path needs broader field validation, default-password hashing, org/position validation, uniqueness checks, and role grant coordination.

## 2026-06-06 15:45 +08:00 - user-agent/frontend-agent - User Add Edit Compatibility

### Completed

- Added protected Java-compatible user add/edit routes:
  - `POST /sys/user/add`
  - `POST /sys/user/edit`
  - `POST /biz/user/add`
  - `POST /biz/user/edit`
- Added controller handlers for system and business user add/edit forms.
- Added `UserDirectoryService::addUser` and `UserDirectoryService::editUser` for base `sys_user` profile writes.
- Added field mapping for copied frontend camelCase form payloads and camelCase detail/page aliases for extended user profile fields.
- Added validation for required account/name/org/position, account/phone/email uniqueness, active organization, active position, optional director, extra position JSON, tenant compatibility, and non-negative salary.
- Preserved Java-compatible defaults on add and protected password/status/create metadata on edit.
- Preserved business user data-scope guarding with organization scope or current-user edit fallback.
- Updated user add/edit API docs, biz directory docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/sys/UserController.php`
- `app/service/user/UserDirectoryService.php`
- `route/app.php`
- `docs/api/biz-directory-alias-readonly.md`
- `docs/api/user-add-edit-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\sys\UserController.php`: passed.
- `php -l app\service\user\UserDirectoryService.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list`: passed; all four add/edit routes are listed, route rows count is 368.
- Direct service smoke: passed; one temporary system user and one temporary business user were created, edited, verified, and physically removed by unique test ids/accounts.
- Temporary data cleanup check: passed; `codex_` smoke account count returned 0.
- `composer dump-autoload`: passed.
- `php think`: passed.
- Strict PHP lint for `app`, `config`, and `route`: passed, 235 files.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend `http://127.0.0.1:82/`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.
- No-token HTTP smoke for all four new POST routes on the backend root path: returned business `code=401`.

### Current Issues

- User import/export, route-permission middleware, token/session invalidation after profile edits, Java data-change event publishing, and full SM4 encrypted-field migration remain deferred.
- Direct backend test path is the current PHP server root path; `/think/...` returns a ThinkPHP 404 in this local server mode, while the frontend proxy can still apply its own prefix behavior.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit this user add/edit compatibility slice.
- Continue with user import/export planning or move to the next browser-visible low-risk write/read gap before side-effect-heavy workflow, finance, stock, and sale-project state writes.

## 2026-06-06 16:30 +08:00 - user-agent/frontend-agent - Organization Add Edit Delete Compatibility

### Completed

- Added protected Java-compatible organization write routes:
  - `POST /sys/org/add`
  - `POST /sys/org/edit`
  - `POST /sys/org/delete`
  - `POST /biz/org/add`
  - `POST /biz/org/edit`
  - `POST /biz/org/delete`
- Added controller handlers for system and business organization forms and delete actions.
- Added `OrgService::add`, `OrgService::edit`, and `OrgService::delete` for base `sys_org` writes.
- Added validation for parent organization, category, sort code, same-level duplicate names, optional director, tenant compatibility, and parent cycle prevention.
- Added dependency-protected organization delete checks for active users, user extra-position JSON, roles, and positions.
- Used logical delete on `sys_org.DELETE_FLAG` during the staged refactor.
- Preserved business organization data-scope guarding for add/edit/delete.
- Updated organization API docs, biz directory docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/sys/OrgController.php`
- `app/service/user/OrgService.php`
- `route/app.php`
- `docs/api/biz-directory-alias-readonly.md`
- `docs/api/org-write-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\sys\OrgController.php`: passed.
- `php -l app\service\user\OrgService.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list`: passed; all six organization write routes are listed, route rows count is 374.
- Direct service smoke: passed; one temporary system organization and one temporary business organization were created, edited, logically deleted, verified, and physically removed by unique test ids.
- Temporary data cleanup check: passed; remaining temporary org rows returned 0.
- `composer dump-autoload`: passed.
- `php think`: passed.
- Strict PHP lint for `app`, `config`, and `route`: passed, 235 files.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend `http://127.0.0.1:82/`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.
- No-token HTTP smoke for all six new POST routes on the backend root path: returned business `code=401`.

### Current Issues

- Position add/edit/delete, user import/export, route-permission middleware, Java data-change event publishing, and Java physical delete behavior remain deferred.
- Direct backend test path is the current PHP server root path; `/think/...` returns a ThinkPHP 404 in this local server mode, while the frontend proxy can still apply its own prefix behavior.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this organization write compatibility slice.
- Continue with the next low-risk browser-visible personnel module gap: position add/edit/delete planning or user import/export planning, before side-effect-heavy workflow, finance, stock, and sale-project state writes.

## 2026-06-06 17:05 +08:00 - user-agent/frontend-agent - Position Add Edit Delete Compatibility

### Completed

- Added protected Java-compatible position write routes:
  - `POST /sys/position/add`
  - `POST /sys/position/edit`
  - `POST /sys/position/delete`
  - `POST /biz/position/add`
  - `POST /biz/position/edit`
  - `POST /biz/position/delete`
- Added controller handlers for system and business position forms and delete actions.
- Added `PositionService::add`, `PositionService::edit`, and `PositionService::delete` for base `sys_position` writes.
- Added validation for active organization, category, sort code, same-organization duplicate names, and tenant compatibility.
- Added dependency-protected position delete checks for active user `POSITION_ID` and active user `POSITION_JSON[*].positionId`.
- Used logical delete on `sys_position.DELETE_FLAG` during the staged refactor.
- Preserved business position organization data-scope guarding for add/edit/delete.
- Updated position API docs, biz directory docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/sys/PositionController.php`
- `app/service/user/PositionService.php`
- `route/app.php`
- `docs/api/biz-directory-alias-readonly.md`
- `docs/api/position-write-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\sys\PositionController.php`: passed.
- `php -l app\service\user\PositionService.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list`: passed; all six position write routes are listed, route rows count is 380.
- Direct service smoke: passed; one temporary system position and one temporary business position were created, edited, logically deleted, verified, and physically removed by unique test ids.
- Temporary data cleanup check: passed; remaining temporary position rows returned 0.
- `composer dump-autoload`: passed.
- `php think`: passed.
- Strict PHP lint for `app`, `config`, and `route`: passed, 235 files.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend `http://127.0.0.1:82/`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.
- No-token HTTP smoke for all six new POST routes on the backend root path: returned business `code=401`.

### Current Issues

- User import/export, route-permission middleware, Java data-change event publishing, Java physical delete behavior, and full encrypted profile-field migration remain deferred.
- Direct backend test path is the current PHP server root path; `/think/...` returns a ThinkPHP 404 in this local server mode, while the frontend proxy can still apply its own prefix behavior.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this position write compatibility slice.
- Continue with a focused user import/export planning slice or move back to safe read/write gaps outside personnel before side-effect-heavy workflow, finance, stock, and sale-project state writes.

## 2026-06-06 17:45 +08:00 - user-agent/frontend-agent - User Export Download Compatibility

### Completed

- Added protected Java-compatible user download routes:
  - `GET /sys/user/downloadImportUserTemplate`
  - `GET /sys/user/export`
  - `GET /sys/user/exportUserInfo`
  - `GET /biz/user/export`
  - `GET /biz/user/exportUserInfo`
- Added controller download handlers for system and business user pages.
- Added `UserDirectoryService::downloadImportUserTemplate`, `exportUsers`, and `exportUserInfoFile`.
- Returned CSV/plain-text download blobs without adding Composer dependencies.
- Reused sanitized user rows so exported content does not include passwords, token data, or secrets.
- Added export permission checks and conservative business organization data-scope or current-user fallback.
- Updated export API docs, biz directory alias docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/sys/UserController.php`
- `app/service/user/UserDirectoryService.php`
- `route/app.php`
- `docs/api/biz-directory-alias-readonly.md`
- `docs/api/user-export-download-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\sys\UserController.php`: passed.
- `php -l app\service\user\UserDirectoryService.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list`: passed; all five download routes are listed, route rows count is 385.
- `composer dump-autoload`: passed.
- `php think`: passed.
- Strict PHP lint for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- Direct template service smoke: passed; `downloadImportUserTemplate` returned `user-import-template.csv`, `text/csv`, and no `PASSWORD` header.
- Backend no-token HTTP smoke for all five new GET routes: returned business `code=401`.

### Current Issues

- Direct DB-backed export service smoke could not be completed because local MySQL rejected connections.
- Windows service `MySQL80` exists but `Start-Service MySQL80` failed and the service remained stopped.
- `POST /sys/user/import`, real `.xlsx` generation, real `.docx` template rendering, file upload/storage behavior, route-permission middleware, Java data-change events, and encrypted profile-field migration remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- After local MySQL is available, rerun direct service smoke for system export, business export, and single-user profile export.
- Continue with the next safe frontend-visible slice outside personnel import/export, or start targeted data-scope and permission hardening before side-effect-heavy workflow, finance, stock, and sale-project state writes.

## 2026-06-06 18:05 +08:00 - test-agent - Smoke Runbook Automation

### Completed

- Confirmed `OA-ThinkPHP` is on `refactor/thinkphp-main`, clean, and ahead of origin by the completed user export compatibility commit.
- Cleaned up the temporary local `mysqld.exe` processes started during the DB export smoke attempt.
- Confirmed current route coverage already includes `/dev/message/createSseConnect`, `/biz/dict/*`, `/biz/org/*`, `/biz/user/*`, and `/biz/position/*`.
- Confirmed org/user display-field compatibility is already documented in `docs/api/sys-user-org-display-compat.md` and implemented through user/org/position row aliases.
- Added `scripts/test-agent-smoke.ps1` for repeatable post-slice baseline checks.
- Added `docs/tasks/test-agent-smoke-runbook.md` with usage and DB blocker notes.

### Modified Files

- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/test-agent-smoke-runbook.md`
- `scripts/test-agent-smoke.ps1`

### Test Results

- `.\scripts\test-agent-smoke.ps1`: passed.
- `composer dump-autoload`: passed through the smoke script.
- `php think`: passed through the smoke script.
- `php think route:list`: passed through the smoke script.
- Required route coverage check: passed for current personnel download routes, message SSE, and biz directory aliases.
- Strict PHP lint for `app`, `config`, and `route`: passed, 235 files.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- Direct DB-backed user export smoke remains blocked because the local MySQL instance rejects the ThinkPHP `.env` database user with access denied.
- User cooperation is needed to start the intended MySQL instance or provide/update working local database credentials for `phpoa20026`.

### Next Plan

- After DB credentials are fixed, rerun the DB-backed user export service smokes and then browser-smoke the copied user export/download buttons.

## 2026-06-06 17:30 +08:00 - test-agent - DB Export Smoke Follow-Up

### Completed

- Started the user-provided local runtime bundle from `F:\project\socket\AI\testPhp\files\startServer1.bat`.
- Confirmed MySQL listens on `127.0.0.1:3306`.
- Confirmed Redis listens on `127.0.0.1:6379` and responds after authentication.
- Updated the ignored local `.env` to use the user-provided local MySQL and Redis runtime values.
- Confirmed `phpoa20026` exists and contains application tables.
- Reran direct DB-backed user export smokes.
- Updated `docs/tasks/test-agent-smoke-runbook.md` from blocker notes to the current local runtime and DB-backed export smoke status.

### Modified Files

- `STATUS.md`
- `docs/tasks/test-agent-smoke-runbook.md`
- local ignored `.env` only, not committed

### Test Results

- MySQL database creation/confirmation: passed.
- `php think route:list` user download route check: passed.
- Redis authenticated ping: passed.
- `UserDirectoryService::exportUsers(false, ...)`: passed and returned a CSV descriptor.
- `UserDirectoryService::exportUsers(true, ...)`: passed and returned a CSV descriptor.
- `UserDirectoryService::exportUserInfoFile(...)`: passed and returned a text descriptor.
- Export smoke safety check: passed; no password header/text was present in the sampled descriptors.

### Current Issues

- Browser smoke for copied Vue export/download buttons still needs an authenticated frontend session.
- Real `.xlsx` generation, real `.docx` rendering, and user import remain deferred.

### Next Plan

- Commit the DB smoke follow-up documentation.
- Continue with browser-facing smoke when frontend/backend servers and a login session are available, or move to the next low-risk read/API slice.

## 2026-06-06 17:40 +08:00 - docs-agent - Local Runtime Service Notes

### Completed

- Added project-level local runtime startup notes to `AGENTS.md` so future Codex conversations can find the database/Redis startup path immediately.
- Added `docs/tasks/local-runtime-services.md` with the local service bundle path, expected MySQL/Redis/PHP-FastCGI ports, non-secret `.env` keys, and verification commands.
- Linked the local runtime notes from `docs/tasks/test-agent-smoke-runbook.md`.
- Kept MySQL and Redis passwords out of tracked documentation; they remain in the ignored local `.env` or must be provided by the user.

### Modified Files

- `AGENTS.md`
- `STATUS.md`
- `docs/tasks/local-runtime-services.md`
- `docs/tasks/test-agent-smoke-runbook.md`

### Current Issues

- Browser smoke for copied Vue export/download buttons still needs an authenticated frontend session.

### Next Plan

- Commit the local runtime documentation.
- Continue with the next browser-facing smoke or low-risk read/API slice.

## 2026-06-06 17:55 +08:00 - test-agent - DB Smoke Script Automation

### Completed

- Ran true multi-Agent coordination:
  - `frontend-agent` mapped user export/download browser smoke paths and blockers.
  - `api-agent` recommended `/biz/dict/edit` as the next low-risk frontend-visible implementation slice.
  - `test-agent` identified the missing repeatable DB-backed smoke script.
  - `test-agent worker` added the script-only patch.
- Added `scripts/test-agent-db-smoke.ps1`.
- Updated `scripts/test-agent-smoke.ps1` so optional no-token HTTP smoke handles real HTTP 401 responses.
- Updated `docs/tasks/test-agent-smoke-runbook.md` with the DB smoke command.
- Updated implementation and active plan notes.

### Modified Files

- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`
- `docs/tasks/test-agent-smoke-runbook.md`
- `scripts/test-agent-db-smoke.ps1`
- `scripts/test-agent-smoke.ps1`

### Test Results

- `.\scripts\test-agent-smoke.ps1 -SkipComposer`: passed.
- `.\scripts\test-agent-db-smoke.ps1`: passed.
- DB smoke table count check: passed, `phpoa20026` has application tables.
- Redis smoke: passed, `PING` returned `PONG`.
- User export service smoke: passed for system export, business export, and single-user profile export descriptors.
- Export safety check: passed; sampled content did not include `PASSWORD`.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- Browser smoke for copied Vue export/download buttons still needs a valid login account and, for business export buttons, matching button permission codes.
- `/biz/dict/edit` is the current recommended next low-risk implementation slice.

### Next Plan

- Commit the DB smoke script automation.
- Start a focused worker for `/biz/dict/edit` after reviewing Java/controller details locally.

## 2026-06-06 18:20 +08:00 - api-agent - Business Dictionary Edit Compatibility

### Completed

- Used true multi-Agent workflow:
  - `api-agent explorer` recommended `/biz/dict/edit` as the next low-risk frontend-visible slice.
  - `api-agent worker` implemented the route/controller/service changes.
  - Main merge/coordinator reviewed the patch, reran smoke tests, and updated docs/status.
- Added protected `POST /biz/dict/edit`.
- Added controller body parsing for form POST, raw JSON, and request parameters.
- Added business dictionary edit service logic restricted to active `CATEGORY = BIZ` rows.
- Added validation for required id, label, numeric sort code, optional business parent, duplicate labels, and tenant compatibility.
- Preserved category, dictionary value, tenant, and create metadata.
- Updated API docs, biz directory compatibility docs, public route-change request, API gap map, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`
- `app/controller/dev/DictController.php`
- `app/service/dev/DictService.php`
- `route/app.php`
- `docs/api/biz-dict-edit-compat.md`
- `docs/api/biz-directory-alias-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\dev\DictController.php`: passed.
- `php -l app\service\dev\DictService.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list`: passed and listed `POST /biz/dict/edit`.
- Direct service smoke: passed; temporary BIZ dictionary rows were inserted, one row was edited, duplicate label blocking was verified, and all temporary rows were physically cleaned up.
- `php think`: passed.
- Strict PHP lint for `app`, `config`, and `route`: passed.
- `.\scripts\test-agent-smoke.ps1 -SkipComposer`: passed.
- `.\scripts\test-agent-db-smoke.ps1`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- `/biz/dict/add` and `/biz/dict/delete` remain deferred.
- Java dictionary cache invalidation parity remains deferred.
- Browser smoke for copied Vue business dictionary edit still needs a valid login session.

### Next Plan

- Commit this business dictionary edit compatibility slice.
- Continue with browser-facing smoke or the next low-risk read/API slice after confirming frontend login credentials.

## 2026-06-06 18:45 +08:00 - docs-agent - Default Multi-Agent And Local Login Notes

### Completed

- Ran a scoped `docs-agent worker` for documentation updates.
- Added project-level guidance that future Codex conversations should default to real multi-Agent mode.
- Documented that the main conversation is the merge/coordinator session and scoped workers such as `frontend-agent`, `api-agent`, `test-agent`, and `docs-agent` handle explicitly assigned slices only.
- Added local login smoke credential variable names to tracked docs:
  - `LOCAL_SUPER_ADMIN_ACCOUNT`
  - `LOCAL_SUPER_ADMIN_PASSWORD`
- Stored the user-provided local login values in the ignored local `.env`.
- Confirmed no plaintext local login password, database password, or Redis password was written to tracked documentation.

### Modified Files

- `AGENTS.md`
- `STATUS.md`
- `docs/tasks/frontend-joint-test-workflow.md`
- `docs/tasks/local-runtime-services.md`
- `docs/tasks/test-agent-smoke-runbook.md`
- local ignored `.env` only, not committed

### Current Issues

- Browser smoke still requires starting backend/frontend services and using the ignored local `.env` values for login.

### Next Plan

- Commit the default multi-Agent and local login documentation slice.
- Continue future work in real multi-Agent mode by default.

## 2026-06-06 19:10 +08:00 - docs-agent worker - New Conversation Bootstrap

### Completed

- Added `docs/tasks/new-conversation-bootstrap.md` for future Codex conversations.
- Documented that new conversations should default to real multi-Agent mode with the main conversation acting as merge/coordinator and scoped worker Agents executing slices.
- Documented startup reads for `AGENTS.md`, `PLANS.md`, `IMPLEMENT.md`, and `STATUS.md`.
- Documented local runtime startup dependency through `docs/tasks/local-runtime-services.md`.
- Documented login smoke `.env` variable names only:
  - `LOCAL_SUPER_ADMIN_ACCOUNT`
  - `LOCAL_SUPER_ADMIN_PASSWORD`
- Documented common smoke scripts:
  - `scripts/test-agent-smoke.ps1`
  - `scripts/test-agent-db-smoke.ps1`
- Repeated the Java source read-only boundary and fallback rule for conversations with missing tools.
- Added an `AGENTS.md` pointer to the bootstrap note.

### Modified Files

- `AGENTS.md`
- `STATUS.md`
- `docs/tasks/new-conversation-bootstrap.md`

### Test Results

- Documentation-only slice; no runtime smoke was required.

### Current Issues

- None for this documentation slice.

### Next Plan

- Main merge/coordinator can review and commit when ready.

## 2026-06-11 15:55 +08:00 - merge-agent - Collection Receipt Mark-Success Compatibility

### Completed

- Continued in real multi-Agent mode: the main merge/coordinator implemented and accepted the slice, while explorer Agent `019eb59c-47f3-79e0-8951-984177410965` verified the Java behavior and side-effect boundary.
- Added Java-compatible `POST /biz/bizcollectionreceipt/mark/success/edit`.
- Matched Java `BizCollectionReceiptServiceImpl.markSettlement(String id)` as a single-table update:
  - requires `id`;
  - checks current tenant and write scope;
  - sets `PLAY_STATUS = AlreadySettled`;
  - updates `UPDATE_TIME` and `UPDATE_USER`;
  - increments `VERSION`.
- Kept batch expenditure, add, edit, and delete deferred because they require expenditure-record, settlement-account, or broader transactional side-effect handling.

### Modified Files

- `app/service/biz/CollectionReceiptService.php`
- `app/controller/biz/CollectionReceiptController.php`
- `route/app.php`
- `docs/api/biz-collection-receipt-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `PLANS.md`
- `STATUS.md`

### Test Results

- `php -l app\service\biz\CollectionReceiptService.php`: passed.
- `php -l app\controller\biz\CollectionReceiptController.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list`: passed and lists `POST /biz/bizcollectionreceipt/mark/success/edit`.
- DB smoke marked one imported collection receipt as settled, verified `PLAY_STATUS`, unchanged amount/payment fields, `VERSION` increment, no settlement-account/statement/payment/expenditure side effects, restored the original row, and verified missing-id `400` plus non-admin `403`.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- Collection-receipt add/edit/delete remain deferred. Subsequent state: `POST /biz/bizcollectionreceipt/batchExpenditure/edit` is covered by the 2026-06-17 repayment quick-settlement slice, while broader collection-receipt CRUD and related finance side effects remain deferred.
- Debit-note mark-success is now covered as a single-table status update; payment/settlement-account side-effect paths remain deferred in history-add and batch-repayment.

### Next Plan

- Commit this collection-receipt mark-success compatibility slice.
- Continue with the next true low-risk write or browser-visible compatibility gap, not scanner false positives.

## 2026-06-12 08:18 +08:00 - merge-agent - Debit Note Mark-Success Compatibility

### Completed

- Continued in real multi-Agent mode: the main merge/coordinator implemented and accepted the slice, while explorer Agent `019eb605-9191-7502-b1f4-dcc337233471` verified the Java behavior and side-effect boundary.
- Added Java-compatible `POST /biz/bizdebitnote/mark/success/edit`.
- Matched Java `BizDebitNoteServiceImpl.markSettlement(String id)` as a single-table update:
  - requires `id`;
  - checks current tenant and write scope;
  - sets `PLAY_STATUS = AlreadySettled`;
  - updates `UPDATE_TIME` and `UPDATE_USER`;
  - increments `VERSION`.
- Kept history add, batch repayment, add, edit, and delete deferred in that slice because they require payment-record, settlement-account, or broader transactional side-effect handling. Subsequent state: batch repayment is covered by the 2026-06-17 loan-repayment quick-settlement slice.

### Modified Files

- `app/service/biz/DebitNoteService.php`
- `app/controller/biz/DebitNoteController.php`
- `route/app.php`
- `docs/api/biz-debit-note-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `PLANS.md`
- `STATUS.md`

### Test Results

- `php -l app\service\biz\DebitNoteService.php`: passed.
- `php -l app\controller\biz\DebitNoteController.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list`: passed and lists `POST /biz/bizdebitnote/mark/success/edit`.
- DB smoke marked one imported debit note as settled, verified `PLAY_STATUS`, unchanged amount/history/expenditure/org fields, `VERSION` increment, no settlement-account/statement/payment/expenditure side effects, restored the original row, and verified missing-id `400` plus non-admin `403`.

### Current Issues

- `POST /biz/bizdebitnote/history/add` and debit-note add/edit/delete remain deferred. Subsequent state: `POST /biz/bizdebitnote/batchRepayment/edit` is covered by the 2026-06-17 loan-repayment quick-settlement slice.
- Payroll low-risk edit/batch-edit/delete is the next candidate recommended by the payroll explorer.

### Next Plan

- Commit this debit-note mark-success compatibility slice.
- Continue with payroll `edit`, `bath/edit`, and `delete` as a separate low-risk DB write slice.

## 2026-06-12 08:29 +08:00 - merge-agent - Payroll Edit Batch-Edit Delete Compatibility

### Completed

- Continued in real multi-Agent mode: the main merge/coordinator implemented and accepted the slice using the payroll explorer report as the Java behavior reference.
- Added Java-compatible payroll write endpoints:
  - `POST /biz/bizpayroll/edit`
  - `POST /biz/bizpayroll/bath/edit`
  - `POST /biz/bizpayroll/delete`
- Matched the low-risk Java behavior boundary:
  - `edit` updates only Java `BizPayrollEditParam` fields.
  - `bath/edit` validates all ids before writing and rejects duplicate ids.
  - `delete` performs staged logical delete with `DELETE_FLAG = DELETED`.
- Preserved non-edit fields such as `POST_WAGE`, `YEAR_END_BONUS`, `PUBLIC_ACCOUNT`, `PRIVATE_ACCOUNT`, `REMARK`, `USER`, `ORG`, and `SALARY_TIME`.
- Kept payroll add, generate, import, export, and template download deferred.

### Modified Files

- `app/service/biz/BizPayrollService.php`
- `app/controller/biz/BizPayrollController.php`
- `route/app.php`
- `docs/api/biz-payroll-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\service\biz\BizPayrollService.php`: passed.
- `php -l app\controller\biz\BizPayrollController.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list`: passed and lists the three payroll write routes.
- Focused DB smoke with temporary payroll rows passed:
  - `edit` updated editable salary fields.
  - non-edit payroll fields were preserved.
  - `bath/edit` updated two rows.
  - missing-id batch edit failed before changing existing rows.
  - non-admin out-of-scope edit returned `403`.
  - `delete` set `DELETE_FLAG = DELETED` and hid the row from `detail`.
  - temporary smoke rows were physically cleaned up.

### Current Issues

- Payroll `add`, `generate/add`, `import`, `export`, and `downloadImportTemplate` remain deferred.
- Broader payroll calculation, Excel parsing/rendering, and workflow/business side effects remain out of scope.

### Next Plan

- Commit this payroll compatibility slice.
- Continue with the next low-risk backend slice after reviewing Java behavior through a scoped worker.

## 2026-06-12 09:01 +08:00 - merge-agent - Payroll Import Template Download Compatibility

### Completed

- Continued in real multi-Agent mode: payroll explorer confirmed that only `downloadImportTemplate` is safe for the next low-risk payroll slice.
- Added protected `GET /biz/bizpayroll/downloadImportTemplate`.
- Copied the original Java `userPayrollTemplate.xlsx` into the ThinkPHP project as a versioned non-public resource:
  - `app/resources/biz/payroll/userPayrollTemplate.xlsx`
- Added service/controller support to return a direct xlsx blob response instead of a JSON envelope.
- Kept payroll import, export, generate, and add deferred at this checkpoint.

Subsequent state on 2026-06-18: payroll export, generation, and focused Java-template import are now covered by narrow slices; payroll add remains deferred.

### Modified Files

- `app/resources/biz/payroll/userPayrollTemplate.xlsx`
- `app/service/biz/BizPayrollService.php`
- `app/controller/biz/BizPayrollController.php`
- `route/app.php`
- `docs/api/biz-payroll-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\service\biz\BizPayrollService.php`: passed.
- `php -l app\controller\biz\BizPayrollController.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list`: passed and lists `GET /biz/bizpayroll/downloadImportTemplate`.
- Service smoke verified filename, xlsx content type, 13427 bytes, `PK` file header, and SHA256 `4A98E66E74E8D310D6226A5F6DD60602652FC25FD6D0FB272281BBF19CD861B8`.
- Authenticated HTTP smoke on local backend `http://127.0.0.1:82` returned `200`, xlsx content type, `.xlsx` content disposition, 13427 bytes, matching SHA256, and `PK` file header.
- `biz_payroll` count remained stable before and after template download.

### Current Issues

- `POST /biz/bizpayroll/import` remains deferred because Java import parses EasyExcel, matches employees by name, allows partial success, writes payroll rows, and triggers data-change events.
- `GET /biz/bizpayroll/export` remains deferred because Java export uses EasyExcel multi-level headers and merged organization groups.
- `POST /biz/bizpayroll/generate/add` remains deferred because it aggregates users, sale projects, payment records, and leave records before writing payroll rows.
- `/biz/bizpayroll/add` remains intentionally absent because the Java controller does not expose that endpoint.

Subsequent state on 2026-06-16: `/biz/bizpayroll/export` is now covered as an authenticated CSV download; EasyExcel-style xlsx rendering and styling remain deferred.

### Next Plan

- Commit this payroll template download compatibility slice.
- The next candidate from explorer review is leave-application `edit/delete`, but it should be treated as a separate slice because it can affect workflow history and payroll-facing leave calculations.

## 2026-06-12 09:10 +08:00 - merge-agent - Leave Application Edit Delete Compatibility

### Completed

- Continued in real multi-Agent mode: a leave/vacation explorer recommended only `edit/delete` as a low-risk leave slice, and a follow-up read-only explorer checked local PHP write/delete patterns.
- Added Java-compatible leave-application write endpoints:
  - `POST /biz/bizleaveapplication/edit`
  - `POST /biz/bizleaveapplication/delete`
- `edit` now updates only Java `BizLeaveApplicationEditParam` fields and preserves create audit, tenant, object metadata, and delete state.
- `delete` now performs staged logical delete with full-batch validation before any write.
- Enhanced delete id parsing after sub-Agent review so `[{ id }]`, `{ ids: [{ id }] }`, scalar ids, and comma-separated ids are supported.
- Added tenant/data-scope write guards for admin-compatible users, applicant organization rows, current-applicant rows, and creator-owned rows.
- Kept leave `add`, workflow start/approval actions, annual-leave/vacation balance mutation, and payroll-facing recalculation deferred.

### Modified Files

- `app/service/biz/BizLeaveApplicationService.php`
- `app/controller/biz/BizLeaveApplicationController.php`
- `route/app.php`
- `docs/api/biz-leave-application-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\service\biz\BizLeaveApplicationService.php`: passed.
- `php -l app\controller\biz\BizLeaveApplicationController.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list`: passed and lists the two leave write routes.
- Focused DB smoke with temporary leave rows passed:
  - `edit` updated `processId`, `amount`, `remark`, `startTime`, and `endTime`.
  - create audit, tenant, and object metadata were preserved.
  - nested `{ ids: [{ id }] }` missing-id delete failed before changing existing rows.
  - non-admin out-of-scope edit returned `403`.
  - `delete` set `DELETE_FLAG = DELETED` and hid the row from `detail`.
  - payroll and vacation table counts stayed unchanged.
  - temporary smoke rows were physically cleaned up.

### Current Issues

- `POST /biz/bizleaveapplication/add` remains intentionally absent because Java controller comments it out.
- Workflow start/approve/reject/cancel, annual-leave/vacation deduction, and payroll-facing leave recalculation remain deferred.
- Browser smoke for the copied leave page should still be run after the frontend server is active.

### Next Plan

- Commit this leave compatibility slice.
- Continue with the next low-risk backend slice after checking Java behavior and frontend consumers through scoped sub-Agents.

## 2026-06-12 09:19 +08:00 - merge-agent - Sale Project Draft Save Compatibility

### Completed

- Continued in real multi-Agent mode: the main merge/coordinator selected a smaller `bizdraft` slice while a scoped explorer reviewed remaining low-risk candidates.
- Added Java-compatible sale-project draft save endpoint:
  - `POST /biz/bizdraft/saleproject/add`
- Matched Java `addOrEditSaleProjectDraft` behavior:
  - create by `targetId` when no active draft exists
  - set `CATEGORY = SALE_PROJECT_INIT`
  - preserve raw frontend `EXT_JSON`
  - update the existing active draft for the same `targetId` and tenant
- Kept real sale-project writes, workflow start/approval, and file upload/storage side effects deferred.

### Modified Files

- `app/service/biz/BizDraftService.php`
- `app/controller/biz/BizDraftController.php`
- `route/app.php`
- `docs/api/biz-draft-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/public-file-change-request.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\service\biz\BizDraftService.php`: passed.
- `php -l app\controller\biz\BizDraftController.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list`: passed and lists `POST /biz/bizdraft/saleproject/add`.
- Focused DB smoke with temporary draft rows passed:
  - first save created one active `biz_draft` row.
  - second save for the same `targetId` updated the existing active row, not a duplicate.
  - `detail` returned the updated raw `extJson`.
  - missing `targetId` returned a controlled `400`.
  - `biz_sale_project` row count stayed unchanged.
  - temporary smoke rows were physically cleaned up.

### Current Issues

- Real sale-project add/edit and process-start behavior remain deferred.
- File upload/storage side effects remain handled by existing file routes, not this draft endpoint.
- Browser smoke for the sale-project draft save button should still be run with the frontend active.

### Next Plan

- Commit this sale-project draft save compatibility slice.
- Next sub-Agent recommendation is `GET /gen/basic/previewGen` as a low-risk generator preview route, while keeping `execGenZip` and `execGenPro` deferred.

## 2026-06-12 09:35 +08:00 - merge-agent - Gen Basic Preview Compatibility

### Completed

- Continued in real multi-Agent mode: the main merge/coordinator picked up the prior scoped explorer recommendation for `GET /gen/basic/previewGen`.
- Added copied-frontend-compatible generator preview endpoint:
  - `GET /gen/basic/previewGen`
- Matched the Java preview result shape used by `snowy-admin-web/src/views/gen/preview.vue`:
  - `genBasicCodeSqlResultList`
  - `genBasicCodeFrontendResultList`
  - `genBasicCodeBackendResultList`
  - `genBasicCodeMobileResultList`
  - per-file `codeFileName`, `codeFileWithPathName`, and `codeFileContent`
- Kept generator add/edit/delete, ZIP generation, direct project output, and full Java Beetl template parity deferred.

### Modified Files

- `app/service/gen/BasicService.php`
- `app/controller/gen/BasicController.php`
- `route/app.php`
- `docs/api/gen-readonly-compat.md`
- `docs/api/gen-basic-metadata-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\service\gen\BasicService.php`: passed.
- `php -l app\controller\gen\BasicController.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list`: passed and lists `GET /gen/basic/previewGen`.
- `git diff --check`: passed with CRLF conversion warnings only.
- Focused service smoke through ThinkPHP bootstrap passed:
  - sample active `gen_basic` id `1809769215206965250` returned preview data.
  - SQL/frontend/backend buckets contained non-empty file objects with the expected fields.
  - missing id returned controlled `404`.
  - no-mobile generator rows returned `genBasicCodeMobileResultList = null`.
  - `gen_basic` and `gen_config` row counts stayed unchanged.
  - runtime file count stayed unchanged.

### Current Issues

- `/gen/basic/add`, `/edit`, and `/delete` remain deferred.
- `/gen/basic/execGenZip` and `/execGenPro` remain deferred because they write ZIP or project output.
- Preview content is safe PHP-rendered compatibility text, not full Java Beetl template parity.
- Browser smoke for the generator preview modal should still be run with the frontend active.

### Next Plan

- Commit this generator preview compatibility slice.
- Continue with the next low-risk frontend-visible gap after a scoped sub-Agent checks Java behavior and current frontend consumers.

## 2026-06-12 10:39 +08:00 - merge-agent - Lean Continuation Workflow Optimization

### Completed

- Added a documentation-only low-token workflow for future continuations.
- Optimized new-conversation startup from full long-log reads to a fast startup packet plus targeted module search.
- Preserved the quality bar by keeping risk-based gates for:
  - read-only routes
  - isolated writes
  - side-effect-heavy writes
  - frontend-visible changes
  - infrastructure/runtime work
- Documented multi-Agent fallback behavior when sub-Agent tools or quota are unavailable.

### Modified Files

- `AGENTS.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/autonomous-execution-rules.md`
- `docs/tasks/lean-continuation-workflow.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- Documentation-only change; no PHP or frontend runtime behavior changed.
- Process search consistency check passed: all new startup references point to `docs/tasks/lean-continuation-workflow.md`.
- `git diff --check`: passed with CRLF conversion warnings only.
- `git status --short --branch`: passed and showed only scoped documentation changes.

### Current Issues

- Sub-Agent quota was unavailable during the previous attempted SSE explorer task, so the new workflow explicitly records the single-conversation fallback.
- Existing large historical logs remain large by design; future turns should use targeted search and tails instead of full reads.

### Next Plan

- Commit this process optimization.
- Resume implementation with the next small slice, likely task SSE compatibility, using the lean startup and risk-based quality gates.

## 2026-06-12 13:38 +08:00 - merge-agent - Message SSE Process Notice Compatibility

### Completed

- Used the lean workflow single-conversation fallback because sub-Agent quota was unavailable in the prior SSE explorer attempt.
- Rechecked Java and frontend behavior before implementing:
  - Java `BizTaskController` does not expose `/biz/task/sse/stream`.
  - copied `bizTaskApi.sse()` exists but no active frontend caller was found.
  - the real layout EventSource is `/dev/message/createSseConnect`.
  - the layout task count refreshes on `FlushProcessNotice`.
- Updated the existing short-lived SSE compatibility stream to emit both:
  - `FlushMessageNotice`
  - `FlushProcessNotice`
- Kept standalone task SSE, workflow approve/reject, Redis pub/sub, long-lived push, and workflow side effects deferred.

### Modified Files

- `app/service/dev/MessageSseService.php`
- `docs/api/dev-message-sse-compat-plan.md`
- `docs/api/biz-workflow-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\service\dev\MessageSseService.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list`: passed and lists existing `GET /dev/message/createSseConnect`; no `/biz/task/sse/stream` route was added.
- `MessageSseService` response smoke: passed with `text/event-stream`, `code = 0`, `FlushMessageNotice`, and `FlushProcessNotice`.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- Full realtime push remains deferred.
- `/biz/task/approve`, `/biz/task/reject`, and workflow start/cancel remain deferred.
- `/biz/task/sse/stream` remains deferred because no Java route or active frontend caller was confirmed.

### Next Plan

- Run the targeted checks and commit this SSE compatibility refinement.
- Continue with the next low-risk frontend-visible gap after checking Java/frontend/PHP scope.

## 2026-06-15 11:45 +08:00 - merge-agent - Workflow Read-Only Row Compatibility And New-Conversation Handoff

### Completed

- Continued in real multi-Agent mode. Mencius performed a read-only workflow diff review while the main merge/coordinator implemented, verified, and documented the slice.
- Normalized workflow task/process page rows for the copied Vue workflow pages.
- Added S-Table/Java-style pagination aliases to workflow page responses.
- Preserved task-list `id` as the task id; process instance ids are available through `instanceId` and `processInstanceId`.
- Preserved process-list `id` as the process instance id.
- Guarded `useProcessParam` when `SYS_CONFIG` or `processConfigMap` is missing.
- Added a copy-paste new-chat starter prompt to `docs/tasks/new-conversation-bootstrap.md`.
- Updated workflow API docs, frontend joint-test notes, progress dashboard, implementation notes, and this status log.

### Modified Files

- `app/service/workflow/WorkflowQueryService.php`
- `snowy-admin-web/src/composables/useProcessParam/index.js`
- `docs/api/biz-workflow-readonly-compat.md`
- `docs/tasks/frontend-joint-test-workflow.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\service\workflow\WorkflowQueryService.php`: passed.
- `npm run build` under `snowy-admin-web`: passed, with existing warnings only.
- Authenticated API shape check passed for `/biz/task/page`, `/biz/task/history/page`, `/biz/process/page`, `/biz/process/all/page`, and `/biz/ccrecords/page`.
- Browser smoke passed for `/biz/biztask`, `/biz/biztask/historyTask`, `/biz/biztask/mystarttask`, `/biz/biztask/allprocess`, and `/biz/biztask/copytask`.
- Browser smoke observed no blocking console errors, no request failures, and no workflow approve/reject/cancel/start/edit/CC-delete/task-SSE requests.

### Current Issues

- Workflow approve/reject/start/cancel/edit, task SSE, vacation deductions, and workflow business side effects remain deferred.
- `useProcessParam` is guarded, but start/edit workflow forms still need their own write-flow design and browser smoke before workflow writes are enabled.
- Long-context continuation should now start from `docs/tasks/new-conversation-bootstrap.md` instead of relying on old chat history.

### Next Plan

- Commit this workflow read-only compatibility and handoff slice after final `git diff --check`.
- Continue with the next smallest safe slice from the progress dashboard/API gap map, using sub-Agents for scoped reconnaissance and keeping side-effect-heavy workflow writes deferred until a design is approved.

## 2026-06-15 12:00 +08:00 - merge-agent - Public Auth And Password-Recovery Deferred Wrapper Compatibility

### Completed

- Continued in real multi-Agent mode. Explorer Agent Beauvoir was assigned the bounded task of identifying the next safe gap while the main merge/coordinator inspected current wrapper and route coverage.
- Added controlled-deferred public compatibility routes for copied login/WebPush wrappers:
  - `GET /auth/b/getPhoneValidCode`
  - `POST /auth/b/subscription`
- Added controlled-deferred public compatibility routes for copied password-recovery wrappers:
  - `GET /sys/userCenter/findPasswordGetPhoneValidCode`
  - `GET /sys/userCenter/findPasswordGetEmailValidCode`
  - `POST /sys/userCenter/findPasswordByPhone`
  - `POST /sys/userCenter/findPasswordByEmail`
- Kept SMS, email, WebPush persistence, phone-code login, and password reset mutations deferred.
- Updated auth/user-center API docs, API gap map, progress dashboard, implementation log, plan, and this status log.

### Modified Files

- `app/controller/auth/AuthController.php`
- `app/controller/sys/UserCenterController.php`
- `route/app.php`
- `docs/api/auth-sm2-compatibility.md`
- `docs/api/user-center-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\auth\AuthController.php`: passed.
- `php -l app\controller\sys\UserCenterController.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list`: passed and lists all six new public wrapper routes.
- Public HTTP smoke against the running local backend without `/api` prefix returned HTTP 200 with `code=400` for all six new routes.

### Current Issues

- Real phone-code login, SMS/email verification, password recovery mutation, and WebPush subscription persistence remain deferred pending security/provider design.
- Remaining missing copied wrapper paths are still mostly side-effect-heavy workflow, finance, stock, generator execution, tenant mutation, or provider actions.

### Next Plan

- Continue with the next bounded low-risk route only after confirming it is not a provider, workflow transition, finance/stock mutation, generator execution, tenant mutation, or sale-project state write.

## 2026-06-15 12:15 +08:00 - merge-agent - Selector Pagination Shape Compatibility

### Completed

- Used Beauvoir's explorer recommendation for the next safe slice: selector pagination compatibility.
- Spawned Locke for a bounded Java/frontend expectation check while the main merge/coordinator implemented the read-only PHP service shape fix.
- Updated user and position selector services so existing system/business selector routes return Java-style paged payloads with `records`, `total`, `current`, `page`, `limit`, `size`, and `pages`.
- Preserved full sanitized user selector fields and full position row aliases while adding select aliases.
- Added support for copied frontend `size` pagination.
- Updated selector API docs, dashboard, implementation log, plan, and this status log.

### Modified Files

- `app/service/user/UserDirectoryService.php`
- `app/service/user/PositionService.php`
- `docs/api/frontend-readonly-selector-compat.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\service\user\UserDirectoryService.php`: passed.
- `php -l app\service\user\PositionService.php`: passed.
- Authenticated HTTP smoke using ignored `.env` login values passed for all eight selector routes: `/sys/user/positionSelector`, `/biz/user/positionSelector`, `/sys/position/positionSelector`, `/biz/position/positionSelector`, `/sys/user/userSelector`, `/biz/user/userSelector`, `/sys/org/userSelector`, and `/biz/org/userSelector`.

### Current Issues

- Browser form smoke for opening `/sys/user` and `/biz/user` selector dropdowns was not run in this slice.
- Business selector aliases still reuse system controllers; Java's stricter business data-scope and child-organization selector behavior remain future hardening work.
- Role selector pagination remains unchanged because this slice targeted the user/position selectors called by copied user forms.

### Next Plan

- Continue with browser-side selector smoke or another bounded read-only response-shape cleanup before any side-effect-heavy workflow, finance, stock, generator, tenant, or sale-project state write.

## 2026-06-15 14:00 +08:00 - merge-agent - Project Progress Acceleration Helper

### Completed

- Audited the workspace and confirmed `OA-ThinkPHP` on `refactor/thinkphp-main` is the active integration center.
- Confirmed old module worktrees are mostly behind `refactor/thinkphp-main`, so future work should start from the lean bootstrap and targeted searches rather than full old module logs.
- Added `scripts/project-progress.ps1` to print a compact read-only progress snapshot.
- Updated the new-conversation bootstrap and lean continuation workflow to prefer the helper script.

### Modified Files

- `scripts/project-progress.ps1`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/lean-continuation-workflow.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `.\scripts\project-progress.ps1 -SkipStatusTail`: passed.
- `.\scripts\project-progress.ps1 -DashboardLines 20 -StatusTail 20 -IncludeWorktreeSummary`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

### Current Issues

- This is a workflow acceleration helper only; it does not improve business feature coverage by itself.
- The integration branch is still locally ahead of `origin/refactor/thinkphp-main`; push remains a separate user decision.

### Next Plan

- Use `.\scripts\project-progress.ps1` at the start of future continuation turns.
- Continue with browser-side selector smoke or another bounded low-risk slice before side-effect-heavy workflow, finance, stock, generator, tenant, or sale-project state writes.

## 2026-06-15 14:20 +08:00 - merge-agent - Problem Optimization Log

### Completed

- Added `docs/tasks/problem-optimization-log.md` as the living project problem table.
- Seeded the table with current recurring workflow problems and mitigations.
- Updated `scripts/project-progress.ps1` to print the problem table in the fast startup snapshot.
- Updated bootstrap and lean workflow docs so future slices review and update the problem table.

### Modified Files

- `docs/tasks/problem-optimization-log.md`
- `scripts/project-progress.ps1`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/lean-continuation-workflow.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `.\scripts\project-progress.ps1 -SkipStatusTail -ProblemLines 50`: passed and printed the problem table.
- `git diff --check`: passed with existing line-ending warnings only.

### Current Issues

- The table is useful only if future slices update it when recurring problems or better mitigations appear.
- Existing local process changes remain uncommitted and the branch is still ahead of `origin/refactor/thinkphp-main`.

### Next Plan

- During every future slice, update `docs/tasks/problem-optimization-log.md` for repeated problems, blockers, confusing workflow, slow commands, avoidable mistakes, or test gaps.
- Continue with browser-side selector smoke or another bounded low-risk slice before side-effect-heavy workflow, finance, stock, generator, tenant, or sale-project state writes.

## 2026-06-15 14:35 +08:00 - merge-agent - Context Handoff Flow

### Completed

- Added `docs/tasks/context-handoff.md` to define when to ask the user for a new conversation.
- Added a persistent new conversation starter prompt that begins from `.\scripts\project-progress.ps1`.
- Updated the progress helper, bootstrap doc, and lean workflow doc with context handoff guidance.
- Added problem-log row `P-006` for context overload risk.

### Modified Files

- `docs/tasks/context-handoff.md`
- `scripts/project-progress.ps1`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/lean-continuation-workflow.md`
- `docs/tasks/problem-optimization-log.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `.\scripts\project-progress.ps1 -SkipStatusTail -ProblemLines 60`: passed and printed the context handoff pointer plus problem table.
- `git diff --check`: passed with existing line-ending warnings only.

### Current Issues

- Handoff quality still depends on keeping `STATUS.md` and the problem table current before asking for a new conversation.
- Existing local process changes remain uncommitted and the branch is still ahead of `origin/refactor/thinkphp-main`.

### Next Plan

- If this conversation becomes too large before the next broad/risky slice, ask the user to open a new conversation using `docs/tasks/context-handoff.md`.
- Continue with browser-side selector smoke or another bounded low-risk slice before side-effect-heavy workflow, finance, stock, generator, tenant, or sale-project state writes.

## 2026-06-15 15:05 +08:00 - merge-agent - Role Selector Pagination Shape Compatibility

### Completed

- Reviewed copied `roleSelectorPlus` and Java role selector behavior.
- Updated `/sys/user/roleSelector` and `/biz/user/roleSelector` to return Java-style paged payloads.
- Updated `/sys/role/roleSelector` to include the same pagination aliases and selector aliases.
- Added copied frontend `size` pagination support in `RoleService`.
- Updated selector API notes, dashboard, implementation log, plan, and problem table.

### Modified Files

- `app/service/user/UserDirectoryService.php`
- `app/service/auth/RoleService.php`
- `docs/api/frontend-readonly-selector-compat.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/problem-optimization-log.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\service\user\UserDirectoryService.php`: passed.
- `php -l app\service\auth\RoleService.php`: passed.
- `php think route:list | Select-String "roleSelector"`: passed and listed all three role selector routes.
- Initial DB-backed role selector smoke was blocked because MySQL was not running; recorded as `P-008`.
- Started the documented local runtime bundle and confirmed MySQL, Redis, and PHP FastCGI listening.
- DB-backed role selector shape smoke passed for system user role selector, business user role selector, and system role selector.

### Current Issues

- Browser smoke for opening the role grant modal in `/sys/user` and `/biz/user` was not run in this slice.
- Business role selector still reuses system controller behavior; Java's stricter business data-scope remains future hardening.
- Existing local changes remain uncommitted and the branch is still ahead of `origin/refactor/thinkphp-main`.

### Next Plan

- Run browser-side selector smoke for user/role grant dialogs when the frontend server is active, or continue with another bounded low-risk frontend-visible cleanup.
- Keep side-effect-heavy workflow, finance, stock, generator, tenant, and sale-project state writes deferred until a dedicated plan is written.

## 2026-06-15 15:25 +08:00 - merge-agent - Runtime Readiness Check

### Completed

- Added `scripts/runtime-ready.ps1` to check local MySQL, Redis, and PHP FastCGI ports before DB/HTTP smokes.
- Added `-CheckRuntime` to `scripts/project-progress.ps1`.
- Updated runtime docs, bootstrap docs, implementation log, plan, status, and problem table.

### Modified Files

- `scripts/runtime-ready.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/local-runtime-services.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/problem-optimization-log.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `.\scripts\runtime-ready.ps1`: passed after local runtime services were started.
- `.\scripts\project-progress.ps1 -CheckRuntime -SkipStatusTail -ProblemLines 20`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

### Current Issues

- The readiness helper checks ports only; it does not authenticate MySQL or Redis.
- Browser smoke for selector dialogs remains pending.
- Existing local changes remain uncommitted and the branch is still ahead of `origin/refactor/thinkphp-main`.

### Next Plan

- Use `.\scripts\runtime-ready.ps1` before DB-backed or HTTP smoke tests.
- Continue with browser-side selector smoke or another bounded low-risk frontend-visible cleanup.

## 2026-06-15 15:45 +08:00 - merge-agent - Web Readiness Check

### Completed

- Added `scripts/web-ready.ps1` to check local ThinkPHP backend and Vue frontend readiness before browser or authenticated HTTP smoke tests.
- Added `-CheckWeb` to `scripts/project-progress.ps1`.
- Updated frontend joint-test workflow, runtime docs, bootstrap docs, lean workflow, context handoff, dashboard, and problem table.
- Added problem-log row `P-009` for the gap between base runtime readiness and application-server readiness.

### Modified Files

- `scripts/web-ready.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/frontend-joint-test-workflow.md`
- `docs/tasks/local-runtime-services.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/lean-continuation-workflow.md`
- `docs/tasks/context-handoff.md`
- `docs/tasks/problem-optimization-log.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `.\scripts\web-ready.ps1`: passed after local ThinkPHP and Vite dev servers were started.
- `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -SkipStatusTail -ProblemLines 35`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

### Current Issues

- Backend port `82` and frontend port `83` were not listening at the start of this slice; they are now running locally for follow-up browser smoke.
- Vite took longer than a short 3-second HTTP check during cold startup, so `scripts/web-ready.ps1` now uses a longer default HTTP timeout.
- The copied frontend project does not include Playwright; recorded as problem-log row `P-010` before deferring selector-dialog browser automation to a dedicated browser-smoke slice.
- Existing local changes remain uncommitted and the branch is still ahead of `origin/refactor/thinkphp-main`.

### Next Plan

- Run `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -SkipStatusTail` before browser smoke.
- Start ThinkPHP backend on `82` and Vue frontend on `83` when selector-dialog browser smoke is needed.

## 2026-06-15 16:10 +08:00 - merge-agent - Role Selector HTTP Smoke

### Completed

- Confirmed copied `/sys/user` and `/biz/user` grant-role dialogs use `roleSelectorPlus`.
- Confirmed local frontend dependencies do not include Playwright, `@playwright/test`, or Puppeteer.
- Added `scripts/role-selector-http-smoke.ps1` to verify the authenticated read-only HTTP payloads used by the role selector dialog.
- Added the script to `scripts/project-progress.ps1` fast commands and the new-conversation bootstrap.
- Updated selector API docs, dashboard, plan, implementation log, status, and problem table.
- Updated problem-log row `P-010` with the no-browser-dependency fallback and added `P-011` for PowerShell JSON duplicate-key parsing.

### Modified Files

- `scripts/role-selector-http-smoke.ps1`
- `scripts/project-progress.ps1`
- `docs/api/frontend-readonly-selector-compat.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/problem-optimization-log.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `.\scripts\web-ready.ps1`: passed.
- `.\scripts\role-selector-http-smoke.ps1`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

### Current Issues

- This is an authenticated HTTP fallback, not a true browser click-through smoke of the role-grant modal.
- True browser automation still needs an external browser tool or a separate approved plan before adding Playwright/Puppeteer.
- Existing local changes remain uncommitted and the branch is still ahead of `origin/refactor/thinkphp-main`.

### Next Plan

- Use `.\scripts\role-selector-http-smoke.ps1` after selector-shape changes.
- Keep true `/sys/user` and `/biz/user` grant-dialog browser smoke as a separate browser-automation slice.

## 2026-06-15 16:25 +08:00 - merge-agent - Case-Safe JSON Smoke Helper

### Completed

- Confirmed local PowerShell is 5.1 and lacks `ConvertFrom-Json -AsHashtable`.
- Added `scripts/json-read.js` to read JSON paths through Node with case-sensitive key handling.
- Added the helper to `scripts/project-progress.ps1` fast commands.
- Updated bootstrap docs and problem row `P-011` with the concrete mitigation.

### Modified Files

- `scripts/json-read.js`
- `scripts/project-progress.ps1`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/problem-optimization-log.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- Case-sensitive sample read for `data.records.0.id`: passed and returned `lower`.
- Case-sensitive sample read for `data.records.0.ID`: passed and returned `upper`.
- `.\scripts\role-selector-http-smoke.ps1`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

### Current Issues

- Existing larger HTTP smoke scripts still use `ConvertFrom-Json`; they are fine for current endpoints but should use `json-read.js` when mixed-case duplicate aliases are possible.
- Existing local changes remain uncommitted and the branch is still ahead of `origin/refactor/thinkphp-main`.

### Next Plan

- Use `node .\scripts\json-read.js <path>` in new HTTP smoke scripts when parsing endpoints that may include legacy uppercase plus frontend lowercase aliases.
- Keep true role-selector browser click-through smoke as a separate browser-automation slice.

## 2026-06-15 16:40 +08:00 - merge-agent - Project Preflight Bundle

### Completed

- Added `scripts/project-preflight.ps1` as a one-command local preflight bundle.
- Default preflight runs Git status, runtime readiness, web readiness, role-selector HTTP smoke, and `git diff --check`.
- Added skip switches for unavailable layers: `-SkipRuntime`, `-SkipWeb`, `-SkipRoleSelector`, and `-SkipDiffCheck`.
- Updated project progress fast commands, bootstrap docs, lean workflow, runtime docs, dashboard, implementation log, plan, and problem table.
- Added problem-log row `P-012` for fragmented verification commands.

### Modified Files

- `scripts/project-preflight.ps1`
- `scripts/project-progress.ps1`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/lean-continuation-workflow.md`
- `docs/tasks/local-runtime-services.md`
- `docs/tasks/problem-optimization-log.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `.\scripts\project-preflight.ps1`: passed.
- `.\scripts\project-preflight.ps1 -SkipWeb -SkipRoleSelector -SkipDiffCheck`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

### Current Issues

- Preflight is a local readiness and focused-smoke bundle, not a replacement for module-specific regression tests.
- Existing local changes remain uncommitted and the branch is still ahead of `origin/refactor/thinkphp-main`.

### Next Plan

- Use `.\scripts\project-preflight.ps1` before and after small frontend-visible/API slices when local services are available.
- Use skip switches for backend-only work or when the frontend server is intentionally offline.

## 2026-06-15 12:51 +08:00 - merge-agent - New Conversation Fast Handoff

### Completed

- Updated the long-context handoff starter to begin from `F:\AI\projects\testJava\OA-ThinkPHP`.
- Made `.\scripts\project-progress.ps1 -SkipStatusTail` the first lightweight context command for new conversations.
- Made `.\scripts\project-preflight.ps1` the default next command when local runtime, backend, and frontend services are expected.
- Synchronized the same startup sequence across context handoff, new-conversation bootstrap, and lean continuation workflow docs.

### Modified Files

- `docs/tasks/context-handoff.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/lean-continuation-workflow.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `.\scripts\project-preflight.ps1`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

### Current Issues

- Existing local changes remain uncommitted and the branch is still ahead of `origin/refactor/thinkphp-main`.
- This was a documentation/process slice only; it does not replace module-specific regression tests.

### Next Plan

- For the next code slice, start with `.\scripts\project-progress.ps1 -SkipStatusTail`, then use `.\scripts\project-preflight.ps1` when local services are available.
- If the next slice is broad, side-effect-heavy, or cross-module, ask the user to open a new conversation using `docs/tasks/context-handoff.md` before starting it.

## 2026-06-15 12:51 +08:00 - merge-agent - Explicit Commit Guardrail

### Completed

- Updated active startup and handoff docs so future Agents do not commit unless the user explicitly asks or the main merge/coordinator explicitly approves the completed slice.
- Removed automatic per-slice commit wording from the active multi-Agent and implementation-loop guidance.
- Added problem-log row `P-013` for commit-workflow ambiguity.

### Modified Files

- `docs/tasks/context-handoff.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/lean-continuation-workflow.md`
- `docs/tasks/problem-optimization-log.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `.\scripts\project-preflight.ps1`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

### Current Issues

- Historical `STATUS.md` entries still mention older commit plans; they are left as history and should not be copied into active continuation prompts.
- Existing local changes remain uncommitted and the branch is still ahead of `origin/refactor/thinkphp-main`.

### Next Plan

- Continue using `docs/tasks/context-handoff.md` for new conversations.
- Keep commit commands out of future slices unless the user explicitly asks for one.

## 2026-06-15 12:51 +08:00 - merge-agent - Progress Snapshot Commit Guardrail

### Completed

- Added a `Commit Guardrail` section to `scripts/project-progress.ps1`.
- Updated problem row `P-013` so the mitigation includes the startup snapshot, not only docs.
- Updated the problem-log review checklist to use the lighter `.\scripts\project-progress.ps1 -SkipStatusTail` command.

### Modified Files

- `scripts/project-progress.ps1`
- `docs/tasks/problem-optimization-log.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `.\scripts\project-progress.ps1 -SkipStatusTail -ProblemLines 20`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

### Current Issues

- Existing local changes remain uncommitted and the branch is still ahead of `origin/refactor/thinkphp-main`.
- This was a process/script visibility slice only; it does not replace module-specific tests.

### Next Plan

- Use the progress snapshot as the first command in future continuations so the commit guardrail is visible immediately.
- For any broader business/API/frontend work, consider starting a new conversation from `docs/tasks/context-handoff.md` first.

## 2026-06-15 12:51 +08:00 - merge-agent - Recent Problem Row Visibility

### Completed

- Updated `scripts/project-progress.ps1` to print a `Recent Problem Rows` section after the problem-log head.
- Added problem row `P-014` for the issue where shortened startup output can hide the newest mitigations.

### Modified Files

- `scripts/project-progress.ps1`
- `docs/tasks/problem-optimization-log.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `.\scripts\project-progress.ps1 -SkipStatusTail -ProblemLines 20`: passed and printed recent problem rows including `P-014`.
- `git diff --check`: passed with existing line-ending warnings only.

### Current Issues

- Existing local changes remain uncommitted and the branch is still ahead of `origin/refactor/thinkphp-main`.
- This was a process/script visibility slice only; it does not replace module-specific tests.

### Next Plan

- Use the recent problem rows in the startup snapshot to catch the newest mitigations before editing.
- If continuing into broader business/API/frontend work, start a new conversation from `docs/tasks/context-handoff.md`.

## 2026-06-15 12:51 +08:00 - merge-agent - Lean Progress Snapshot Mode

### Completed

- Added `-Lean` to `scripts/project-progress.ps1`.
- Lean mode shortens dashboard/problem-log head output and skips the `STATUS.md` tail.
- Lean mode keeps recent problem rows, context handoff, commit guardrail, and fast commands visible.
- Updated fast command examples to prefer `-Lean` for runtime and web readiness snapshots.
- Updated context handoff, new conversation bootstrap, and lean continuation docs to use `.\scripts\project-progress.ps1 -Lean` as the first startup command.
- Added problem row `P-015` for startup context size.

### Modified Files

- `scripts/project-progress.ps1`
- `docs/tasks/context-handoff.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/lean-continuation-workflow.md`
- `docs/tasks/problem-optimization-log.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `.\scripts\project-progress.ps1 -Lean`: passed.
- `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

### Current Issues

- Existing local changes remain uncommitted and the branch is still ahead of `origin/refactor/thinkphp-main`.
- This was a process/script slice only; it does not replace module-specific tests.

### Next Plan

- Use `.\scripts\project-progress.ps1 -Lean` as the default first command in any new continuation.
- Use longer line-count options only when the lean snapshot does not contain enough detail.

## 2026-06-15 12:51 +08:00 - merge-agent - Lean Dashboard Summary

### Completed

- Added `Show-DashboardLean` to `scripts/project-progress.ps1`.
- Lean mode now prints `Progress Dashboard Summary` instead of the raw dashboard head.
- The summary keeps update time, completion estimates, compact key frontend route metrics, current branch, and truncated recent verification notes.
- Added problem row `P-016` for wide dashboard rows consuming startup context.

### Modified Files

- `scripts/project-progress.ps1`
- `docs/tasks/problem-optimization-log.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `.\scripts\project-progress.ps1 -Lean`: passed and printed compact `Progress Dashboard Summary` metrics without raw wide dashboard table notes.
- `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

### Current Issues

- Existing local changes remain uncommitted and the branch is still ahead of `origin/refactor/thinkphp-main`.
- This was a process/script slice only; it does not replace module-specific tests.

### Next Plan

- Keep `.\scripts\project-progress.ps1 -Lean` as the default first command.
- Add targeted dashboard summary patterns only when future slices repeatedly need a metric that is absent from the lean summary.

## 2026-06-15 12:51 +08:00 - merge-agent - Lean Problem Summary

### Completed

- Added `Convert-ProblemLine` and `Show-ProblemsLean` to `scripts/project-progress.ps1`.
- Lean mode now prints the problem-log path, open problem count, and compact recent `ID | Area | Status | Problem` rows.
- Non-lean mode still supports the full problem-log head and full recent problem rows.
- Added problem row `P-017` for noisy full problem-table rows in startup output.

### Modified Files

- `scripts/project-progress.ps1`
- `docs/tasks/problem-optimization-log.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `.\scripts\project-progress.ps1 -Lean`: passed and printed compact problem summaries including `P-017`.
- `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

### Current Issues

- Existing local changes remain uncommitted and the branch is still ahead of `origin/refactor/thinkphp-main`.
- This was a process/script slice only; it does not replace module-specific tests.

### Next Plan

- Keep `.\scripts\project-progress.ps1 -Lean` as the default first command for new conversations.
- Use full problem-log output only when investigating a specific row in detail.

## 2026-06-15 12:51 +08:00 - merge-agent - Problem Table Pipe Guard

### Completed

- Fixed problem row `P-017` so it no longer contains raw vertical bars inside a Markdown table cell.
- Hardened `Convert-ProblemLine` to trim non-empty columns and read status from the final column.
- Added problem row `P-018` for raw vertical bars breaking table/script parsing.

### Modified Files

- `scripts/project-progress.ps1`
- `docs/tasks/problem-optimization-log.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `.\scripts\project-progress.ps1 -Lean`: passed and showed `P-017` and `P-018` with `Mitigated` status.
- `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

### Current Issues

- Existing local changes remain uncommitted and the branch is still ahead of `origin/refactor/thinkphp-main`.
- This was a process/script slice only; it does not replace module-specific tests.

### Next Plan

- Avoid raw vertical bars in future problem-log table cells.
- Keep `.\scripts\project-progress.ps1 -Lean` as the first command for future continuation.

## 2026-06-15 16:55 +08:00 - merge-agent - Third-Party Auth Deferred Wrappers

### Completed

- Reviewed the copied `auth/third` frontend wrappers and Java `AuthThirdController` public render/callback routes.
- Added public controlled-deferred `GET /auth/third/render` and `GET /auth/third/callback` wrappers.
- Kept `GET /auth/third/page` protected by `AuthMiddleware`.
- Updated auth-third docs, API gap map route counts, progress dashboard, public route-change notes, plan log, and implementation log.

### Modified Files

- `app/controller/auth/ThirdController.php`
- `route/app.php`
- `docs/api/auth-third-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/public-file-change-request.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\auth\ThirdController.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list | Select-String "auth/third"`: passed.
- Public HTTP smoke for `/auth/third/render` and `/auth/third/callback`: passed with business `code = 400`.
- `.\scripts\project-preflight.ps1`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

### Current Issues

- The routes intentionally do not implement OAuth redirects, callback exchange, token issuance, user binding, or provider configuration.
- Existing local changes remain uncommitted and the branch is still ahead of `origin/refactor/thinkphp-main`.

### Next Plan

- Continue another low-risk frontend-visible gap, preferably a controlled-deferred provider action or isolated read compatibility slice.
- Keep workflow, finance, inventory, sale-project state transitions, provider credentials, and final online data sync deferred until dedicated plans are approved.

## 2026-06-15 17:10 +08:00 - merge-agent - SMS Provider Deferred Wrappers

### Completed

- Reviewed copied frontend SMS provider-send wrappers and Java `DevSmsController` protected send routes.
- Added protected controlled-deferred wrappers for `POST /dev/sms/sendAliyun`, `/dev/sms/sendTencent`, and `/dev/sms/sendXiaonuo`.
- Kept the routes behind `AuthMiddleware` and returned explicit deferred business responses for authenticated calls.
- Updated dev email/SMS docs, API gap map route counts, progress dashboard, public route-change notes, plan log, and implementation log.

### Modified Files

- `app/controller/dev/SmsController.php`
- `route/app.php`
- `docs/api/dev-email-sms-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/public-file-change-request.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\dev\SmsController.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list | Select-String "dev/sms"`: passed.
- Authenticated HTTP smoke for `/dev/sms/sendAliyun`, `/dev/sms/sendTencent`, and `/dev/sms/sendXiaonuo`: passed with business `code = 400`.
- No-token HTTP smoke for the same three routes: passed with business `code = 401`.
- `.\scripts\project-preflight.ps1`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

### Current Issues

- Real SMS provider sends remain deferred; this slice does not read provider credentials, load SDKs, call external SMS services, or write send records.
- Existing local changes remain uncommitted and the branch is still ahead of `origin/refactor/thinkphp-main`.

### Next Plan

- Continue another controlled-deferred provider action or isolated read compatibility slice.
- Keep email provider sends, job scheduler execution, finance, inventory, workflow, sale-project state transitions, and final online data sync deferred until dedicated plans are approved.

## 2026-06-15 17:35 +08:00 - merge-agent - User Display Smoke Coverage

### Completed

- Reviewed existing sys/biz user display alias behavior for copied frontend user pages.
- Added `scripts/user-display-http-smoke.ps1` for authenticated read-only checks of user page/detail/list/detail/userSelector payloads.
- Added the user-display smoke to `scripts/project-preflight.ps1` with `-SkipUserDisplay`.
- Updated display/selector docs, API gap map next order, progress dashboard, plan log, and implementation log.

### Modified Files

- `scripts/user-display-http-smoke.ps1`
- `scripts/project-preflight.ps1`
- `docs/api/sys-user-org-display-compat.md`
- `docs/api/frontend-readonly-selector-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `.\scripts\user-display-http-smoke.ps1`: passed.
- Verified `/sys/user/page`, `/biz/user/page`, `/sys/user/detail`, `/sys/user/list/detail`, `/sys/user/userSelector`, and `/biz/user/userSelector`.
- `.\scripts\project-preflight.ps1`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

### Current Issues

- This is verification coverage only; no frontend browser click-through was added in this slice.
- Real email and SMS sending remain deferred to the final provider phase, with Email before SMS.

### Next Plan

- Continue with safe read-only `biz/saleproject` and `biz/customer` compatibility checks in small slices.
- Keep workflow writes, finance/inventory side effects, job scheduler execution, real provider sends, and final online data sync deferred.

## 2026-06-15 18:00 +08:00 - merge-agent - Business Read Smoke Coverage

### Completed

- Reviewed existing customer and sale-project read routes and copied frontend wrappers.
- Added `scripts/business-read-http-smoke.ps1` for authenticated read-only checks using existing active local customer and sale-project rows.
- Covered customer page/detail/detail-list and sale-project page/case/operation/public/detail/list-detail/product/cost/cost-details reads.
- Added the business-read smoke to `scripts/project-preflight.ps1` with `-SkipBizRead`.
- Updated customer/sale-project docs, API gap map next order, progress dashboard, plan log, and implementation log.

### Modified Files

- `scripts/business-read-http-smoke.ps1`
- `scripts/project-preflight.ps1`
- `docs/api/biz-customer-readonly.md`
- `docs/api/biz-saleproject-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\biz\CustomerController.php`: passed.
- `php -l app\controller\biz\SaleProjectController.php`: passed.
- `php -l app\service\biz\CustomerService.php`: passed.
- `php -l app\service\biz\SaleProjectService.php`: passed.
- `.\scripts\business-read-http-smoke.ps1`: passed.
- `.\scripts\project-preflight.ps1`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

### Current Issues

- This is read-contract coverage only; no customer writes, sale-project state writes, workflow actions, finance effects, stock effects, or browser click-through were added.
- Real Email remains deferred until the final provider phase before real SMS.

### Next Plan

- Continue remaining safe read-only/detail-consumer checks or selector coverage before any side-effect-heavy business writes.
- Keep workflow writes, finance/inventory side effects, job scheduler execution, real provider sends, and final online data sync deferred.

## 2026-06-15 18:35 +08:00 - merge-agent - Parallel Coordination And Directory Alias Smoke

### Completed

- Spawned three read-only explorer agents for directory alias smoke scouting, remaining read-only/detail-consumer scouting, and workflow boundary reconnaissance.
- Added `docs/tasks/parallel-execution-plan.md` with safe parallel tracks, serial shared files, deferred high-risk modules, worker prompt templates, and the current recommended queue.
- Linked the parallel plan from context handoff, new conversation bootstrap, and lean continuation workflow docs.
- Fixed `OrgService::pagination()` so `/biz/org/page?current=1&size=1` honors copied frontend `size` pagination.
- Added `scripts/directory-alias-http-smoke.ps1` for authenticated read-only biz org/position/dict page/tree/selector checks.
- Added the directory alias smoke to `scripts/project-preflight.ps1` with `-SkipDirectoryAlias`.
- Updated directory alias docs, selector docs, API gap map, progress dashboard, plan log, and implementation log.

### Modified Files

- `docs/tasks/parallel-execution-plan.md`
- `docs/tasks/context-handoff.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/lean-continuation-workflow.md`
- `app/service/user/OrgService.php`
- `scripts/directory-alias-http-smoke.ps1`
- `scripts/project-preflight.ps1`
- `docs/api/biz-directory-alias-readonly.md`
- `docs/api/frontend-readonly-selector-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\service\user\OrgService.php`: passed.
- `.\scripts\directory-alias-http-smoke.ps1`: passed.
- `.\scripts\project-preflight.ps1`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

### Current Issues

- Parallel workers should remain read-only unless the coordinator assigns disjoint file ownership.
- Workflow approve/reject/start/cancel, job execution, finance/inventory side effects, cloud storage, and provider sends remain deferred.
- Real Email remains deferred until the final provider phase before real SMS.

### Next Plan

- Next candidate slice: customer/sale-project follow-up detail-consumer smoke.
- Alternative safe slice: no-write workflow HTTP smoke for list/query endpoints only.

## 2026-06-15 19:05 +08:00 - merge-agent - Follow-Up Read Smoke Coverage

### Completed

- Reviewed customer follow-up and sale-project follow-up read controllers, services, routes, frontend wrappers, and docs.
- Extended `scripts/business-read-http-smoke.ps1` to verify customer follow-up page/detail and sale-project follow-up page/detail payloads with existing active local rows when available.
- Kept follow-up detail checks conditional so the smoke remains stable if local sample follow-up rows are absent.
- Updated `scripts/json-read.js` to strip a leading BOM before JSON parsing.
- Updated customer and sale-project follow-up docs, API gap map next order, progress dashboard, problem log, bootstrap docs, plan log, and implementation log.

### Modified Files

- `scripts/business-read-http-smoke.ps1`
- `scripts/json-read.js`
- `docs/api/biz-customer-readonly.md`
- `docs/api/biz-saleproject-followup-readonly.md`
- `docs/tasks/parallel-execution-plan.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/problem-optimization-log.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\biz\CustomerFollowUpController.php`: passed.
- `php -l app\controller\biz\SaleProjectFollowUpController.php`: passed.
- `php -l app\service\biz\CustomerFollowUpService.php`: passed.
- `php -l app\service\biz\SaleProjectFollowUpService.php`: passed.
- `.\scripts\business-read-http-smoke.ps1`: passed.
- `.\scripts\project-preflight.ps1`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

### Current Issues

- This is read-contract coverage only; no follow-up writes, attachment cleanup, notifications, workflow actions, finance effects, inventory effects, or browser click-through were added.
- Real Email remains deferred until the final provider phase before real SMS.

### Next Plan

- Next candidate slice: no-write workflow HTTP smoke for list/query endpoints only.
- Alternative safe slice: sale-project billing nested read smoke excluding invoicing complete, stock, settlement, and file cleanup.
## 2026-06-15 22:45 +08:00 - merge-agent - Workflow Read Smoke Coverage

### Completed

- Reviewed workflow task/process/CC read routes and services.
- Added `scripts/workflow-read-http-smoke.ps1` for authenticated no-write HTTP smoke coverage.
- Covered task count/list/page/history, process page/all-page/query/query-list/detail/variable/file-list, project runtime query when a local sample `projectId` exists, and CC page/detail when a current-user CC sample exists.
- Added workflow-read smoke to `scripts/project-preflight.ps1` with `-SkipWorkflowRead`.
- Updated workflow docs, API gap map, parallel plan, progress dashboard, bootstrap docs, problem log, plan log, and implementation log.

### Modified Files

- `scripts/workflow-read-http-smoke.ps1`
- `scripts/project-preflight.ps1`
- `docs/api/biz-workflow-readonly-compat.md`
- `docs/tasks/parallel-execution-plan.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/problem-optimization-log.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\biz\TaskController.php`: passed.
- `php -l app\controller\biz\ProcessController.php`: passed.
- `php -l app\controller\biz\CcRecordsController.php`: passed.
- `php -l app\service\workflow\WorkflowQueryService.php`: passed.
- `php -l app\service\workflow\WorkflowVariableService.php`: passed.
- `php -l app\service\biz\CcRecordsService.php`: passed.
- `.\scripts\workflow-read-http-smoke.ps1`: passed, with task runtime detail and CC detail skipped because the local smoke account currently has no pending task or current-user CC sample.
- `.\scripts\project-preflight.ps1`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

### Current Issues

- Empty-filter `/biz/process/query/list` can load all historic workflows and variables on a large local dataset. The new smoke uses a bounded missing `processKeys` filter; a service-level pagination or required-filter decision should be a dedicated follow-up.
- Workflow approve/reject/start/cancel, task SSE, CC generation/delete side effects, finance/inventory side effects, job execution, real Email/SMS provider sends, and final online data sync remain deferred.

### Next Plan

- Commit this slice as `test: add workflow read smoke`.
- Next candidate slice: sale-project billing nested read smoke, excluding invoicing complete, stock, settlement, and file cleanup.

## 2026-06-15 23:20 +08:00 - merge-agent - Sale-Project Billing Nested Read Smoke Coverage

### Completed

- Reviewed sale-project billing routes, controllers, `SaleProjectBillingService`, and existing compatibility docs.
- Extended `scripts/business-read-http-smoke.ps1` to verify billing-adjacent nested read contracts.
- Covered invoicing page/detail/customer, delivery invoice page/list, invoice-item page and invoice-filtered page, and reissue-order `list/query`.
- Verified nested response shapes for invoice list (`bizSaleProjectInvoice`, `invoiceItems`) and reissue list (`order`, `productItemList`).
- Updated billing/read docs, API gap map, parallel plan, progress dashboard, plan log, and implementation log.

### Modified Files

- `scripts/business-read-http-smoke.ps1`
- `docs/api/biz-saleproject-billing-readonly.md`
- `docs/api/biz-saleproject-readonly.md`
- `docs/api/sale-project-invoice-item-readonly.md`
- `docs/tasks/parallel-execution-plan.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\biz\SaleProjectInvoicingController.php`: passed.
- `php -l app\controller\biz\SaleProjectInvoiceController.php`: passed.
- `php -l app\controller\biz\SaleProjectInvoiceItemController.php`: passed.
- `php -l app\controller\biz\SaleProjectReissueOrderController.php`: passed.
- `php -l app\service\biz\SaleProjectBillingService.php`: passed.
- `.\scripts\business-read-http-smoke.ps1`: passed.
- `.\scripts\project-preflight.ps1`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

### Current Issues

- This is read-contract coverage only; no billing writes, invoicing complete, stock, settlement, finance, workflow, sale-project state write, file cleanup, or browser click-through was added.
- Real Email remains deferred until the final provider phase before real SMS.

### Next Plan

- Commit this slice as `test: extend sale project billing smoke`.
- Next candidate slice: sale-project product/package relation read smoke, excluding product-info writes and relation mark writes.
## 2026-06-15 23:45 +08:00 - merge-agent - Sale-Project Product Relation Read Smoke Coverage

### Completed

- Reviewed product-info and product-item relation read controllers, services, routes, and existing compatibility docs.
- Extended `scripts/business-read-http-smoke.ps1` to verify product/package info and combo-product child relation read contracts.
- Covered `/biz/saleprojectproductinfo/page`, `/detail`, bounded `/list?targetIds=...`, and `/biz/saleprojectproductitemrelation/list`.
- Verified product-info display fields and relation fields including `productId`, `extJson`, product aliases, project aliases, and child product attributes.
- Updated product-info/relation docs, API gap map, parallel plan, progress dashboard, plan log, and implementation log.

### Modified Files

- `scripts/business-read-http-smoke.ps1`
- `docs/api/biz-saleproject-product-info-readonly.md`
- `docs/api/biz-saleproject-product-item-relation-readonly.md`
- `docs/tasks/parallel-execution-plan.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\biz\SaleProjectProductInfoController.php`: passed.
- `php -l app\controller\biz\SaleProjectProductItemRelationController.php`: passed.
- `php -l app\service\biz\SaleProjectProductInfoService.php`: passed.
- `php -l app\service\biz\SaleProjectProductItemRelationService.php`: passed.
- `.\scripts\business-read-http-smoke.ps1`: passed.
- `.\scripts\project-preflight.ps1`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

### Current Issues

- This is read-contract coverage only; no product-info writes, relation mark edits, product-item mark edits, inventory, delivery, finance, workflow, sale-project state write, file cleanup, or browser click-through was added.
- Real Email remains deferred until the final provider phase before real SMS.

### Next Plan

- Commit this slice as `test: extend sale project product smoke`.
- Next candidate: targeted browser smoke after selecting a concrete visible page and forbidden request pattern, or a dedicated workflow `query/list` performance/compatibility slice.

## 2026-06-15 14:53 +08:00 - workflow-agent - Process Query List Guard

### Completed

- Compared PHP workflow `query/list` behavior with Java `BizBaseProcessQueryParam` and confirmed Java requires non-empty `processKeyList` plus `attribute`.
- Updated `ProcessController` so `query/list`, `variable`, and `fileList` accept JSON body payloads from copied frontend callers while retaining existing query/form fallbacks.
- Updated `WorkflowQueryService::queryProcessList()` to return controlled `400` responses for missing process keys or attributes instead of scanning all historic processes.
- Updated `scripts/workflow-read-http-smoke.ps1` to send JSON via a temporary file and assert both filtered `query/list` success and empty-filter guard behavior.
- Updated workflow docs, problem log, API gap map, parallel plan, progress dashboard, plan log, and implementation log.

### Modified Files

- `app/controller/biz/ProcessController.php`
- `app/service/workflow/WorkflowQueryService.php`
- `scripts/workflow-read-http-smoke.ps1`
- `docs/api/biz-workflow-readonly-compat.md`
- `docs/tasks/problem-optimization-log.md`
- `docs/tasks/parallel-execution-plan.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\biz\ProcessController.php`: passed.
- `php -l app\service\workflow\WorkflowQueryService.php`: passed.
- `.\scripts\workflow-read-http-smoke.ps1`: passed, with task runtime detail and CC detail skipped because the local smoke account currently has no pending task or current-user CC sample.
- `.\scripts\project-preflight.ps1`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

### Current Issues

- This only hardens read-query behavior; workflow approve/reject/start/cancel, task SSE, Java delegate side effects, finance/inventory side effects, provider sends, cloud cleanup, and final data sync remain deferred.
- Real Email remains deferred until the final provider phase before real SMS.

### Next Plan

- Commit this slice as `fix: guard workflow query list`.
- Next candidate: targeted browser smoke after selecting a concrete visible page and forbidden request pattern, or cloud storage cleanup/provider planning after configuration policy is confirmed.

## 2026-06-15 14:55 +08:00 - test-agent - PowerShell JSON Body Smoke Hardening

### Completed

- Reviewed smoke helper usage after the workflow query-list slice exposed PowerShell/curl inline JSON quote loss.
- Updated `scripts/business-read-http-smoke.ps1` so POST JSON bodies are written to a temporary file and sent with `curl.exe --data-binary @file`.
- Added `finally` cleanup for the temporary JSON body file.
- Added problem-log row `P-020` and updated plan/implementation/status logs.

### Modified Files

- `scripts/business-read-http-smoke.ps1`
- `docs/tasks/problem-optimization-log.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `.\scripts\business-read-http-smoke.ps1`: passed.
- `.\scripts\project-preflight.ps1`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

### Current Issues

- This is smoke reliability only; it does not change any business endpoint behavior.
- Browser smoke still needs a concrete visible page and forbidden request pattern before running.

### Next Plan

- Commit this slice as `test: harden smoke json posts`.
- Next candidate: targeted browser smoke after selecting a concrete visible page and forbidden request pattern, or cloud storage cleanup/provider planning after configuration policy is confirmed.

## 2026-06-15 15:09 +08:00 - workflow-agent - Workflow Detail Browser Smoke Compatibility

### Completed

- Ran targeted workflow detail browser smoke with system Chrome through Playwright, using a temporary local token and temporary workflow route cache.
- `/biz/biztask/allprocess` rendered without 404 but had no current local rows.
- `/biz/biztask/mystarttask` rendered 3 rows; clicking the first row opened the process detail drawer.
- Fixed `/biz/process/variable` to return Java-compatible variable rows expected by copied workflow detail forms.
- Fixed `useProcessParam()` to tolerate missing or partial cached process config.
- Hardened project-return workflow detail against missing local related sale-project/warehouse rows.
- Updated workflow docs, problem log, progress dashboard, plan log, and implementation log.

### Modified Files

- `app/controller/biz/ProcessController.php`
- `snowy-admin-web/src/composables/useProcessParam/index.js`
- `snowy-admin-web/src/views/biz/bizprocess/processDetails/infoForm/project/projectReturnInfo.vue`
- `docs/api/biz-workflow-readonly-compat.md`
- `docs/tasks/problem-optimization-log.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\biz\ProcessController.php`: passed.
- `.\scripts\workflow-read-http-smoke.ps1`: passed, with task runtime detail and CC detail skipped because the local smoke account currently has no pending task or current-user CC sample.
- Browser smoke: passed; observed process detail/fileList/variable reads plus sale-project detail and warehouses list reads, with no console errors, failed API requests, or forbidden write/upload/delete requests.
- `npm run build` in `snowy-admin-web`: passed with existing Vite/Browserslist/chunk-size warnings.
- `.\scripts\project-preflight.ps1`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

### Current Issues

- Workflow write paths remain deferred: approve, reject, start, cancel, task SSE, and Java delegate side effects were not implemented or called.
- Real Email remains deferred until the final provider phase before real SMS.

### Next Plan

- Commit this slice as `fix: stabilize workflow detail reads`.
- Next candidate: cloud storage cleanup/provider planning after configuration policy is confirmed, or another targeted browser smoke only after selecting a concrete page and forbidden request pattern.

## 2026-06-16 08:52 +08:00 - test-agent/frontend-agent - Sale-Project DealProjectList Browser Smoke

### Completed

- Started the local runtime bundle, ThinkPHP backend on port `82`, and Vue frontend on port `83`.
- Ran `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean`; runtime and backend were ready, Vue needed a cold-start wait before binding `83`.
- Ran full backend/read preflight with `.\scripts\project-preflight.ps1 -SkipWeb`; all runtime, frontend API method, authenticated HTTP read, SSE, and whitespace checks passed.
- Ran a targeted headless Chrome CDP browser smoke for `/biz/saleproject/dealProjectList` using a temporary local auth token and temporary browser profile.
- Injected the same frontend cache shape used after login (`TOKEN`, `USER_INFO`, `MENU`, `SYS_CONFIG`, `SYS_USER_PROCESS_CONFIG`, `DICT_TYPE_TREE_DATA`, and module id) without printing secrets.
- Verified the page rendered 5 table rows and clicking the first project link opened the detail drawer.
- Observed only read/SSE backend requests:
  - `/api/dev/message/createSseConnect`
  - `/api/biz/user/orgTreeSelector`
  - `/api/biz/saleproject/page`
  - `/api/biz/task/count`
  - `/api/sys/index/message/list`
  - `/api/biz/process/query`
  - `/api/biz/saleproject/detail`
  - `/api/biz/saleprojectreissueorder/list/query`
  - `/api/biz/bizfilerelation/list`
  - `/api/biz/process/project/runtime/query/list`
  - `/api/biz/returnorder/query`
  - `/api/biz/customer/detail`
- Updated the progress dashboard and problem log.

### Modified Files

- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/problem-optimization-log.md`
- `STATUS.md`

### Test Results

- `.\scripts\project-progress.ps1 -Lean`: passed.
- `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean`: passed after services were started and Vue finished cold-starting.
- `.\scripts\project-preflight.ps1 -SkipWeb`: passed.
- Browser smoke: passed; no forbidden write/upload/delete/approval/complete/provider requests, no failed backend API statuses, no uncanceled failed loads, and no blocking console/page errors. The known Ant Design Vue Descriptions span warning was treated as non-blocking.

### Current Issues

- This is browser verification only; no business code, frontend source, Java source, schema, `.env`, production data, or Git history was changed.
- The local frontend took about 81 seconds to cold-start before port `83` was ready.
- Browser automation was done through a temporary Chrome CDP script because the project still has no local Playwright/Puppeteer dependency.

### Next Plan

- Continue with cloud storage cleanup/provider planning after configuration policy is confirmed, or pick another concrete browser-visible page with an explicit forbidden request pattern before opening side-effect-heavy sale-project, workflow, finance, stock, or provider writes.

## 2026-06-16 09:07 +08:00 - test-agent/frontend-agent - Reusable Browser Page Smoke Helper

### Completed

- Promoted the one-off Chrome CDP browser-smoke flow into `scripts/browser-page-smoke.ps1`.
- Kept the helper ASCII-only to avoid Windows PowerShell corrupting non-ASCII JavaScript regex/text while still allowing runtime page text in output.
- The helper creates a temporary local auth token, injects the copied frontend cache shape, starts temporary headless Chrome, tracks backend `/api` requests, filters the known Ant Design Vue Descriptions warning, and fails on forbidden write/upload/delete/approval/complete/provider requests.
- Reran `/biz/saleproject/dealProjectList` through the helper with first-link click enabled.
- Verified the page rendered 5 table rows and clicking the first project link opened the detail drawer.
- Updated the progress dashboard and closed problem-log row `P-023`.

### Modified Files

- `scripts/browser-page-smoke.ps1`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/problem-optimization-log.md`
- `STATUS.md`

### Test Results

- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/saleproject/dealProjectList" -ClickFirstTableLink -MinRows 1`: passed.
- Browser smoke observed only read/SSE backend requests and no failed backend API statuses, uncanceled failed loads, console errors, page errors, or forbidden write/provider requests.

### Current Issues

- This helper depends on the local runtime, backend `http://127.0.0.1:82/think`, frontend `http://127.0.0.1:83/`, local `.env`, Node, PHP, and system Chrome.
- It is intentionally a smoke helper, not a full browser regression suite; broader page-specific assertions still need explicit target pages and forbidden request patterns.

### Next Plan

- Run `.\scripts\project-progress.ps1 -Lean`, `git diff --check`, and Git status.
- Continue with cloud storage cleanup/provider planning after configuration policy is confirmed, or choose the next concrete browser-visible page for this helper before touching side-effect-heavy writes.

## 2026-06-16 09:18 +08:00 - test-agent/frontend-agent - Customer Browser Smoke

### Completed

- Selected `/biz/customer` as the next low-risk copied frontend page because its list and detail routes are read-focused and already have HTTP smoke coverage.
- Ran the reusable Chrome CDP browser helper against `/biz/customer` with first table-link click enabled.
- Found and fixed a helper false-positive: customer details may render a business-license image through `GET /api/dev/file/download`, which is a read path, not a write/provider side effect.
- Updated `scripts/browser-page-smoke.ps1` so the default forbidden regex no longer flags `download`, and added `-ForbiddenPathPattern` for page-specific stricter checks.
- Reran the customer browser smoke successfully: 11 rows rendered, the first customer detail drawer opened, and no forbidden write/upload/delete/approval/complete/provider requests were observed.
- Added problem-log row `P-024` and updated the progress dashboard.

### Modified Files

- `scripts/browser-page-smoke.ps1`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/problem-optimization-log.md`
- `STATUS.md`

### Test Results

- `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean`: passed.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/customer" -ClickFirstTableLink -MinRows 1`: passed.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/saleproject/dealProjectList" -ClickFirstTableLink -MinRows 1`: passed after the helper forbidden-filter adjustment.
- ASCII-only check for `scripts/browser-page-smoke.ps1`: passed.
- Observed backend requests: message SSE, org tree selector, customer page, task count, index messages, customer detail, and local file download for the image.

### Current Issues

- This remains browser smoke only; no customer business behavior, schema, Java source, `.env`, or production data was changed.
- The helper now treats downloads as allowed by default; pages that must forbid downloads should pass a stricter `-ForbiddenPathPattern`.

### Next Plan

- Rerun the sale-project browser smoke once after the helper filter change, then run `.\scripts\project-progress.ps1 -Lean`, `git diff --check`, and Git status.
- Continue with another concrete read page only after choosing its expected forbidden request pattern, or wait for cloud storage/provider configuration policy before planning those deferred areas.

## 2026-06-16 09:21 +08:00 - test-agent/frontend-agent - Product And Supplier Browser Smoke

### Completed

- Selected `/biz/bizproduct` and `/biz/supplier` as low-risk copied frontend main-data pages because their first table links open detail drawers instead of edit/delete actions.
- Ran the reusable Chrome CDP browser helper against `/biz/bizproduct`.
- Verified product management rendered 10 rows and clicking the first product name opened the product detail drawer.
- Ran the reusable Chrome CDP browser helper against `/biz/supplier`.
- Verified supplier management rendered 11 rows and clicking the first supplier name opened the supplier detail drawer.
- Updated the progress dashboard with the new browser-smoke coverage.

### Modified Files

- `docs/tasks/refactor-progress-dashboard.md`
- `STATUS.md`

### Test Results

- `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean`: passed.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/bizproduct" -ClickFirstTableLink -MinRows 1`: passed.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/supplier" -ClickFirstTableLink -MinRows 1`: passed.
- Product smoke observed message SSE, org tree selector, task count, index messages, product page, and product detail reads.
- Supplier smoke observed message SSE, supplier page/detail reads, task count, index messages, and related purchase-order list read.

### Current Issues

- This is still browser smoke only; no business code, frontend source, schema, Java source, `.env`, production data, or Git history was changed.
- Product status switches and supplier edit/delete controls were not clicked; side-effect-heavy actions remain deferred to explicit write plans.

### Next Plan

- Run `.\scripts\project-progress.ps1 -Lean`, `git diff --check`, and Git status.
- Continue expanding browser smoke only for concrete read/detail pages with known safe click targets, or pause for user confirmation on cloud storage/provider configuration before planning those deferred areas.

## 2026-06-16 09:24 +08:00 - test-agent/frontend-agent - Settlement Account And Warehouse Browser Smoke

### Completed

- Inspected `/biz/warehouses` and `/biz/settlementaccount` before clicking table links.
- Confirmed `/biz/warehouses` has no safe name/detail link in the table body; its visible table links are edit/delete controls, so the smoke stayed list-only.
- Confirmed `/biz/settlementaccount` uses the account-name link as the detail entry while status switch and income/expense controls are separate actions.
- Ran the reusable Chrome CDP browser helper against `/biz/settlementaccount` with first table-link click enabled.
- Verified settlement-account management rendered 10 rows and clicking the first account name opened the detail drawer.
- Ran the reusable Chrome CDP browser helper against `/biz/warehouses` without clicking table links.
- Verified warehouse management rendered 4 rows without triggering write/upload/delete/approval/complete/provider requests.
- Updated the progress dashboard with the new browser-smoke coverage.

### Modified Files

- `docs/tasks/refactor-progress-dashboard.md`
- `STATUS.md`

### Test Results

- `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean`: passed.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/settlementaccount" -ClickFirstTableLink -MinRows 1`: passed.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/warehouses" -MinRows 1`: passed.
- Settlement-account smoke observed message SSE, org tree selector, account page/detail, task count, index messages, payment-record list, and expenditure-record list reads.
- Warehouse smoke observed message SSE, org tree selector, warehouse page, task count, and index messages.

### Current Issues

- This is browser smoke only; no settlement, warehouse, finance, stock, schema, Java source, `.env`, production data, or Git history was changed.
- Warehouse add/edit/delete controls and settlement account status/income/expense/transfer actions were not clicked; those remain covered only by explicit write plans and lower-level smokes.

### Next Plan

- Run `.\scripts\project-progress.ps1 -Lean`, `git diff --check`, and Git status.
- Continue only with pages where the safe click target is known, or stop browser-smoke expansion and wait for cloud storage/provider configuration policy before planning those deferred areas.

## 2026-06-16 09:28 +08:00 - test-agent/frontend-agent - Inventory And Finance Browser Smoke

### Completed

- Inspected `/biz/inventory`, `/biz/paymentrecord`, and `/biz/bizexpenditurerecord` before choosing click behavior.
- Confirmed `/biz/inventory` has a safe product-name detail link; the inventory add, export, and stock-check controls were left untouched.
- Confirmed `/biz/paymentrecord` and `/biz/bizexpenditurerecord` should be list-only in this round because their visible actions are workflow/export/edit oriented rather than direct finance-detail links.
- Ran the reusable Chrome CDP browser helper against `/biz/inventory` with first table-link click enabled.
- Verified inventory management rendered 10 rows and clicking the first product name opened the product detail drawer.
- Ran list-only browser smokes for `/biz/paymentrecord` and `/biz/bizexpenditurerecord`; both rendered 10 rows.
- The first expenditure-record smoke was run in parallel with payment-record smoke and hit a `Runtime.evaluate timeout`; rerunning it alone passed, so this was classified as browser-smoke execution interference rather than a page/backend failure.
- Added a named mutex to `scripts/browser-page-smoke.ps1` so future helper invocations run sequentially.
- Added problem-log row `P-025` and updated the progress dashboard.

### Modified Files

- `scripts/browser-page-smoke.ps1`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/problem-optimization-log.md`
- `STATUS.md`

### Test Results

- `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean`: passed.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/inventory" -ClickFirstTableLink -MinRows 1`: passed.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/paymentrecord" -MinRows 1`: passed.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/bizexpenditurerecord" -MinRows 1`: passed when rerun sequentially.
- ASCII-only check for `scripts/browser-page-smoke.ps1`: passed after adding the mutex.
- Payment-record smoke was rerun after the mutex change and passed.

### Current Issues

- This is browser smoke only; no inventory, finance, workflow, stock, schema, Java source, `.env`, production data, or Git history was changed.
- Finance export/process-detail/edit actions and inventory add/export/stock-check actions remain unclicked and deferred to explicit plans.

### Next Plan

- Run `.\scripts\project-progress.ps1 -Lean`, `git diff --check`, and Git status.
- Keep browser smokes sequential and continue only with concrete read/detail pages whose click target is safe.

## 2026-06-16 09:32 +08:00 - test-agent/frontend-agent - Purchase Return And Receivable Browser Smoke

### Completed

- Inspected `/biz/bizpurchaseorder`, `/biz/returnorder`, `/biz/bizcollectionreceipt`, and `/biz/bizdebitnote` before choosing click behavior.
- Confirmed `/biz/bizpurchaseorder` has a safe title detail link while procurement start, one-click warehouse entry, export, cancel, warehouse entry, edit, and audit repair actions are separate controls.
- Confirmed `/biz/returnorder` has a safe project-name detail link while process-detail, edit, and delete controls are separate links.
- Confirmed `/biz/bizcollectionreceipt` and `/biz/bizdebitnote` should stay list-only in this round because their visible actions are quick settlement, export, historical entry, or mark-settled controls.
- Ran the reusable Chrome CDP browser helper against `/biz/bizpurchaseorder` with first table-link click enabled.
- Verified purchase-order management rendered 10 rows and clicking the first title opened the purchase-order detail drawer.
- Ran the helper against `/biz/returnorder` with first table-link click enabled.
- Verified return-order management rendered 1 row and clicking the first project name opened the sale-project detail drawer.
- Ran list-only browser smokes for `/biz/bizcollectionreceipt` and `/biz/bizdebitnote`; both rendered 10 rows.
- Updated the progress dashboard with the new browser-smoke coverage.

### Modified Files

- `docs/tasks/refactor-progress-dashboard.md`
- `STATUS.md`

### Test Results

- `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean`: passed.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/bizpurchaseorder" -ClickFirstTableLink -MinRows 1`: passed.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/returnorder" -ClickFirstTableLink -MinRows 1`: passed.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/bizcollectionreceipt" -MinRows 1`: passed.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/bizdebitnote" -MinRows 1`: passed.
- Purchase-order smoke observed supplier list, purchase-order page/detail, process query, and filtered process query-list reads.
- Return-order smoke observed return-order page and sale-project detail read chain.
- Collection-receipt and debit-note smokes observed settlement-account list plus page/list reads only.

### Current Issues

- This is browser smoke only; no purchase, return, receivable, debit, warehouse, workflow, settlement, schema, Java source, `.env`, production data, or Git history was changed.
- Procurement start, warehouse entry, export, cancel, edit, audit repair, process-detail, quick settlement, mark-settled, and historical entry actions remain unclicked and deferred to explicit plans.

### Next Plan

- Run `.\scripts\project-progress.ps1 -Lean`, `git diff --check`, and Git status.
- Continue only with concrete read/detail pages whose click target is safe, or stop browser-smoke expansion until cloud storage/provider configuration policy is confirmed.

## 2026-06-16 11:29 +08:00 - test-agent/frontend-agent - Upload Provider Guard Browser Smoke Batch

### Completed

- Added `scripts/browser-upload-provider-guard-smoke.ps1` as a sequential wrapper around `scripts/browser-page-smoke.ps1`.
- Encoded the upload/provider deferred-plan forbidden request pattern into the wrapper, including upload, import, export, delete, send, provider-like, scheduler-run, approval, workflow, grant, reset, status, and save paths.
- Ran the guarded smoke batch for `/dev/file/index`, `/biz/bizpayroll`, `/biz/bizproduct`, `/biz/customer`, and `/biz/saleproject/dealProjectList`.
- Clicked only known safe read-detail table links on product, customer, and deal-project pages.
- Allowed legitimate local image reads through `/api/dev/file/download` while keeping upload and provider actions unclicked.
- Updated the upload/provider deferred plan and progress dashboard with the reusable command.

### Modified Files

- `scripts/browser-upload-provider-guard-smoke.ps1`
- `docs/tasks/upload-provider-deferred-plan.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `STATUS.md`

### Test Results

- `.\scripts\browser-upload-provider-guard-smoke.ps1`: passed.
- `/dev/file/index`: rendered 10 rows with file page reads only.
- `/biz/bizpayroll`: rendered 2 rows with payroll page reads only.
- `/biz/bizproduct`: rendered 10 rows, opened product detail, and made only product page/detail reads.
- `/biz/customer`: rendered 11 rows, opened customer detail, and made only customer page/detail plus local file download reads.
- `/biz/saleproject/dealProjectList`: rendered 5 rows, opened project detail, and made sale-project, process, file-relation, return-order, and customer reads only.

### Current Issues

- This is browser smoke only; no upload, import, export, delete, provider send, workflow, scheduler, schema, Java source, `.env`, production data, or Git history was changed.
- Real cloud storage, provider sends, thumbnail generation, physical file cleanup, import/export actions, and side-effect-heavy business writes remain deferred.

### Next Plan

- Run `.\scripts\project-progress.ps1 -Lean`, `git diff --check`, and Git status.
- Continue with another low-risk browser/read smoke slice or choose a module-specific transactional write plan before implementing side-effect-heavy behavior.

## 2026-06-16 11:51 +08:00 - test-agent/frontend-agent - Finance Purchase Inventory Guard Browser Smoke Batch

### Completed

- Reused `scripts/browser-upload-provider-guard-smoke.ps1` with explicit finance, purchase, inventory, and business-operation targets from the current login menu.
- Ran list-only guarded browser smokes for purchase order, return order, collection receipt, debit note, inventory, payment record, expenditure record, settlement account, warehouse, supplier, sale-project invoicing, and sale-project product-info pages.
- Kept the slice list/static-only because these pages expose export, delete, settlement, stock, invoicing, return, warehouse, finance, upload, or workflow controls.
- Checked current `loginMenu` paths and confirmed raw `/sys/resource`, `/mobile/module`, `/mobile/menu`, and `/gen/*` targets are not currently menu-backed without temporary authorization; no temporary authorization rows were inserted.
- Updated the upload/provider deferred plan and progress dashboard with the reusable commands and login-menu limitation.

### Modified Files

- `docs/tasks/upload-provider-deferred-plan.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `STATUS.md`

### Test Results

- `.\scripts\browser-upload-provider-guard-smoke.ps1 -SkipDefaultTargets -TargetPath '/biz/bizpurchaseorder','/biz/returnorder','/biz/bizcollectionreceipt','/biz/bizdebitnote','/biz/inventory'`: passed.
- `.\scripts\browser-upload-provider-guard-smoke.ps1 -SkipDefaultTargets -TargetPath '/biz/paymentrecord','/biz/bizexpenditurerecord','/biz/settlementaccount','/biz/warehouses','/biz/supplier','/biz/saleprojectinvoicing','/biz/saleprojectproductinfo'`: first six targets passed; `/biz/saleprojectproductinfo` hit `Runtime.evaluate timeout`.
- `.\scripts\browser-upload-provider-guard-smoke.ps1 -SkipDefaultTargets -TargetPath '/biz/saleprojectproductinfo'`: passed when rerun alone.
- All successful page checks had zero forbidden requests and zero bad API statuses.

### Current Issues

- This is browser smoke only; no purchase, return, finance, inventory, warehouse, supplier, invoicing, product-info, workflow, schema, Java source, `.env`, production data, or Git history was changed.
- Resource/mobile/gen browser smoke still needs a separate temporary-authorization plan if the current local login menu does not expose those paths.
- The `Runtime.evaluate timeout` on the long batch matched the known browser-smoke resource issue and passed on single-target rerun.

### Next Plan

- Run `.\scripts\project-progress.ps1 -Lean`, `git diff --check`, and Git status.
- Continue with another login-menu-backed read/browser slice, or pause broad browser-smoke expansion until a module-specific write or temporary-authorization plan is selected.

## 2026-06-16 12:12 +08:00 - test-agent/frontend-agent - Sales Operations Report Guard Browser Smoke Batch

### Completed

- Found local web services stopped at the start of the continuation; restarted ThinkPHP on port `82` and Vue on port `83`.
- Found MySQL/Redis/PHP-FPM runtime stopped when the first browser smoke attempted to generate a local token; restarted the user-provided runtime bundle at `F:\project\socket\AI\testPhp\files\startServer1.bat`.
- Waited for Vite cold start to finish; this run took about 90 seconds before port `83` responded.
- Ran list/report-only guarded browser smokes for sale-project, sales public/case/shipment/completed/cancelled/report pages.
- Ran list/report-only guarded browser smokes for operations customer/project, proxy-payment, security-deposit, and data-report pages.
- Updated the upload/provider deferred plan and progress dashboard with the commands and runtime precondition note.

### Modified Files

- `docs/tasks/upload-provider-deferred-plan.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `STATUS.md`

### Test Results

- `.\scripts\runtime-ready.ps1`: passed after starting the local runtime bundle.
- `.\scripts\web-ready.ps1`: passed after backend/frontend startup and Vite cold start.
- `.\scripts\browser-upload-provider-guard-smoke.ps1 -SkipDefaultTargets -TargetPath '/biz/saleproject','/biz/saleproject/public/list','/biz/saleproject/dealProjectCaseList','/biz/saleproject/waitShipment','/biz/saleproject/completeProjectList','/biz/saleproject/cancelProjectList','/biz/saleproject/report'`: passed.
- `.\scripts\browser-upload-provider-guard-smoke.ps1 -SkipDefaultTargets -TargetPath '/biz/bizops/operationCustomerList','/biz/bizops/operationProjectList','/biz/proxyPayment','/biz/ProjectSecurityDeposit','/biz/bizdatareport/index','/biz/bizdatareport/summaryStatistics','/biz/bizdatareport/settlement','/biz/bizdatareport/saleProfit'`: passed.
- All successful page checks had zero forbidden requests and zero bad API statuses.

### Current Issues

- This is browser smoke only; no sale-project, operations, report, settlement, finance, workflow, delivery, cancel, export, schema, Java source, `.env`, production data, or Git history was changed.
- Browser smoke depends on both runtime readiness and web readiness; `/think` can respond while MySQL/Redis are still unavailable, so use `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean` before DB-backed or browser checks.
- Vite startup can be slow in this workspace; wait for `.\scripts\web-ready.ps1` before interpreting page failures.

### Next Plan

- Run `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean`, `git diff --check`, and Git status.
- Continue with another login-menu-backed read/browser slice, or pause broad browser-smoke expansion until a module-specific write or temporary-authorization plan is selected.

## 2026-06-16 11:34 +08:00 - test-agent/frontend-agent - Management Page Guard Browser Smoke Batch

### Completed

- Reused `scripts/browser-upload-provider-guard-smoke.ps1` with explicit management-page targets.
- Ran guarded render-only browser smokes for system user, business user, role, system organization, system position, business organization, business position, business dictionary, system config, and station-message pages.
- Kept the slice list/static-only because these pages expose import, export, grant, reset, send, save, delete, or status controls.
- Updated the upload/provider deferred plan with the management-page target commands.
- Updated the progress dashboard with this verification note.

### Modified Files

- `docs/tasks/upload-provider-deferred-plan.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `STATUS.md`

### Test Results

- `.\scripts\browser-upload-provider-guard-smoke.ps1 -SkipDefaultTargets -TargetPath '/sys/user','/biz/user','/sys/role','/sys/org','/sys/position'`: passed.
- `.\scripts\browser-upload-provider-guard-smoke.ps1 -SkipDefaultTargets -TargetPath '/biz/org','/biz/position','/biz/dict/index','/sys/sysConfig/index','/dev/message/index'`: passed.
- `/sys/user`, `/biz/user`, `/sys/role`, `/sys/org`, `/sys/position`, `/biz/org`, `/biz/position`, `/biz/dict/index`, and `/dev/message/index` rendered list/tree rows with read/cache/SSE requests only.
- `/sys/sysConfig/index` rendered the config page with cache/SSE requests and no save-style request.

### Current Issues

- This is browser smoke only; no user/org/position/role/dict/config/message writes, provider sends, import/export, grant/reset/status, schema, Java source, `.env`, production data, or Git history was changed.
- Add/edit/delete/import/export/grant/reset/send/save/status controls remain unclicked and must stay tied to module-specific write plans.

### Next Plan

- Run `.\scripts\project-progress.ps1 -Lean`, `git diff --check`, and Git status.
- Continue with another low-risk browser/read smoke slice or choose a module-specific transactional write plan before implementing side-effect-heavy behavior.

## 2026-06-16 10:55 +08:00 - test-agent/frontend-agent - Frontend Controlled Deferred Write Wrappers

### Completed

- Inspected the copied frontend API consumers and the read-only Java reference controllers for payment record, return order, and sale-project invoicing.
- Added missing copied-frontend API exports for visible payment-record, return-order, and invoicing form/delete controls.
- Added protected ThinkPHP routes and controller wrappers for payment-record add/edit/edit-account/delete, return-order add/edit/delete, and sale-project-invoicing add/edit/delete.
- Kept these wrappers controlled-deferred only: authenticated calls return `code=400` and do not call service write methods, mutate tables, move inventory, update balances, start workflow, read provider credentials, or clean physical files.
- Added `scripts/frontend-deferred-write-wrapper-smoke.ps1` and documentation for the wrapper scope.
- Added problem-log row `P-032` and updated dashboard/API-gap notes.

### Modified Files

- `app/controller/biz/PaymentRecordController.php`
- `app/controller/biz/ReturnOrderController.php`
- `app/controller/biz/SaleProjectInvoicingController.php`
- `route/app.php`
- `snowy-admin-web/src/api/biz/bizPaymentRecordApi.js`
- `snowy-admin-web/src/api/biz/returnOrderApi.js`
- `snowy-admin-web/src/api/biz/bizSaleProjectInvoicingApi.js`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/problem-optimization-log.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\biz\PaymentRecordController.php; php -l app\controller\biz\ReturnOrderController.php; php -l app\controller\biz\SaleProjectInvoicingController.php; php -l route\app.php`: passed.
- `php think route:list` targeted check: listed all ten controlled-deferred wrapper routes.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed with no remaining active missing API-method calls.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed; ten authenticated wrapper calls returned `code=400` and three representative no-token calls returned `code=401`.
- `.\scripts\project-progress.ps1 -CheckWeb -Lean`: passed before the final doc update.
- ASCII-only check for `scripts/frontend-deferred-write-wrapper-smoke.ps1`: passed.

### Current Issues

- This is not real payment-record, return-order, or invoicing write implementation; transactional finance, return/order state, stock, invoicing, workflow, rollback, notification, provider, and data-change behavior remains deferred.
- The API gap-map aggregate frontend endpoint counts were not fully regenerated in this slice; the route count was verified as 491, and the new wrappers were verified separately.
- No Java source, `.env`, production data, schema, or Git history was changed.

### Next Plan

- Run `.\scripts\project-progress.ps1 -Lean`, `git diff --check`, and Git status.
- Continue with the next low-risk compatibility slice only after checking active frontend consumers and the relevant problem-log rows.

## 2026-06-16 09:42 +08:00 - test-agent/frontend-agent - Data Report And Team Project Browser Smoke

### Completed

- Inspected data-report and team-project frontend consumers before choosing smoke targets.
- A direct `/biz/bizdatareport` smoke rendered 404; this was a guessed path, not a page/backend failure.
- Queried local `sys_resource` menu paths and confirmed the actual report paths are `/biz/bizdatareport/index`, `/biz/bizdatareport/summaryStatistics`, `/biz/bizdatareport/settlement`, and `/biz/bizdatareport/saleProfit`.
- Ran list/card/chart browser smokes for all four report pages without clicking statistic cards or navigation targets.
- Ran a list/card browser smoke for `/biz/bizteamproject` without clicking project cards, task controls, or comment/write areas.
- Added problem-log row `P-027` and updated the progress dashboard.

### Modified Files

- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/problem-optimization-log.md`
- `STATUS.md`

### Test Results

- `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean`: passed.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/bizdatareport/index" -MinRows 0`: passed.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/bizdatareport/settlement" -MinRows 0`: passed.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/bizdatareport/saleProfit" -MinRows 0`: passed.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/bizdatareport/summaryStatistics" -MinRows 0`: passed.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/bizteamproject" -MinRows 0`: passed.
- Report smokes observed sale-project report, settlement income/expense, unpaid-payment, sale-profit, and summary-statistics reads only.
- Team-project smoke observed team-project page read only.

### Current Issues

- This is browser smoke only; no report, team-project, task, comment, workflow, schema, Java source, `.env`, production data, or Git history was changed.
- Statistic-card navigation, team-project detail, task/comment actions, add/edit/delete, and report export-style actions remain unclicked.
- Route selection for browser smoke should use menu `PATH` when a component folder has no exact route root.

### Next Plan

- Run `.\scripts\project-progress.ps1 -Lean`, `git diff --check`, and Git status.
- Continue only with concrete read/detail pages whose route and click target are known safe, or stop browser-smoke expansion until cloud storage/provider configuration policy is confirmed.

## 2026-06-16 10:31 +08:00 - test-agent/frontend-agent - Browser Smoke Optional Click Helper

### Completed

- Added `-AllowMissingTableLink` to `scripts/browser-page-smoke.ps1`.
- Preserved strict behavior by default: `-ClickFirstTableLink` still fails when no visible table link exists unless `-AllowMissingTableLink` is explicitly supplied.
- When optional click is allowed and no link exists, the helper now passes the rendered page check and reports `click.missingAllowed=true`.
- Updated problem-log row `P-029` from mitigated rerun practice to a closed helper-level fix.
- Updated the progress dashboard with this helper verification note.

### Modified Files

- `scripts/browser-page-smoke.ps1`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/problem-optimization-log.md`
- `STATUS.md`

### Test Results

- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/biztask" -MinRows 0 -ClickFirstTableLink -AllowMissingTableLink`: passed with `click.missingAllowed=true`.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/biztask/mystarttask" -MinRows 0 -ClickFirstTableLink -AllowMissingTableLink`: passed and still opened the process detail drawer when a visible table link existed.

### Current Issues

- This is a helper-only improvement; no business APIs, frontend views, schema, Java source, `.env`, production data, or Git history was changed.
- Optional click should not be used for pages where opening detail is a required assertion.

### Next Plan

- Run `.\scripts\project-progress.ps1 -Lean`, `git diff --check`, and Git status.
- With login-menu component leaves covered, shift the next slice to a deliberate plan for deferred upload/provider/file-cleanup behavior or specific write flows instead of broad read-only browser expansion.

## 2026-06-16 10:43 +08:00 - test-agent/api-agent - Upload Provider Deferred Plan

### Completed

- Added `docs/tasks/upload-provider-deferred-plan.md` as the execution boundary for upload-control browser smoke, cloud storage, real provider sends, and optional physical file cleanup.
- Recorded current confirmed behavior: LOCAL/dynamic upload, public local download, metadata logical delete, and business file relation binding are covered; cloud upload routes remain unsupported stubs; SMS sends remain controlled-deferred wrappers; real email/SMS providers, thumbnails, historical path migration, and physical cleanup remain deferred.
- Added concrete browser smoke forbidden-request patterns, including the default pattern that allows local file downloads and a stricter opt-in pattern that forbids `download`.
- Linked the new plan from `docs/tasks/api-gap-map.md`.
- Added problem-log row `P-030` so future continuations see the upload/provider scope boundary before opening a risky slice.
- Updated the progress dashboard.

### Modified Files

- `docs/tasks/upload-provider-deferred-plan.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/problem-optimization-log.md`
- `STATUS.md`

### Test Results

- Documentation-only slice; no route, service, frontend, schema, Java source, `.env`, credential, cloud provider, file system cleanup, or production data behavior was changed.

### Current Issues

- Real Aliyun, Tencent, Minio, email, SMS, thumbnail, historical storage-path migration, and physical file cleanup remain intentionally deferred.
- Future browser smoke on pages with upload or provider controls must define the forbidden request pattern before running the helper.

### Next Plan

- Run `.\scripts\project-progress.ps1 -Lean`, `git diff --check`, and Git status.
- Continue with a specific low-risk business write/read slice only after checking frontend consumers and smoke scope, or wait for user confirmation before opening cloud/provider/file-cleanup implementation.

## 2026-06-16 10:47 +08:00 - test-agent/frontend-agent - Frontend API Static Smoke Comment Handling

### Completed

- Inspected `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred` after the upload/provider planning slice.
- Found that the deferred list included `bizSaleProjectApi.bizSaleProjectApplyApproval` from a commented-out legacy code block in the copied project start-payment form.
- Updated `scripts/frontend-api-method-smoke.ps1` to strip Vue/HTML comments plus JavaScript line and block comments before scanning imports and API method calls.
- The comment stripper preserves string/template literals and line structure so real active imports and calls are still scanned.
- Documented the behavior in `docs/api/frontend-api-method-smoke.md`.
- Added problem-log row `P-031`.

### Modified Files

- `scripts/frontend-api-method-smoke.ps1`
- `docs/api/frontend-api-method-smoke.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/problem-optimization-log.md`
- `STATUS.md`

### Test Results

- `.\scripts\frontend-api-method-smoke.ps1`: passed.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed; deferred list no longer includes the commented legacy `bizSaleProjectApplyApproval` call and now shows 6 active write-like missing methods.
- ASCII-only check for `scripts/frontend-api-method-smoke.ps1`: passed.

### Current Issues

- Active write-like frontend calls for payment record, return order, and sale-project invoicing remain deferred and should not be implemented without module-specific write plans.

### Next Plan

- Rerun the static frontend API method smoke.
- Then run `.\scripts\project-progress.ps1 -Lean`, `git diff --check`, and Git status.

## 2026-06-16 10:28 +08:00 - test-agent/frontend-agent - Home And Team Project Detail Browser Smoke

### Completed

- Listed the current smoke account's `loginMenu` again and compared it with the covered browser-smoke paths.
- Inspected `/index` and `/biz/bizteamprojectdetails` before running smoke; confirmed the team-project detail page requires `route.query.id`.
- Retrieved a local team-project sample id through the authenticated page API and opened `/biz/bizteamprojectdetails?id=1903996479133360129`.
- Ran browser smokes for `/index` and the team-project detail page without clicking schedule/message, comment, member, task, edit, delete, upload, or save controls.
- Updated the progress dashboard to note that current `loginMenu` component leaf pages are now covered by read-only browser smoke, excluding category/grouping nodes without page components.

### Modified Files

- `docs/tasks/refactor-progress-dashboard.md`
- `STATUS.md`

### Test Results

- `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean`: passed.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/index" -MinRows 0`: passed with workbench, visit-log, all-process, schedule, message, and task-count reads.
- Authenticated team-project sample lookup through `/biz/bizteamproject/page?current=1&size=1`: returned sample id `1903996479133360129`.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/bizteamprojectdetails?id=1903996479133360129" -MinRows 0`: passed with team-project detail, user, comment, and task-list reads.

### Current Issues

- This is browser smoke only; no schedule/message actions, team-project comment/member/task writes, edit/delete, upload, schema, Java source, `.env`, production data, or Git history was changed.
- Category/grouping menu nodes such as `/biz`, `/system`, and numeric folder paths do not have page components and remain intentionally excluded from page smoke coverage.

### Next Plan

- Run `.\scripts\project-progress.ps1 -Lean`, `git diff --check`, and Git status.
- With login-menu component leaves covered, shift the next slice to a deliberate plan for deferred upload/provider/file-cleanup behavior or specific write flows instead of broad read-only browser expansion.

## 2026-06-16 10:21 +08:00 - test-agent/frontend-agent - Workflow Menu Browser Smoke

### Completed

- Inspected the copied workflow menu pages before choosing click behavior.
- Ran browser smokes for login-menu-backed `/biz/biztask`, `/biz/historytask`, `/biz/biztask/mystarttask`, `/biz/biztask/allprocess`, `/biz/copytask`, and `/biz/biztask/processList`.
- Opened the process detail drawer on `/biz/biztask/mystarttask`, where the local sample exposed a visible safe title link.
- Reran `/biz/biztask`, `/biz/historytask`, `/biz/biztask/allprocess`, and `/biz/copytask` as list-only smokes after strict detail-click attempts found no visible table link in the current local sample.
- Kept `/biz/biztask/processList` static-only because it contains start-flow buttons.
- Added problem-log row `P-029` and updated the progress dashboard.

### Modified Files

- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/problem-optimization-log.md`
- `STATUS.md`

### Test Results

- `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean`: passed.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/biztask" -MinRows 0`: passed with 1 rendered table row and `/api/biz/task/page` reads.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/historytask" -MinRows 0`: passed with 1 rendered table row and `/api/biz/task/history/page` reads.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/biztask/mystarttask" -MinRows 0 -ClickFirstTableLink`: passed with 3 rendered rows, opened process detail, and read process detail/file/variable plus related business data.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/biztask/allprocess" -MinRows 0`: passed with 1 rendered table row and `/api/biz/process/all/page` reads.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/copytask" -MinRows 0`: passed with 1 rendered table row and `/api/biz/ccrecords/page` reads.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/biztask/processList" -MinRows 0`: passed with static start-flow page rendering and read-only supporting requests.

### Current Issues

- This is browser smoke only; no approve, reject, cancel, start-flow, copy-record delete, schema, Java source, `.env`, production data, or Git history was changed.
- Strict `-ClickFirstTableLink` attempts on empty/local-sample task pages can false-fail with no visible table link; `P-029` records the mitigation.

### Next Plan

- Run `.\scripts\project-progress.ps1 -Lean`, `git diff --check`, and Git status.
- Continue only with login-menu-backed read/detail pages whose click targets are known safe, or stop browser-smoke expansion until cloud storage/provider configuration policy is confirmed.

## 2026-06-16 10:14 +08:00 - test-agent/frontend-agent - Operations Invoicing And Debit Browser Smoke

### Completed

- Inspected the copied operations, invoicing, sale-project product-info, proxy-payment, and project-security-deposit pages before choosing click behavior.
- Ran browser smokes for login-menu-backed `/biz/bizops/operationCustomerList`, `/biz/bizops/operationProjectList`, `/biz/saleprojectinvoicing`, `/biz/saleprojectproductinfo`, `/biz/proxyPayment`, and `/biz/ProjectSecurityDeposit`.
- Clicked safe detail links only on operations customer and operations project pages.
- Kept invoicing, product-info, proxy-payment, and deposit pages list/report-only because their visible controls include mark-complete, export, quick settlement, add, edit, or delete-style actions.
- Updated the progress dashboard with this verification note.

### Modified Files

- `docs/tasks/refactor-progress-dashboard.md`
- `STATUS.md`

### Test Results

- `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean`: passed.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/bizops/operationCustomerList" -MinRows 0 -ClickFirstTableLink`: passed with 11 rendered rows, opened customer detail, and read customer detail plus local file download data.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/bizops/operationProjectList" -MinRows 0 -ClickFirstTableLink`: passed with 11 rendered rows, opened sale-project detail, and read project detail/process/file/reissue/return/customer data.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/saleprojectinvoicing" -MinRows 0`: passed with 11 rendered rows and invoicing page reads.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/saleprojectproductinfo" -MinRows 0`: passed with 1 rendered row and sale-project product/detail report reads.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/proxyPayment" -MinRows 0`: passed with 10 rendered rows and debit-note list/page reads for proxy-payment.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/ProjectSecurityDeposit" -MinRows 0`: passed with 2 rendered rows and debit-note list/page reads for project-security-deposit.

### Current Issues

- This is browser smoke only; no operations customer/project writes, invoicing completion, product-info mutation, debit-note mark-success/quick settlement, export, schema, Java source, `.env`, production data, or Git history was changed.
- Invoicing mark-complete, product-info add/edit/delete/export, proxy-payment/deposit quick settlement/export/mark, and operations edit/delete/head-reassignment actions remain unclicked.

### Next Plan

- Run `.\scripts\project-progress.ps1 -Lean`, `git diff --check`, and Git status.
- Continue only with login-menu-backed read/detail pages whose click targets are known safe, or stop browser-smoke expansion until cloud storage/provider configuration policy is confirmed.

## 2026-06-16 10:06 +08:00 - test-agent/frontend-agent - Sales Project Menu Browser Smoke

### Completed

- Inspected the copied sales-project menu pages before choosing click behavior.
- Ran browser smokes for login-menu-backed `/biz/saleproject`, `/biz/saleproject/public/list`, `/biz/saleproject/dealProjectCaseList`, `/biz/saleproject/waitShipment`, `/biz/saleproject/completeProjectList`, `/biz/saleproject/cancelProjectList`, and `/biz/saleproject/report`.
- Clicked the first safe project/case detail links on `/biz/saleproject`, `/biz/saleproject/public/list`, and `/biz/saleproject/dealProjectCaseList`.
- Kept wait-shipment, completed-project, cancelled-project, and report pages list/chart-only because their visible links or controls lead toward invoice, delivery, workflow, export, cancel, or write-style actions.
- Updated the progress dashboard with this verification note.

### Modified Files

- `docs/tasks/refactor-progress-dashboard.md`
- `STATUS.md`

### Test Results

- `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean`: passed.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/saleproject" -MinRows 0 -ClickFirstTableLink`: passed with 11 rendered rows, opened project detail, and read project detail/process/file/reissue/return/customer data.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/saleproject/public/list" -MinRows 0 -ClickFirstTableLink`: passed with 10 rendered rows, opened project detail, and read project detail/process/file/reissue/return/customer data.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/saleproject/dealProjectCaseList" -MinRows 0 -ClickFirstTableLink`: passed with 11 rendered rows, opened case detail, and read file-relation/project-rate data.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/saleproject/waitShipment" -MinRows 0`: passed with 10 rendered rows and sale-project page reads.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/saleproject/completeProjectList" -MinRows 0`: passed with 10 rendered rows and sale-project page reads.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/saleproject/cancelProjectList" -MinRows 0`: passed with 11 rendered rows plus process-query reads.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/saleproject/report" -MinRows 0`: passed with chart/statistic reads through `POST /api/biz/bizdatareport/saleproject/list`.

### Current Issues

- This is browser smoke only; no sale-project write behavior, invoicing, delivery, workflow start/cancel, export, schema, Java source, `.env`, production data, or Git history was changed.
- Shipment, completed-project, cancelled-project, report-card navigation, invoice, delivery, workflow, export, batch repeal, and visibility-switch actions remain unclicked.

### Next Plan

- Run `.\scripts\project-progress.ps1 -Lean`, `git diff --check`, and Git status.
- Continue only with login-menu-backed read/detail pages whose click targets are known safe, or stop browser-smoke expansion until cloud storage/provider configuration policy is confirmed.

## 2026-06-16 09:58 +08:00 - test-agent/frontend-agent - Business Directory Browser Smoke

### Completed

- Listed the current smoke account's `loginMenu` paths before choosing targets so browser routes were backed by frontend dynamic-menu registration.
- Ran list/tree browser smokes for `/biz/org`, `/biz/position`, `/biz/user`, and `/biz/dict/index`.
- Kept this slice read-only by not clicking add, edit, delete, import, export, grant, reset, status, save, or provider-action controls.
- Confirmed all four pages rendered through the copied frontend with read/cache/SSE-style backend requests only.
- Updated the progress dashboard with this verification note.

### Modified Files

- `docs/tasks/refactor-progress-dashboard.md`
- `STATUS.md`

### Test Results

- `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean`: passed.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/org" -MinRows 0`: passed with 10 rendered rows and `/api/biz/org/tree` plus `/api/biz/org/page` reads.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/position" -MinRows 0`: passed with 10 rendered rows and `/api/biz/org/tree` plus `/api/biz/position/page` reads.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/user" -MinRows 0`: passed with 10 rendered rows and `/api/biz/org/tree` plus `/api/biz/user/page` reads.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/dict/index" -MinRows 0`: passed with 10 rendered rows and `/api/biz/dict/tree` plus `/api/biz/dict/page` reads.

### Current Issues

- This is browser smoke only; no business organization, position, user, dictionary, schema, Java source, `.env`, production data, or Git history was changed.
- Add/edit/delete/import/export/grant/reset/status/save actions remain unclicked and should stay tied to explicit module-specific write plans.

### Next Plan

- Run `.\scripts\project-progress.ps1 -Lean`, `git diff --check`, and Git status.
- Continue with only login-menu-backed read/detail pages whose click targets are known safe, or stop browser-smoke expansion until cloud storage/provider configuration policy is confirmed.

## 2026-06-16 09:54 +08:00 - test-agent/frontend-agent - System Org And Position Browser Smoke

### Completed

- Inspected the copied `/sys/org` and `/sys/position` pages before choosing a safe list/tree-only smoke path.
- Ran browser smokes for both login-menu-backed routes without clicking add, edit, delete, status, grant, reset, import, export, or save controls.
- Confirmed both pages rendered rows through the copied frontend while using only read/cache/SSE-style backend requests.
- Updated the progress dashboard with this verification note.

### Modified Files

- `docs/tasks/refactor-progress-dashboard.md`
- `STATUS.md`

### Test Results

- `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean`: passed.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/sys/org" -MinRows 0`: passed with 10 rendered rows and `/api/sys/org/tree` plus `/api/sys/org/page` reads.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/sys/position" -MinRows 0`: passed with 10 rendered rows and `/api/sys/org/tree` plus `/api/sys/position/page` reads.

### Current Issues

- This is browser smoke only; no organization or position add/edit/delete/status behavior, schema, Java source, `.env`, production data, or Git history was changed.
- Future browser smokes should still verify target paths through `loginMenu` first instead of raw `sys_resource` rows.

### Next Plan

- Run `.\scripts\project-progress.ps1 -Lean`, `git diff --check`, and Git status.
- Continue only with login-menu-backed read/detail pages whose click targets are known safe, or stop browser-smoke expansion until cloud storage/provider configuration policy is confirmed.

## 2026-06-16 09:49 +08:00 - test-agent/frontend-agent - Dev And System Browser Smoke

### Completed

- Queried menu-backed dev/sys paths before running browser smoke.
- Found that raw `sys_resource` includes `/dev/job`, `/dev/email/index`, `/dev/sms/index`, and `/dev/monitor`, but the current smoke account's `loginMenu` only exposes a smaller set of dev/sys routes to the copied frontend.
- Updated `scripts/browser-page-smoke.ps1` so it selects the top-level menu module that contains the target route instead of always using the first menu module.
- Confirmed `/dev/job` still renders 404 because it is not in the current `loginMenu`; this is a route-availability issue, not a backend page failure.
- Ran list/render browser smokes for `/dev/file/index`, `/dev/message/index`, `/sys/sysConfig/index`, `/sys/role`, and `/sys/user` without clicking upload, send, delete, grant, reset, import, export, enable/disable, or save controls.
- Reran `/biz/customer` as a business-route regression check after the helper module-selection change.
- Added problem-log row `P-028` and updated the progress dashboard.

### Modified Files

- `scripts/browser-page-smoke.ps1`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/problem-optimization-log.md`
- `STATUS.md`

### Test Results

- `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean`: passed.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/dev/file/index" -MinRows 0`: passed.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/dev/message/index" -MinRows 0`: passed.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/sys/sysConfig/index" -MinRows 0`: passed.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/sys/role" -MinRows 0`: passed.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/sys/user" -MinRows 0`: passed.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/customer" -MinRows 1`: passed after the helper module-selection change.
- ASCII-only check for `scripts/browser-page-smoke.ps1`: passed.

### Current Issues

- This is browser smoke only; no dev/system write behavior, provider send, scheduler execution, upload/delete, grant/reset/status, schema, Java source, `.env`, production data, or Git history was changed.
- `/dev/job` remains unsuitable for this helper under the current smoke account because it is not present in `loginMenu`; the same rule should be checked before targeting other raw `sys_resource` paths.

### Next Plan

- Run `.\scripts\project-progress.ps1 -Lean`, `git diff --check`, and Git status.
- Continue only with login-menu-backed read/detail pages, or stop browser-smoke expansion and wait for cloud storage/provider configuration policy before planning deferred provider/file-cleanup areas.

## 2026-06-16 09:37 +08:00 - test-agent/frontend-agent - HR And History Browser Smoke

### Completed

- Inspected `/biz/bizpayroll`, `/biz/bizleaveapplication`, and `/biz/bizhistoryexcel` before choosing click behavior.
- Confirmed `/biz/bizpayroll` should stay list-only because visible actions include import, export, and delete.
- Confirmed `/biz/bizleaveapplication` should stay list-only in this round because visible actions include add, edit, delete, and workflow process detail rather than a direct leave-detail link.
- Confirmed `/biz/bizhistoryexcel` has a source-level table-name detail link, but the local rendered sample did not expose a visible safe table link; the final smoke stayed list-only.
- Ran the reusable Chrome CDP browser helper against `/biz/bizpayroll`; the first run exposed a non-blocking Ant Design Vue resizable-table warning.
- Added that exact table warning to the helper's allowed-console filters and reran payroll successfully.
- Ran list-only browser smokes for `/biz/bizleaveapplication` and `/biz/bizhistoryexcel`.
- Added problem-log row `P-026` and updated the progress dashboard.

### Modified Files

- `scripts/browser-page-smoke.ps1`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/problem-optimization-log.md`
- `STATUS.md`

### Test Results

- `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean`: passed.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/bizpayroll" -MinRows 1`: passed after allowing the known Ant Design Vue table warning.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/bizleaveapplication" -MinRows 1`: passed.
- `.\scripts\browser-page-smoke.ps1 -TargetPath "/biz/bizhistoryexcel" -MinRows 0`: passed.
- ASCII-only check for `scripts/browser-page-smoke.ps1`: passed after the warning-filter update.

### Current Issues

- This is browser smoke only; no payroll, leave, history Excel, workflow, schema, Java source, `.env`, production data, or Git history was changed.
- Payroll import/export/delete, leave add/edit/delete/process-detail, and history Excel add/edit/delete/detail actions remain unclicked.
- The local history Excel page rendered 1 table row but no visible safe link for the helper to click.

### Next Plan

- Run `.\scripts\project-progress.ps1 -Lean`, `git diff --check`, and Git status.
- Continue only with concrete read/detail pages whose click target is safe, or stop browser-smoke expansion until cloud storage/provider configuration policy is confirmed.

## 2026-06-16 16:26 +08:00 - merge-agent/api-agent/test-agent/docs-agent - Payroll Export CSV Download

### Completed

- Replaced `/biz/bizpayroll/export` controlled-deferred behavior with an authenticated CSV blob download.
- Reused existing payroll filters, data-scope guards, organization sorting, and download response handling.
- Added `scripts/biz-payroll-export-http-smoke.ps1`, which inserts one temporary payroll row, downloads CSV, verifies header/row markers, checks no-token rejection, confirms representative related table counts stay unchanged, and cleans up.
- Removed payroll export from the frontend controlled-deferred smoke list.
- Updated payroll API docs, deferred-wrapper docs, gap map, progress dashboard, bootstrap notes, frontend adaptation notes, public route-change notes, and project-progress fast commands.

### Modified Files

- `app/controller/biz/BizPayrollController.php`
- `app/service/biz/BizPayrollService.php`
- `docs/api/biz-payroll-readonly.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/biz-payroll-export-plan.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `scripts/biz-payroll-export-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-progress.ps1`
- `STATUS.md`

### Test Results

- `php -l app\controller\biz\BizPayrollController.php`: passed.
- `php -l app\service\biz\BizPayrollService.php`: passed.
- PowerShell syntax check for `scripts\biz-payroll-export-http-smoke.ps1`: passed.
- `php think route:list | Select-String -Pattern 'biz/bizpayroll/(export|page|detail|downloadImportTemplate)'`: passed.
- `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean`: passed.
- `.\scripts\biz-payroll-export-http-smoke.ps1`: passed.
- `.\scripts\hr-read-http-smoke.ps1`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed with 78 authenticated deferred wrappers and 17 representative no-token checks.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 560/560 frontend endpoints covered by route path and zero missing reads.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.

### Current Issues

- Export is CSV for dependency-free ThinkPHP compatibility; Java EasyExcel-style `.xlsx` rendering, merged headers, and styling remain deferred.
- Payroll import, generate/add, and add remain controlled-deferred because they create payroll rows and/or aggregate leave, project, payment, and business side effects.

### Next Plan

- Run `git diff --check` and a final lean progress check.
- Continue with the next smallest controlled-deferred group only after a module-specific side-effect and rollback plan.

## 2026-06-16 17:09 +08:00 - merge-agent/api-agent/test-agent/docs-agent - Payment Record Payer-Time Edit

### Completed

- Replaced `/biz/bizpaymentrecord/edit` controlled-deferred behavior with a narrow Java-compatible payer-time correction.
- Added `PaymentRecordService::edit`, which validates `id`/`payerTime`, checks tenant/write scope, updates only payment-record audit/time fields, syncs the linked settlement-account statement timestamp by `SERIAL_ID`, and rolls back when the linked statement is missing.
- Kept `/biz/bizpaymentrecord/add`, `/edit/account`, and `/delete` controlled-deferred.
- Added `scripts/biz-payment-record-edit-http-smoke.ps1`, which inserts temporary payment/statement rows, verifies no-token and missing-field rejection, confirms detail readback and statement sync, checks client-spoofed fields are ignored, tests missing-statement rollback, verifies related-table counts stay stable, and cleans up.
- Removed `/biz/bizpaymentrecord/edit` from the frontend deferred-wrapper smoke list.
- Updated payment-record API docs, deferred-wrapper docs, gap map, progress dashboard, bootstrap notes, frontend adaptation notes, public route-change notes, project-progress fast commands, and this status log.

### Modified Files

- `app/controller/biz/PaymentRecordController.php`
- `app/service/biz/PaymentRecordService.php`
- `docs/api/biz-payment-record-readonly-compat.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/biz-payment-record-edit-plan.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `scripts/biz-payment-record-edit-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-progress.ps1`
- `route/app.php`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\biz\PaymentRecordController.php`: passed.
- `php -l app\service\biz\PaymentRecordService.php`: passed.
- `php -l route\app.php`: passed.
- PowerShell syntax checks for `scripts\biz-payment-record-edit-http-smoke.ps1` and `scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed.
- `php think route:list | Select-String -Pattern 'biz/bizpaymentrecord/(edit|edit/account|add|delete|page|detail)'`: passed.
- `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean`: passed.
- `.\scripts\biz-payment-record-edit-http-smoke.ps1`: passed.
- `.\scripts\finance-read-http-smoke.ps1`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed with 77 authenticated deferred wrappers and 17 representative no-token checks.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 560/560 frontend endpoints covered by route path and zero missing reads.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.
- `.\scripts\project-preflight.ps1`: passed.
- `git diff --check`: passed with existing LF/CRLF warnings only.

### Current Issues

- Payment-record add/delete and account switch remain controlled-deferred because they create or transfer finance records and account statements.
- This slice does not implement settlement-account balance mutation, workflow/data-change events, Java source changes, schema changes, `.env`, Composer changes, production data operations, or Git push.

### Next Plan

- Run the broader regression set: `project-progress`, finance read smoke, deferred-wrapper smoke, frontend route-gap/method smokes, and `git diff --check`.
- Decide whether to commit after the verified slice boundary, taking care not to stage unrelated dirty worktree changes.

## 2026-06-17 Collection Receipt Batch Expenditure

Agent: merge-agent / api-agent / test-agent / docs-agent

### Completed

- Replaced `/biz/bizcollectionreceipt/batchExpenditure/edit` controlled-deferred behavior with narrow Java-compatible repayment quick-settlement creation.
- Added `CollectionReceiptService::batchExpenditure()`, which validates batch items, locks selected receipts, creates repayment expenditure/statement rows through settlement-account quick-expense logic, decrements the selected settlement account, updates receipt settlement amount/status/audit/version, and rolls back failed cases.
- Kept collection-receipt add/edit/delete controlled-deferred.
- Added `scripts/biz-collection-receipt-batch-expenditure-http-smoke.ps1`.
- Removed the route from `scripts/frontend-deferred-write-wrapper-smoke.ps1`.
- Updated collection-receipt docs, deferred-wrapper docs, gap map, dashboard, bootstrap notes, frontend adaptation notes, public route-change notes, plan log, implementation log, and this status log.

### Test Results

- `php -l app\controller\biz\CollectionReceiptController.php`: passed.
- `php -l app\service\biz\CollectionReceiptService.php`: passed.
- `php -l app\service\biz\SettlementAccountService.php`: passed.
- PowerShell syntax check for `scripts\biz-collection-receipt-batch-expenditure-http-smoke.ps1`: passed.
- `php think route:list | Select-String -Pattern 'biz/bizcollectionreceipt/(batchExpenditure/edit|mark/success/edit|page|list|detail|add|edit|delete)'`: passed.
- `.\scripts\web-ready.ps1`: passed.
- `.\scripts\biz-collection-receipt-batch-expenditure-http-smoke.ps1`: passed.
- `.\scripts\settlement-account-expenses-add-http-smoke.ps1`: passed.
- `.\scripts\finance-read-http-smoke.ps1`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed with 71 authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, and 16 representative no-token checks.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 560/560 frontend endpoints covered by route path and zero missing reads.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.
- `.\scripts\project-progress.ps1 -Lean`: passed.
- `git diff --check`: passed with existing LF/CRLF warnings only.

### Current Issues

- Collection-receipt add/edit/delete remain controlled-deferred.
- Debit-note repayment, Java event bus, workflow/data-change hooks, Java source changes, database schema changes, `.env`, Composer/npm/frontend source changes, production data operations, and Git push remain out of scope.

### Next Plan

- Continue with the next smallest finance write route only after a Java/frontend side-effect comparison and a dedicated rollback smoke plan.

## 2026-06-26 Purchase Order Add/Delete And Inventory Delete

Agent: api-agent / test-agent

### Completed

- Replaced `/biz/bizpurchaseorder/add`, `/biz/bizpurchaseorder/delete`, and `/biz/inventory/delete` controlled-deferred behavior with bounded direct CRUD behavior after explicit user approval.
- Added purchase-order direct add for submitted supplier/product payloads. It creates one `NOT_COMPLETED` and `NOT_IN_WAREHOUSE` order plus item rows and writes no finance, workflow, delivery, inventory, notification, or Java data-change side effects.
- Added guarded purchase-order logical delete. It rejects completed, warehoused, expenditure-linked, delivery-linked, or item-warehoused orders before deleting active order/item rows.
- Added inventory logical delete for active zero-count rows only after warehouse/product write-scope checks.
- Removed the three routes from `scripts/frontend-deferred-write-wrapper-smoke.ps1`.
- Updated purchase/inventory API docs, deferred-wrapper docs, API gap map, dashboard, frontend adaptation notes, plan log, implementation log, and this status log.

### Test Results

- `php -l app\controller\biz\PurchaseOrderController.php`: passed.
- `php -l app\service\biz\PurchaseOrderService.php`: passed.
- `php -l app\controller\biz\InventoryController.php`: passed.
- `php -l app\service\biz\InventoryService.php`: passed.
- `php think route:list | Select-String -Pattern 'biz/bizpurchaseorder|biz/inventory'`: passed.
- PowerShell parse check for `scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 560/560 covered frontend endpoints.
- `git diff --check -- app\controller\biz\PurchaseOrderController.php app\service\biz\PurchaseOrderService.php app\controller\biz\InventoryController.php app\service\biz\InventoryService.php scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed with existing LF/CRLF warnings only.

### Current Issues

- DB-backed HTTP CRUD smokes were not run because local Redis/PHP runtime was still unavailable in the earlier runtime check.
- Purchase-order deletion with stock/finance rollback, purchase expenditure creation, settlement-account statements, nonzero inventory deletion, workflow hooks, Java data-change events, Java source changes, database schema changes, `.env`, Composer/npm/frontend source changes, production data operations, and Git push remain out of scope.

### Next Plan

- Remaining product-decision/functionality candidates are task SSE, gen-config add, and non-`FOLLOW` product-item mutation; deployment/runtime work remains final-stage unless the user redirects.

## 2026-06-17 Debit Note Batch Repayment

Agent: merge-agent / api-agent / test-agent / docs-agent

### Completed

- Replaced `/biz/bizdebitnote/batchRepayment/edit` controlled-deferred behavior with narrow Java-compatible loan-repayment quick-settlement creation.
- Added `DebitNoteService::batchRepayment()`, which validates batch items, locks selected debit notes, creates `LoanRepayment` payment/statement rows through settlement-account quick-income logic, increments the selected settlement account, updates debit-note settlement amount/status/audit/version, and rolls back failed cases.
- Kept debit-note add/edit/history-add/delete controlled-deferred.
- Added `scripts/biz-debit-note-batch-repayment-http-smoke.ps1`.
- Removed the route from `scripts/frontend-deferred-write-wrapper-smoke.ps1`.
- Updated debit-note docs, deferred-wrapper docs, gap map, dashboard, bootstrap notes, frontend adaptation notes, public route-change notes, plan log, implementation log, and this status log.

### Test Results

- `php -l app\controller\biz\DebitNoteController.php`: passed.
- `php -l app\service\biz\DebitNoteService.php`: passed.
- `php -l app\service\biz\SettlementAccountService.php`: passed.
- PowerShell syntax check for `scripts\biz-debit-note-batch-repayment-http-smoke.ps1`: passed.
- `php think route:list | Select-String -Pattern 'biz/bizdebitnote/(batchRepayment/edit|mark/success/edit|page|list|detail|add|edit|history/add|delete)'`: passed.
- `.\scripts\web-ready.ps1`: passed.
- `.\scripts\biz-debit-note-batch-repayment-http-smoke.ps1`: passed.
- `.\scripts\settlement-account-payment-add-http-smoke.ps1`: passed.
- `.\scripts\finance-read-http-smoke.ps1`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed with 70 authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, and 16 representative no-token checks.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 560/560 frontend endpoints covered by route path and zero missing reads.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.
- `.\scripts\project-progress.ps1 -Lean`: passed.
- `git diff --check`: passed with existing LF/CRLF warnings only.

### Current Issues

- Debit-note add/edit/history-add/delete remain controlled-deferred.
- Java event bus, workflow/data-change hooks, Java source changes, database schema changes, `.env`, Composer/npm/frontend source changes, production data operations, and Git push remain out of scope.

### Next Plan

- Continue with the next smallest finance write route only after a Java/frontend side-effect comparison and a dedicated rollback smoke plan.

## 2026-06-16 18:25 +08:00 - merge-agent/api-agent/test-agent/docs-agent - Expenditure Record Payer-Time Category Edit

### Completed

- Replaced `/biz/bizexpenditurerecord/edit` controlled-deferred behavior with a narrow Java-compatible payer-time/category correction.
- Added `ExpenditureRecordService::edit`, which validates `id`, optional `payerTime`, optional `settlementCategory`, checks tenant/write scope, rejects object-linked rows, enforces Java category guard rules, updates only expenditure payer-time/category/audit fields, syncs the linked settlement-account statement timestamp when payer time is supplied, and rolls back when the linked statement is missing.
- Kept `/biz/bizexpenditurerecord/add`, `/edit/account`, and `/delete` controlled-deferred.
- Added `scripts/biz-expenditure-record-edit-http-smoke.ps1`, which inserts temporary expenditure/statement/account rows, verifies no-token and missing-id rejection, confirms detail readback and statement sync, checks protected-category and object-linked guards, tests missing-statement rollback, verifies spoofed fields and related-table counts stay stable, and cleans up.
- Removed `/biz/bizexpenditurerecord/edit` from the frontend deferred-wrapper smoke list.
- Updated expenditure-record API docs, deferred-wrapper docs, gap map, progress dashboard, bootstrap notes, frontend adaptation notes, public route-change notes, project-progress fast commands, and this status log.

### Modified Files

- `app/controller/biz/ExpenditureRecordController.php`
- `app/service/biz/ExpenditureRecordService.php`
- `docs/api/biz-expenditure-record-readonly-compat.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/biz-expenditure-record-edit-plan.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `scripts/biz-expenditure-record-edit-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-progress.ps1`
- `route/app.php`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Test Results

- `php -l app\controller\biz\ExpenditureRecordController.php`: passed.
- `php -l app\service\biz\ExpenditureRecordService.php`: passed.
- `php -l route\app.php`: passed.
- PowerShell syntax checks for `scripts\biz-expenditure-record-edit-http-smoke.ps1` and `scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed.
- `php think route:list | Select-String -Pattern 'biz/bizexpenditurerecord/(edit|edit/account|add|delete|page|detail)'`: passed.
- `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean`: passed.
- `.\scripts\biz-expenditure-record-edit-http-smoke.ps1`: passed.
- `.\scripts\finance-read-http-smoke.ps1`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed with 76 authenticated deferred wrappers and 17 representative no-token checks.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 560/560 frontend endpoints covered by route path and zero missing reads.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.
- `git diff --check`: passed with existing LF/CRLF warnings only.

### Current Issues

- Expenditure-record add/delete and account switch remain controlled-deferred because they create/delete finance rows or transfer account/statement data.
- This slice does not implement settlement-account balance mutation, statement category/account edits, purchase/inventory/return/workflow/data-change events, Java source changes, schema changes, `.env`, Composer changes, production data operations, or Git push.

### Next Plan

- Run the broader regression set: `project-progress`, finance read smoke, deferred-wrapper smoke, frontend route-gap/method smokes, and `git diff --check`.
- Decide whether to commit after the verified slice boundary, taking care not to stage unrelated dirty worktree changes.

## 2026-06-17 Debit Note History Add

Agent: merge-agent / api-agent / test-agent / docs-agent

### Completed

- Replaced `/biz/bizdebitnote/history/add` controlled-deferred behavior with narrow Java-compatible historical debit-note creation.
- Added `DebitNoteService::historyAdd()`, which validates account, amount, history amount, create time, and remark fields, derives org/tenant from the selected settlement account, inserts one `biz_debit_note` row with no expenditure link, sets `SETTLEMENT_AMOUNT = HISTORY_AMOUNT`, and derives `PLAY_STATUS`.
- Kept debit-note add/edit/delete controlled-deferred.
- Added `scripts/biz-debit-note-history-add-http-smoke.ps1`.
- Removed the route from `scripts/frontend-deferred-write-wrapper-smoke.ps1`.
- Updated debit-note docs, deferred-wrapper docs, gap map, dashboard, bootstrap notes, frontend adaptation notes, public route-change notes, plan log, implementation log, and this status log.

### Test Results

- `php -l app\controller\biz\DebitNoteController.php`: passed.
- `php -l app\service\biz\DebitNoteService.php`: passed.
- `php -l app\service\biz\SettlementAccountService.php`: passed.
- `php -l route\app.php`: passed.
- PowerShell syntax checks for `scripts\biz-debit-note-history-add-http-smoke.ps1` and `scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed.
- `php think route:list | Select-String -Pattern 'biz/bizdebitnote/(history/add|batchRepayment/edit|mark/success/edit|page|list|detail|add|edit|delete)'`: passed.
- `.\scripts\web-ready.ps1`: passed.
- `.\scripts\biz-debit-note-history-add-http-smoke.ps1`: passed.
- `.\scripts\biz-debit-note-batch-repayment-http-smoke.ps1`: passed.
- `.\scripts\settlement-account-payment-add-http-smoke.ps1`: passed.
- `.\scripts\finance-read-http-smoke.ps1`: passed.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed with 69 authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, and 15 representative no-token checks.
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`: passed with 560/560 frontend endpoints covered by route path and zero missing reads.
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`: passed.
- `.\scripts\project-progress.ps1 -Lean`: passed.
- `git diff --check`: passed with existing LF/CRLF warnings only.

### Current Issues

- Debit-note add/edit/delete remain controlled-deferred.
- Java event bus, workflow/data-change hooks, Java source changes, database schema changes, `.env`, Composer/npm/frontend source changes, production data operations, and Git push remain out of scope.

### Next Plan

- Continue with the next smallest finance write route only after a Java/frontend side-effect comparison and a dedicated rollback smoke plan.

## 2026-07-13 - frontend-agent/test-agent - Shared Team Project Detail Route

### Completed

- Fixed invited team-project members receiving a frontend 404 for `/biz/bizteamprojectdetails?id=...` when their role menu did not include the hidden detail resource.
- Registered the detail page as an authenticated hidden static route; project visibility remains protected by the existing backend current-member checks.
- Added a static regression assertion and updated P-028 plus the regression checklist for cross-role invitation/notification deep links.

### Modified Files

- `snowy-admin-web/src/config/route.js`
- `scripts/regression-safety-smoke.php`
- `docs/tasks/problem-optimization-log.md`
- `docs/tasks/regression-checklist.md`
- `STATUS.md`

### Test Results

- `php scripts/regression-safety-smoke.php`: passed.
- `composer check`: passed with 245 PHP files and the route table.
- `npm run build` in `snowy-admin-web`: passed with existing environment, Browserslist, dependency-eval, and CSS-comment warnings only.
- `git diff --check`: passed with normal LF/CRLF warnings only.

### Current Issues

- Local MySQL, backend, and frontend services were unavailable, so authenticated browser readback with a second project-member account was not run.
- No backend authorization, schema, Java source, production data, dependency, Git history, or deployment changes were made.
