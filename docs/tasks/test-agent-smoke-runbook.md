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

## Current DB Blocker

The direct DB-backed user export smoke is not included in the script yet.

Current local blocker:

- `MySQL80` service exists but did not start cleanly through `Start-Service`.
- Starting `mysqld.exe` with the explicit local `my.ini` reached port `3306`.
- The ThinkPHP `.env` database user was rejected by MySQL with `SQLSTATE[HY000] [1045] Access denied`.

Required user/environment action before DB-backed export smoke:

- start the intended MySQL instance, or
- update `.env` with a working local database user/password for `phpoa20026`.

Do not print or commit database passwords in test logs.

## Deferred Checks

Add these only after the environment is available:

- direct service smoke for `UserDirectoryService::exportUsers(false, ...)`
- direct service smoke for `UserDirectoryService::exportUsers(true, ...)`
- direct service smoke for `UserDirectoryService::exportUserInfoFile(...)`
- browser smoke through the copied Vue frontend for user export/download buttons
