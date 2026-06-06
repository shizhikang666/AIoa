# Test Agent Smoke Runbook

## Purpose

This runbook turns the repeated manual post-slice checks into a single test-agent command.

The script is intended for the integrated ThinkPHP project at:

`F:\AI\projects\testJava\OA-ThinkPHP`

It does not modify Java source, `.env`, database schema, Composer dependencies, or application behavior.

## Script

`scripts/test-agent-smoke.ps1`

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

## Optional Backend No-Token Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -NoTokenSmoke
```

The optional smoke checks current protected download routes and expects an unauthenticated business response with `code = 401`.

## Local Runtime Services

Use the user-provided local service bundle before DB-backed smoke tests:

```powershell
Set-Location F:\project\socket\AI\testPhp\files
.\startServer1.bat
```

Expected local services:

- MySQL listens on `127.0.0.1:3306`
- Redis listens on `127.0.0.1:6379`
- PHP FastCGI listens on `127.0.0.1:9000`

The project local `.env` is ignored by Git and should hold the user-provided MySQL and Redis credentials. Do not print or commit database or Redis passwords in test logs.

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
- browser smoke through the copied Vue frontend for user export/download buttons
