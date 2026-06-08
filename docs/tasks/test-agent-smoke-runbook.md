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
- local file upload plus `BizFileRelationService` add/list/edit/delete behavior
- file-relation category validation, missing-file rejection, tenant spoofing rejection, and logical delete without deleting `dev_file`

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

## Optional File Relation HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -FileRelationHttpSmoke
```

This optional authenticated smoke creates a short-lived local token from `LOCAL_SUPER_ADMIN_ACCOUNT` in the ignored `.env`, uploads a temporary local file, calls `/biz/bizfilerelation/add` with JSON, verifies the relation row, calls `/biz/bizfilerelation/projectCase/del`, and cleans up the temporary database and disk rows. It does not print tokens or local credentials.

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
- optional file-relation HTTP smoke through `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -FileRelationHttpSmoke`
- browser smoke through the copied Vue frontend for user export/download buttons
