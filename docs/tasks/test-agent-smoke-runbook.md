# Test Agent Smoke Runbook

## Purpose

This runbook turns the repeated manual post-slice checks into a single test-agent command.

The script is intended for the integrated ThinkPHP project at:

`F:\AI\projects\testJava\OA-ThinkPHP`

It does not modify Java source, `.env`, database schema, Composer dependencies, or application behavior.

Future new Codex conversations should treat the main conversation as the merge/coordinator session and use real scoped worker Agents by default. `test-agent` owns smoke checks, syntax checks, route checks, namespace checks, Composer checks, and test documentation inside the explicit task scope. It should not take over frontend, API, docs, or merge/coordinator work unless the user assigns that scope.

## Script

`scripts/test-agent-smoke.ps1`

DB/Redis/export smoke script:

`scripts/test-agent-db-smoke.ps1`

## Baseline Command

```powershell
.\scripts\test-agent-smoke.ps1
```

The baseline command runs:

- `composer dump-autoload`
- `php think`
- `php think route:list`
- required route coverage checks for current frontend-visible personnel, message SSE, and biz directory aliases
- PHP syntax lint for `app`, `config`, and `route`
- `git diff --check`

## DB Smoke Command

After the local runtime services are started and the ignored `.env` contains the local credentials, run:

```powershell
.\scripts\test-agent-db-smoke.ps1
```

The DB smoke command reads the ignored local `.env`, uses the bundled MySQL and Redis clients, and does not print passwords. It verifies:

- `phpoa20026` has application tables
- Redis responds to `PING`
- `UserDirectoryService::exportUsers(false, ...)` returns a valid CSV download descriptor
- `UserDirectoryService::exportUsers(true, ...)` returns a valid CSV download descriptor
- `UserDirectoryService::exportUserInfoFile(...)` returns a valid text download descriptor
- sampled export content does not include `PASSWORD`
- `DevFileService` local download, upload, tenant-scoped logical delete, and no physical delete behavior
- `DevEmailService` and `DevSmsService` tenant-scoped logical delete behavior
- `DevConfigService` `BIZ_DEFINE` add/edit/delete behavior, duplicate-key rejection, sensitive mask preservation, `SYS_BASE` delete rejection, and logical delete behavior
- `DevLogService` category physical delete behavior, tenant isolation, other-category preservation, and empty-category rejection
- `DevJobService` Java-style array delete behavior, malformed payload safety, logical delete behavior, and active-page hiding
- `GenConfigService` Java-style `editBatch` behavior, whitelist writes, optional-field nulling, deleted-row rejection, and failed-batch rollback safety
- `SaleProjectBillingService` invoicing complete behavior, tenant-scoped row lookup, idempotent state update, and cross-tenant rejection
- local file upload plus `BizFileRelationService` add/list/edit/delete behavior
- file-relation category validation, missing-file rejection, tenant spoofing rejection, and logical delete without deleting `dev_file`
- `ResourceService` module add/edit/delete behavior, duplicate-title rejection, built-in module delete rejection path coverage through service logic, child-resource logical delete, and role-resource relation cleanup
- `ResourceService` button add/edit/delete behavior, duplicate-code rejection, logical delete, and role-resource `buttonInfo` cleanup
- `MobileResourceService` module add/edit/delete behavior, generated 10-character code, duplicate-title rejection, module/menu logical delete, mixed missing-id delete tolerance, and role mobile-menu relation cleanup
- `MobileResourceService` button add/edit/delete behavior, duplicate-code rejection, logical delete, mixed missing-id delete tolerance, and role mobile-menu `buttonInfo` cleanup
- `TeamProjectService` base add/edit/delete behavior, automatic current-user `LEADER` member creation, member edit audit refresh without role/permission mutation, project permission relation sync, version increment, and project/member logical delete

## Optional Backend No-Token Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -NoTokenSmoke
```

The optional smoke checks current protected download routes and expects an unauthenticated business response with `code = 401`.

## Optional Dev File HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -DevFileHttpSmoke
```

This optional authenticated smoke creates a short-lived local token from `LOCAL_SUPER_ADMIN_ACCOUNT` in the ignored `.env`, uploads a temporary local file, calls `/dev/file/delete` with Java-style JSON array body, verifies `dev_file.DELETE_FLAG = DELETED`, verifies the physical uploaded file remains until cleanup, and then removes the temporary database and disk rows. It does not print tokens or local credentials.

## Optional Dev Email/SMS HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -DevEmailSmsHttpSmoke
```

This optional authenticated smoke creates a short-lived local token from `LOCAL_SUPER_ADMIN_ACCOUNT` in the ignored `.env`, inserts temporary `dev_email` and `dev_sms` rows, verifies malformed Java-style array delete payloads do not partially delete valid ids, calls `/dev/email/delete` and `/dev/sms/delete`, verifies both rows reach `DELETE_FLAG = DELETED`, and then removes the temporary rows. It does not print tokens or local credentials.

## Optional Dev Config HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -DevConfigHttpSmoke
```

This optional authenticated smoke creates a short-lived local token from `LOCAL_SUPER_ADMIN_ACCOUNT` in the ignored `.env`, calls `/dev/config/add`, `/dev/config/edit`, and `/dev/config/delete` for a temporary `BIZ_DEFINE` row, verifies add/edit/delete return `data = null`, verifies malformed mixed delete payloads do not partially delete valid ids, verifies temporary `SYS_BASE` delete is rejected, confirms `DELETE_FLAG = DELETED`, and then removes temporary rows. It does not print tokens or local credentials.

## Optional Dev Log HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -DevLogHttpSmoke
```

This optional authenticated smoke creates a short-lived local token from `LOCAL_SUPER_ADMIN_ACCOUNT` in the ignored `.env`, inserts temporary `dev_log` rows, calls `/dev/log/delete` with `{ "category": "..." }`, verifies the response returns `data = null`, confirms only the target category row is physically deleted, confirms another temporary category remains until cleanup, and then removes temporary rows. It does not print tokens or local credentials.

## Optional Dev Job HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -DevJobHttpSmoke
```

This optional authenticated smoke creates a short-lived local token from `LOCAL_SUPER_ADMIN_ACCOUNT` in the ignored `.env`, inserts temporary `dev_job` rows, verifies malformed Java-style array delete payloads do not partially delete valid ids, calls `/dev/job/delete`, verifies the response returns `data = null`, confirms only the target row reaches `DELETE_FLAG = DELETED`, and then removes temporary rows. It does not print tokens or local credentials.

## Optional Gen Config HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -GenConfigHttpSmoke
```

This optional authenticated smoke creates a short-lived local token from `LOCAL_SUPER_ADMIN_ACCOUNT` in the ignored `.env`, inserts temporary `gen_config` rows, verifies a malformed mixed `editBatch` payload does not partially update valid rows, calls `/gen/config/editBatch`, verifies the response returns `data = null`, confirms only Java edit-parameter fields are updated, and then removes temporary rows. It does not print tokens or local credentials.

## Optional Sale Project Invoicing HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -SaleProjectInvoicingHttpSmoke
```

This optional authenticated smoke creates a short-lived local token from `LOCAL_SUPER_ADMIN_ACCOUNT` in the ignored `.env`, inserts temporary `biz_sale_project` and `biz_sale_project_invoicing` rows, verifies a cross-tenant complete request fails without updating the row, calls `/biz/saleprojectinvoicing/complete`, verifies the response returns `data = null`, confirms `INVOICING_STATE = INVOICING_STATE_COMPLETE`, and then removes temporary rows. It does not print tokens or local credentials.

## Optional File Relation HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -FileRelationHttpSmoke
```

This optional authenticated smoke creates a short-lived local token from `LOCAL_SUPER_ADMIN_ACCOUNT` in the ignored `.env`, uploads a temporary local file, calls `/biz/bizfilerelation/add` with JSON, verifies the relation row, calls `/biz/bizfilerelation/projectCase/del`, and cleans up the temporary database and disk rows. It does not print tokens or local credentials.

## Optional Sys Button HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -SysButtonHttpSmoke
```

This optional authenticated smoke creates a short-lived local token from `LOCAL_SUPER_ADMIN_ACCOUNT` in the ignored `.env`, calls `/sys/button/add`, verifies the created button appears in `/sys/button/page`, verifies duplicate `code` rejection, calls `/sys/button/edit`, prepares a temporary `SYS_ROLE_HAS_RESOURCE` relation, calls `/sys/button/delete`, verifies `DELETE_FLAG = DELETED` and `EXT_JSON.buttonInfo` cleanup, and removes temporary rows. It does not print tokens or local credentials.

## Optional Sys Module HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -SysModuleHttpSmoke
```

This optional authenticated smoke creates a short-lived local token from `LOCAL_SUPER_ADMIN_ACCOUNT` in the ignored `.env`, calls `/sys/module/add`, verifies the created module appears in `/sys/module/page`, verifies duplicate `title` rejection, calls `/sys/module/edit`, prepares a temporary child menu and `SYS_ROLE_HAS_RESOURCE` relation, calls `/sys/module/delete`, verifies module and child menu reach `DELETE_FLAG = DELETED`, verifies the relation is removed, and removes temporary rows. It does not print tokens or local credentials.

## Optional Mobile Button HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -MobileButtonHttpSmoke
```

This optional authenticated smoke creates a short-lived local token from `LOCAL_SUPER_ADMIN_ACCOUNT` in the ignored `.env`, calls `/mobile/button/add`, verifies the created button appears in `/mobile/button/page`, verifies duplicate `code` rejection, calls `/mobile/button/edit`, prepares a temporary `SYS_ROLE_HAS_MOBILE_MENU` relation, calls `/mobile/button/delete` with a mixed existing and missing id payload, verifies `DELETE_FLAG = DELETED` and `EXT_JSON.buttonInfo` cleanup, and removes temporary rows. It does not print tokens or local credentials.

## Optional Mobile Module HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -MobileModuleHttpSmoke
```

This optional authenticated smoke creates a short-lived local token from `LOCAL_SUPER_ADMIN_ACCOUNT` in the ignored `.env`, calls `/mobile/module/add`, verifies the created module appears in `/mobile/module/page`, verifies duplicate `title` rejection, calls `/mobile/module/edit`, prepares temporary child mobile menu rows and a `SYS_ROLE_HAS_MOBILE_MENU` relation, calls `/mobile/module/delete` with a mixed existing and missing id payload, verifies module/menu rows reach `DELETE_FLAG = DELETED`, verifies the mobile-menu relation row is removed, and removes temporary rows. It does not print tokens or local credentials.

## Optional Team Project HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -TeamProjectHttpSmoke
```

This optional authenticated smoke creates a short-lived local token from `LOCAL_SUPER_ADMIN_ACCOUNT` in the ignored `.env`, calls `/biz/bizteamproject/add`, verifies the created project, current-user `LEADER` member, and `TEAM_PROJECT_USER_HAS_RESOURCE_PERMISSION` relation, calls `/biz/bizteamprojectuser/edit` and verifies audit refresh without role/permission mutation, calls `/biz/bizteamproject/edit`, verifies base field updates and version increment, calls `/biz/bizteamproject/delete`, verifies project/member logical delete, and cleans up temporary rows. It does not print tokens or local credentials.

## Local Runtime Services

Use the user-provided local service bundle before DB-backed smoke tests:

```powershell
Set-Location F:\project\socket\AI\testPhp\files
.\startServer1.bat
```

Detailed connection notes for future conversations are kept in:

`docs/tasks/local-runtime-services.md`

Expected local services:

- MySQL listens on `127.0.0.1:3306`
- Redis listens on `127.0.0.1:6379`
- PHP FastCGI listens on `127.0.0.1:9000`

The project local `.env` is ignored by Git and should hold the user-provided MySQL and Redis credentials. Do not print or commit database or Redis passwords in test logs.

Local login smoke credentials must also come from the ignored project `.env`:

- `LOCAL_SUPER_ADMIN_ACCOUNT`
- `LOCAL_SUPER_ADMIN_PASSWORD`

Never write plaintext local login credentials, tokens, database passwords, Redis passwords, or other secrets into tracked files, smoke output excerpts, commits, or final reports.

## DB-Backed Export Smoke Status

Resolved on 2026-06-06 after starting the user-provided local service bundle.

Verified:

- `phpoa20026` exists.
- The database has application tables.
- Redis responds after authentication.
- `UserDirectoryService::exportUsers(false, ...)` returns a CSV download descriptor.
- `UserDirectoryService::exportUsers(true, ...)` returns a CSV download descriptor.
- `UserDirectoryService::exportUserInfoFile(...)` returns a text profile download descriptor.
- Export smoke output did not include password headers or password text.

## Deferred Checks

Add these only when a backend and frontend browser session are already available:

- optional no-token HTTP smoke through `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -NoTokenSmoke`
- optional dev-file HTTP smoke through `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -DevFileHttpSmoke`
- optional dev-email/SMS HTTP smoke through `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -DevEmailSmsHttpSmoke`
- optional dev-config HTTP smoke through `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -DevConfigHttpSmoke`
- optional dev-log HTTP smoke through `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -DevLogHttpSmoke`
- optional dev-job HTTP smoke through `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -DevJobHttpSmoke`
- optional gen-config HTTP smoke through `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -GenConfigHttpSmoke`
- optional sale-project invoicing HTTP smoke through `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -SaleProjectInvoicingHttpSmoke`
- optional file-relation HTTP smoke through `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -FileRelationHttpSmoke`
- optional sys-module HTTP smoke through `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -SysModuleHttpSmoke`
- optional sys-button HTTP smoke through `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -SysButtonHttpSmoke`
- optional mobile-module HTTP smoke through `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -MobileModuleHttpSmoke`
- optional mobile-button HTTP smoke through `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -MobileButtonHttpSmoke`
- optional team-project base HTTP smoke through `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -TeamProjectHttpSmoke`
- browser smoke through the copied Vue frontend for affected visible pages, including dev config "other config" maintenance when `/dev/config/add|edit|delete` changes
