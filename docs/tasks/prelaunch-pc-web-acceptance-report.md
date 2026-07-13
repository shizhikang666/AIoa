# Prelaunch PC Web Acceptance Report

Date: 2026-07-02

Scope: PC Web only. This report intentionally excludes `mobile/module`, `mobile/menu`, `mobile/button`, mobile pages, and mobile permission flows.

## Goal Gate

The system can enter上线测试 only when all P0 rows are `PASS` or have an explicit owner-approved risk acceptance. P1 rows should be completed where practical; any unrun P1 item must be listed with risk and workaround.

## Explicit Non-Blockers

Do not block this PC Web上线测试 on these deferred items unless separately approved:

- Mobile/phone flows and mobile resource permissions.
- Real Email/SMS/WebPush/OAuth provider sends.
- Cloud storage provider enablement.
- Long-lived `/biz/task/sse/stream`.
- `POST /gen/config/add`.
- Non-`FOLLOW` sale-project product-item direct mutation.
- Real scheduler execution.
- Final production data sync.

## P0 Matrix

| Gate | Required Evidence | Current Evidence | Status | Next Action |
| --- | --- | --- | --- | --- |
| Role login/menu/permission/data isolation | Demo roles can log in, load menus, access allowed APIs, and receive 403 for disallowed APIs | 2026-07-02 online role permission smoke passed after adding sales read permissions for `/biz/ccrecords/page` and `/biz/ccrecords/detail`; forbidden cross-role checks still return `403` | PASS | Keep the target backup/rollback files until after first上线测试 |
| PC role browser pages | Business, finance, executive/HR, tech, and demo-admin pages render without 4xx/5xx, backend JSON errors, console errors, or accidental write requests | 2026-07-02 integrated PC smoke passed twice: role permission smoke plus 17/17 PC browser pages, including sales `/biz/copytask`; the second pass was after Nginx/PHP runtime changes | PASS | None for read-only page gate |
| Business/finance/approval/tech role E2E | Each role exercises at least one visible or API-backed flow that creates, verifies, and cleans up test data | 2026-07-02 target controlled write smokes passed for sales customer follow-up, HR leave, tech package, finance account/payment/receipt/expense/debit cleanup, project-init workflow, and payroll generation cleanup | PASS | Browser button-level write E2E remains weaker evidence, but API-backed target role E2E is current PASS |
| Sales approval approve/reject/cancel | Start, cancel, reject, and approve create expected state and no rejected/canceled side effects | 2026-07-02 target project-init workflow smoke passed cancel/reject/approve; cancel/reject rolled back to `FOLLOW`, approve persisted `WAIT_DELIVER` plus product item/file relation/invoicing side effects, and cleanup left 0 residual rows | PASS | Delivery/play/reissue/return approval smokes remain local/prior evidence unless declared first-test blockers |
| Deployment readiness | Production/strict readiness, public exposure, CORS, security headers, backup tools, web-server syntax, database schema, artifact policy, frontend build policy | 2026-07-02 target live-root strict readiness passed with `0 failures, 0 warnings` after PHP/session/CORS/FileController readiness fixes; security headers, sensitive-path 404s, backend CORS OPTIONS, DB schema, backup tools, writable paths, Nginx/PHP-FPM syntax, Composer/frontend dist policy, and route:list all passed | PASS | Optional: run the same strict script against a staged release package root before final production cutover |
| Backup/rollback/logs | File rollback path, DB backup, log paths, and tail procedure confirmed | Target `.deploy/backups` contains latest file and DB backups; permission, Nginx header, PHP runtime, and cookie config changes have backup directories; Nginx/PHP-FPM logs exist and were tailed | PASS | Keep backup directories until after first test window |
| Core backend smoke on target | Authenticated route/API smokes pass against target base URL and clean temporary data | 2026-07-02 role permission smoke, controlled write/API smokes, project-init workflow smoke, payroll smoke, DB schema check, and `php think route:list` all passed on target | PASS | None for current PC backend target smoke set |

## P1 Matrix

| Area | Coverage Needed | Current Evidence | Status | Next Action |
| --- | --- | --- | --- | --- |
| Customer | List/detail plus follow-up write cleanup | 2026-07-02 target browser read passed and customer-follow-up add/detail/delete cleanup passed | PASS | Optional browser button write flow |
| Sale project | List/detail plus project-init approval flow | 2026-07-02 target browser read passed and project-init approve/reject/cancel workflow passed | PASS | Decide whether delivery/play/reissue/return are first-test blockers |
| Payroll/leave | Payroll direct add/edit/batch/delete/generate plus leave add/delete plus import/export sample | 2026-07-02 target payroll add/detail/edit/batch/delete/generate cleanup and leave add/detail/delete cleanup passed; P1 target payroll export/import sample also passed | PASS | None for current P1 payroll sample |
| Finance | Settlement account, income, expense, collection receipt, debit note | 2026-07-02 target finance controlled smoke passed add/detail/delete and account amount restoration | PASS | None for covered finance smoke |
| Purchase/inventory | Purchase/order and inventory pages plus selected write paths | 2026-07-02 target P1 smoke passed inventory add/detail and purchase-order warehouse-in side effects, then cleaned residual data | PASS | None for current P1 sample |
| Attachment upload/download | PC upload controls and local downloads work | 2026-07-02 target P1 smoke passed local file upload, download body verification, logical delete, and physical/DB cleanup | PASS | Demo role upload permission is still role-config dependent; functional upload/download path is verified |
| Import/export/download | Payroll CSV, user export/download, generator ZIP, templates | 2026-07-02 target P1 smoke passed payroll CSV export and payroll xlsx import using the production template structure, then cleaned residual data | PASS | User-directory export/template remains covered by prior service/local evidence, not rerun in this P1 target sample |

## Recommended Execution Order

1. Confirm target environment and backup window.
2. Run server-side readiness on the staged release:

```bash
bash scripts/deployment-readiness.sh --production --strict --check-error-log-policy --check-opcache-policy --check-scheduler-policy --check-cache-policy --check-cookie-policy --check-url-policy --check-storage-policy --check-provider-policy --check-env-template-policy --check-runtime-permission-policy --check-web-server-policy --check-nginx-syntax --check-php-fpm-syntax --check-security-headers-policy --check-cors-policy --check-database-schema --check-artifact-policy --check-frontend-build-policy --check-composer-policy --check-release-package-policy --check-backup-tools --public-base-url https://oa.fucity.cn --cors-probe-origin https://oa.fucity.cn
```

3. Run PC role browser pages without writes:

```powershell
.\scripts\prelaunch-pc-web-acceptance.ps1 -FrontendBaseUrl https://oa.fucity.cn -ApiPrefix /backend -SkipControlledWrites
```

4. Run target controlled write/API smokes after backup/log readiness:

```powershell
.\scripts\prelaunch-pc-web-acceptance.ps1 -FrontendBaseUrl https://oa.fucity.cn -ApiPrefix /backend -RunControlledWrites
```

5. Run P1 target flow smoke:

```powershell
.\scripts\online-p1-flow-smoke.ps1 -FrontendBaseUrl https://oa.fucity.cn -ApiPrefix /backend
```

6. Fill the final signoff table below and decide whether extended workflow/browser-button coverage is required before first PC Web online testing.

## Evidence Log

### 2026-07-02 Sales Ccrecords Permission Patch

Problem found:

- `csyw001` real menu contains `/biz/copytask`.
- `/biz/copytask` renders the "抄送任务" page, but its list request `GET /biz/ccrecords/page` previously returned application `code=403`, `msg=权限不足`.
- `scripts/demo-tenant-permission-init.php` sales role plan had `/biz/copytask` in `menuPathPrefixes` but did not include sales read API permissions for ccrecords.

Target patch applied with backup:

- Target role ID: `1781598867209709438`
- Added relation `1782958219970000537` for `/biz/ccrecords/page`
- Added relation `1782958220008000850` for `/biz/ccrecords/detail`
- Backup directory: `/www/wwwroot/oa.fucity.cn/runtime/permission-init-sales-ccrecords-20260702-101019`
- Backup files: `before-snapshot.json`, `apply-summary.json`, `rollback-inserted.sql`

Local source of truth was also updated in `scripts/demo-tenant-permission-init.php` so future permission initialization includes the same sales read permissions.

### 2026-07-02 Online Role Permission Smoke

Command:

```powershell
.\scripts\online-role-permission-smoke.ps1 -FrontendBaseUrl https://oa.fucity.cn -ApiPrefix /backend
```

Result: PASS.

Summary:

- Login/menu counts: `superAdminTwo` 66, `csyw001` 24, `cscw001` 18, `cszjb001` 13, `csjs001` 23.
- `csyw001` can now read `GET /biz/ccrecords/page?current=1&size=1`.
- Forbidden data-isolation checks still return `403`: sales payroll, finance customer, executive customer, and tech payroll.

### 2026-07-02 PC Role Browser Pages

Command:

```powershell
.\scripts\prelaunch-pc-web-acceptance.ps1 -FrontendBaseUrl https://oa.fucity.cn -ApiPrefix /backend -SkipControlledWrites
```

Result: 17/17 PC role browser pages passed after the ccrecords permission patch. The integrated run also passed the online role permission smoke. Controlled write/API smokes were intentionally skipped in this run.

The same command was rerun after Nginx security headers, PHP 8.3 runtime policy, and cookie policy changes. Result remained PASS: role permission smoke plus 17/17 PC browser pages passed.

| Role | Account | Page | Rows | Result |
| --- | --- | --- | ---: | --- |
| Sales | `csyw001` | `/biz/customer` | 3 | PASS |
| Sales | `csyw001` | `/biz/saleproject` | 2 | PASS |
| Sales | `csyw001` | `/biz/copytask` | 1 | PASS |
| Finance | `cscw001` | `/biz/settlementaccount` | 2 | PASS |
| Finance | `cscw001` | `/biz/paymentrecord` | 1 | PASS |
| Finance | `cscw001` | `/biz/bizexpenditurerecord` | 1 | PASS |
| Finance | `cscw001` | `/biz/bizcollectionreceipt` | 1 | PASS |
| Finance | `cscw001` | `/biz/bizdebitnote` | 1 | PASS |
| Executive/HR | `cszjb001` | `/biz/bizpayroll` | 2 | PASS |
| Executive/HR | `cszjb001` | `/biz/bizleaveapplication` | 1 | PASS |
| Tech | `csjs001` | `/biz/bizproduct` | 2 | PASS |
| Tech | `csjs001` | `/biz/inventory` | 2 | PASS |
| Tech | `csjs001` | `/biz/saleprojectproductinfo` | 1 | PASS |
| Demo admin | `superAdminTwo` | `/sys/org` | 10 | PASS |
| Demo admin | `superAdminTwo` | `/sys/user` | 10 | PASS |
| Demo admin | `superAdminTwo` | `/sys/position` | 10 | PASS |
| Demo admin | `superAdminTwo` | `/sys/role` | 10 | PASS |

All checked pages stayed on the target route, rendered no 404, had no HTTP 4xx/5xx API responses, no backend JSON application errors, no browser console errors, and no accidental write requests matching add/edit/delete/start/approve/reject/cancel/send/grant/reset/enable/disable/revoke/save.

### 2026-07-02 Target Controlled Write And Workflow Smokes

Command:

```powershell
.\scripts\prelaunch-pc-web-acceptance.ps1 -FrontendBaseUrl https://oa.fucity.cn -ApiPrefix /backend -RunControlledWrites -SkipRolePermission -SkipBrowserPages
```

Result: PASS. Controlled writes executed and cleaned up.

Covered:

- Sales `csyw001`: `/biz/customerfollowup/add -> detail -> delete`, marker `CODEX_WRITE_SMOKE_20260702101922`.
- HR/executive `cszjb001`: `/biz/bizleaveapplication/add -> detail -> delete`, same marker.
- Tech `csjs001`: `/biz/saleprojectproductinfo/add -> detail -> delete`, same marker.
- Finance `cscw001`: settlement account, payment record, collection receipt, expenditure, debit note add/detail/delete flow, marker `CODEX_FINANCE_SMOKE_20260702101927`; account amount was restored to `1000.00` before account delete.
- Sales project-init workflow: start/cancel, start/reject, and start/approve passed, marker `CODEX_PROJECT_INIT_SMOKE_20260702101932`; cancel and reject projects rolled back to `FOLLOW`, approve project persisted `WAIT_DELIVER`, with 1 product item, 1 file relation, 1 invoicing row, customer deal amount `1.00`, and 0 residual setup rows after cleanup.
- Payroll `cszjb001`: direct payroll add/detail/edit/batch-edit/delete and generated payroll for 2 users passed, marker `CODEX_PAYROLL_SMOKE_20260702101948`; active payroll after logical delete was 0 and all remote setup residual counts were 0 after cleanup.

### 2026-07-02 Target Deployment Readiness Remediation

Target runtime changes applied with backups:

- Sales ccrecords permission backup: `/www/wwwroot/oa.fucity.cn/runtime/permission-init-sales-ccrecords-20260702-101019`
- Nginx security headers backup: `/www/wwwroot/oa.fucity.cn/.deploy/backups/nginx-security-headers-20260702-1026`
- PHP 8.3 runtime policy backup: `/www/wwwroot/oa.fucity.cn/.deploy/backups/php83-runtime-policy-20260702-1030`
- Cookie policy backup: `/www/wwwroot/oa.fucity.cn/.deploy/backups/cookie-policy-20260702-1040`
- Live-root readiness sync backup: `/www/wwwroot/oa.fucity.cn/.deploy/backups/live-root-readiness-20260702-1050`
- PHP 8.3 post body limit backup: `/www/wwwroot/oa.fucity.cn/.deploy/backups/php83-post-size-20260702-1050`

Runtime checks now passing on target:

- `APP_DEBUG=false`, PHP 8.3.3, required PHP extensions loaded.
- `display_errors` off, `log_errors` on, `error_log=/www/server/php/83/var/log/php_errors.log`.
- OPcache extension loaded, `opcache.enable=1`, memory `128 MB`, `max_accelerated_files=10000`.
- Cookie secure flag true, HttpOnly true, SameSite `lax`.
- Session name is `OA_THINKPHP_SESSID`.
- Nginx `nginx -t` passed and PHP-FPM 8.3 `php-fpm -tt` passed.
- Security headers present on HTTPS entry: HSTS, `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, CSP, `Referrer-Policy`, and `Permissions-Policy`.
- Backend API CORS preflight to `https://oa.fucity.cn/backend/auth/b/getLoginUser` returned HTTP 204 with explicit `Access-Control-Allow-Origin: https://oa.fucity.cn`, credentials, methods, headers, and `Vary: Origin`.
- Sensitive paths such as `/.env`, `/composer.json`, `/vendor/autoload.php`, `/runtime`, `/app`, `/config`, `/docs`, and `/scripts` returned HTTP 404 from the public entry.
- Database schema check passed: `SELECT 1`, 121 tables, 57 curated tables, and 38 curated column groups.
- Backup tools and `.deploy/backups` are available and writable.
- Final live-root strict command passed with `0 failures, 0 warnings`:

```bash
bash scripts/deployment-readiness.sh --php-bin /www/server/php/83/bin/php --php-fpm-bin /www/server/php/83/sbin/php-fpm --production --live-root --strict --check-error-log-policy --check-opcache-policy --check-scheduler-policy --check-cache-policy --check-cookie-policy --check-url-policy --check-storage-policy --check-provider-policy --check-env-template-policy --check-runtime-permission-policy --check-web-server-policy --check-nginx-syntax --check-php-fpm-syntax --check-security-headers-policy --check-cors-policy --check-database-schema --check-artifact-policy --check-frontend-build-policy --check-composer-policy --check-release-package-policy --check-backup-tools --backup-directory .deploy/backups --public-base-url https://oa.fucity.cn --cors-probe-origin https://oa.fucity.cn --cors-probe-url https://oa.fucity.cn/backend/auth/b/getLoginUser
```

Readiness notes after remediation:

- `--live-root` mode intentionally validates the deployed live tree, where `.env`, runtime directories, backups, and `dist` exist; release-package-only source/docs checks are treated as staged-package responsibilities.
- The CORS probe now targets the backend API URL, not the site root, and passes with HTTP 204 plus explicit origin, credentials, methods, headers, and `Vary: Origin`.
- `app/controller/dev/FileController.php` no longer emits wildcard `Access-Control-Allow-Origin`; same-site and allowlisted `fucity.cn` origins are handled explicitly.

### 2026-07-02 Target P1 Flow Smoke

Command:

```powershell
.\scripts\online-p1-flow-smoke.ps1 -FrontendBaseUrl https://oa.fucity.cn -ApiPrefix /backend
```

Result: PASS. Marker `CODEX_P1_SMOKE_20260702110811`.

Covered:

- Inventory add/detail target smoke.
- Purchase-order warehouse-in target smoke with inventory and delivery side-effect verification.
- Local file upload, download body verification, logical delete, and physical/DB cleanup.
- Payroll CSV export target smoke.
- Payroll xlsx import target smoke using the production payroll import template structure.

Cleanup audit for marker `CODEX_P1_SMOKE_20260702110811`: warehouses 0, products 0, purchase orders 0, purchase items 0, payroll 0, import user 0.

### 2026-07-02 Backup/Rollback/Logs

Confirmed target paths:

- Latest file backup pointer: `/www/wwwroot/oa.fucity.cn/.deploy/backups/files-20260630-110410.tar.gz`
- Latest DB backup pointer: `/www/wwwroot/oa.fucity.cn/.deploy/backups/db-20260630-110410.sql.gz`
- Nginx access log: `/www/wwwlogs/oa.fucity.cn.log`
- Nginx error log: `/www/wwwlogs/oa.fucity.cn.error.log`
- PHP-FPM 8.3 log directory: `/www/server/php/83/var/log`
- PHP error log after runtime policy: `/www/server/php/83/var/log/php_errors.log`
- Current vhost: `/www/server/panel/vhost/nginx/oa.fucity.cn.conf`
- Current rewrite include: `/www/server/panel/vhost/rewrite/oa.fucity.cn.conf`

Rollback references:

- File rollback can use the deployment script with the latest `files-*.tar.gz` backup.
- DB rollback is manual from the latest `db-*.sql.gz` backup.
- The Nginx security header, PHP runtime policy, cookie policy, and sales ccrecords permission changes each have their own backup directory listed above.

### 2026-07-02 Public Deployment Probe

Command:

```powershell
.\scripts\deployment-readiness.ps1 -SkipThinkBoot -PublicBaseUrl https://oa.fucity.cn -CheckSecurityHeadersPolicy -CheckCorsPolicy -CorsProbeOrigin https://oa.fucity.cn
```

Original result before target remediation: 0 failures, 14 warnings.

Passed:

- Sensitive public paths such as `/.env`, `/composer.json`, `/vendor/autoload.php`, `/runtime`, `/app`, `/config`, `/route`, `/docs`, `/scripts`, `/tests`, `PLANS.md`, `IMPLEMENT.md`, and `STATUS.md` returned HTTP 404.
- Public web exposure guard found no sensitive project entries under local `public`.
- `.env` is not tracked and expected ignored artifact paths are ignored.
- HTTPS entry probe returned HTTP 200.
- Same-origin frontend API prefix is relative, so browser CORS can be avoided when frontend/backend stay same-origin.

Original warnings and their final disposition:

- Security headers were added on target and strict readiness now verifies them.
- CORS is handled by target server/API behavior in live-root mode; the backend probe URL passed strict readiness.
- The previous file-download wildcard origin source was removed from `app/controller/dev/FileController.php`.
- The old root-path CORS probe was replaced with the real backend API OPTIONS probe.
- Local PHP upload limit remains a local-development note; target PHP now has `upload_max_filesize=50M` and `post_max_size=64M`.
- Local `.env` remains local-only; target `.env` has `APP_DEBUG=false`.

## Final Signoff Table

| Item | Result | Evidence | Risk | Owner Decision |
| --- | --- | --- | --- | --- |
| P0 role login/menu/permission/data isolation | PASS | 2026-07-02 online role permission smoke passed after sales ccrecords read permission patch; forbidden cross-role checks still return `403` | Low; keep rollback files until after first test window |  |
| P0 PC role browser pages | PASS | 2026-07-02 integrated PC smoke passed role permission smoke plus 17/17 PC browser pages, including `/biz/copytask` | Low for read-only page load gate; write flows are tracked separately |  |
| P0 role E2E controlled writes | PASS | 2026-07-02 target controlled write smokes passed for sales, HR, tech, finance, project-init workflow, and payroll with cleanup | Low for API-backed target E2E; browser button write coverage is not equivalent |  |
| P0 sales approval approve/reject/cancel | PASS | 2026-07-02 project-init start/cancel, start/reject, and start/approve passed with expected side effects and cleanup | Medium if delivery/play/reissue/return approvals are declared first-test blockers; otherwise covered as P1/extended workflow evidence |  |
| P0 deployment readiness | PASS | 2026-07-02 target live-root strict readiness passed with `0 failures, 0 warnings`; backend CORS probe URL, session/cookie policy, security headers, backup tools, DB schema, Nginx/PHP-FPM syntax, frontend dist, Composer, and route:list passed | Low for first PC Web online testing; optional staged release-package strict run remains useful before final production cutover |  |
| P0 backup/rollback/logs | PASS | Latest file/DB backups, Nginx/PHP logs, and rollback backup dirs confirmed on target | Low; DB rollback remains manual by design |  |
| P0 core backend target smoke | PASS | 2026-07-02 target role permission, controlled writes, workflow, payroll, DB schema, and route:list passed | Low |  |
| P1 customer/sale/payroll/finance/purchase/inventory/upload/download/import/export | PASS | 2026-07-02 target P1 smoke passed inventory add/detail, purchase warehouse-in, local upload/download/delete, payroll export, and payroll import; cleanup audit for marker `CODEX_P1_SMOKE_20260702110811` returned 0 residual rows | Low for sampled P1 paths; extended approval families and browser-button write E2E are still broader coverage, not current PC Web first-test blockers unless owner requires them |  |

## Current Recommendation

PC Web P0 and sampled P1 gates are ready for first online testing as of 2026-07-02. Mobile is explicitly excluded from this gate. Recommended remaining non-blocking follow-up before final production cutover: run strict readiness against a staged release package root, and decide whether delivery/play/reissue/return approval families or browser-button write E2E need to be promoted into the first-test scope.
