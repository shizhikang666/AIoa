# Local Runtime Services

## Purpose

This project uses the user-provided local runtime bundle for database-backed and Redis-backed smoke tests.

Future Codex conversations should use this runtime first instead of trying unrelated Windows services such as `MySQL80`.

Future Codex conversations should also assume the project is being handled in real multi-Agent mode. The main conversation is the merge/coordinator session, while scoped workers such as `frontend-agent`, `api-agent`, `test-agent`, and `docs-agent` perform only their explicitly assigned slices. Workers should not expand into unrelated runtime, frontend, API, or merge work.

## Start Command

Run from the service bundle directory:

```powershell
Set-Location E:\project\socket\AI\testPhp\files
.\startServer1.bat
```

The batch file starts the local PHP FastCGI, MySQL, Redis, Nginx, and queue-related services from the bundled `tools` directory.

## Expected Ports

| Service | Host | Port | Notes |
| --- | --- | ---: | --- |
| MySQL | `127.0.0.1` | `3306` | Use database `phpoa20026` |
| Redis | `127.0.0.1` | `6379` | Uses password from ignored local `.env` |
| PHP FastCGI | `127.0.0.1` | `9000` | Started by `startServer1.bat` |

## Project `.env`

The ThinkPHP project reads local connection settings from:

`F:\AI\projects\testJava\OA-ThinkPHP\.env`

This file is intentionally ignored by Git. It may contain the user-provided MySQL and Redis passwords, so do not print it verbatim and do not commit it.

Expected non-secret values:

```dotenv
DB_DRIVER=mysql
DB_TYPE=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=phpoa20026
DB_USER=root
DB_CHARSET=utf8mb4

CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_DB=0
REDIS_TIMEOUT=0
REDIS_EXPIRE=11
```

Secret values such as `DB_PASS` and `REDIS_PASSWD` must remain only in the ignored local `.env` or be provided by the user during the session.

Local login smoke credentials must also be read from the ignored local `.env`:

```dotenv
LOCAL_SUPER_ADMIN_ACCOUNT=
LOCAL_SUPER_ADMIN_PASSWORD=
```

Do not write plaintext login accounts, passwords, tokens, database credentials, Redis credentials, or other secrets into tracked files, task notes, command output snippets, commits, or final reports.

## Verification Commands

Fast readiness check without printing secrets:

```powershell
Set-Location F:\AI\projects\testJava\OA-ThinkPHP
.\scripts\runtime-ready.ps1
```

Web readiness check for local backend/frontend smoke tests:

```powershell
Set-Location F:\AI\projects\testJava\OA-ThinkPHP
.\scripts\web-ready.ps1
```

`runtime-ready.ps1` checks the service bundle ports `3306`, `6379`, and `9000`. `web-ready.ps1` checks the application HTTP targets `http://127.0.0.1:82/think` and `http://127.0.0.1:83/` after the ThinkPHP and Vue dev servers have been started.

Combined local preflight:

```powershell
Set-Location F:\AI\projects\testJava\OA-ThinkPHP
.\scripts\project-preflight.ps1
```

Use skip switches when a layer is intentionally unavailable, for example:

```powershell
.\scripts\project-preflight.ps1 -SkipWeb -SkipRoleSelector
```

Check listening ports:

```powershell
netstat -ano | Select-String -Pattern ':3306|:6379|:9000'
```

Confirm the database exists and contains tables without printing passwords:

```powershell
$env:MYSQL_PWD = '<from ignored .env>'
& 'E:\project\socket\AI\testPhp\files\tools\mysql\bin\mysql.exe' --host=127.0.0.1 --port=3306 --user=root --default-character-set=utf8mb4 -e "SELECT COUNT(*) AS table_count FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='phpoa20026';"
Remove-Item Env:\MYSQL_PWD
```

Confirm Redis is reachable without printing passwords:

```powershell
& 'E:\project\socket\AI\testPhp\files\tools\redis\redis-cli.exe' -h 127.0.0.1 -p 6379 -a '<from ignored .env>' ping
```

Expected result: `PONG`.

## Current Verified State

Verified on 2026-06-06:

- MySQL listened on `127.0.0.1:3306`.
- Redis listened on `127.0.0.1:6379` and responded after authentication.
- PHP FastCGI listened on `127.0.0.1:9000`.
- `phpoa20026` existed and contained 121 application tables.
- DB-backed user export smoke passed through `UserDirectoryService`.
