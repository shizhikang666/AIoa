# Runtime Verification Plan

Date: 2026-05-29

Agent: merge-agent

## Goal

Verify the merged ThinkPHP OA project against a real local database and Redis runtime without touching the read-only Java source project or production data.

Final project path:

`F:\AI\projects\testJava\OA-ThinkPHP`

Read-only SQL reference:

`F:\AI\projects\testJava\OA\oa2026.sql`

## Current Findings

- PHP has `pdo_mysql`, `mysqli`, and `redis` extensions enabled.
- `F:\AI\projects\testJava\OA\oa2026.sql` exists and is about 390 MB.
- No `.env` file exists in the ThinkPHP project root.
- `mysql` CLI is not available in the current PATH.
- `redis-cli` is not available in the current PATH.
- Windows service `MySQL80` exists but is currently stopped.
- `config/cache.php` now supports a Redis store, while defaulting to `file` until `CACHE_DRIVER=redis` is set.

## Safe Local Database Setup

Do not run these commands against production.

1. Start or prepare a local MySQL instance.
2. Create an isolated development database, for example `aioa_dev`.
3. Import the SQL dump into that local database.
4. Configure ThinkPHP database environment variables in a local `.env` file.
5. Run the smoke checks below.

Example commands to run manually after confirming MySQL credentials:

```powershell
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS aioa_dev DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
mysql -u root -p aioa_dev < F:\AI\projects\testJava\OA\oa2026.sql
```

## Local Environment Keys

Do not commit real passwords or secrets.

```dotenv
APP_DEBUG=true

DB_DRIVER=mysql
DB_TYPE=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=aioa_dev
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4

CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_DB=0
CACHE_PREFIX=
```

`CACHE_PREFIX` should stay empty unless the token key convention is adjusted, because `TokenService` already writes keys with the `oa:auth:` prefix.

## Smoke Checks

Run from:

`F:\AI\projects\testJava\OA-ThinkPHP`

```powershell
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

After database and Redis are configured, start the ThinkPHP dev server:

```powershell
php think run
```

Then verify:

- `GET /auth/b/getPicCaptcha`
- `POST /auth/b/doLogin`
- `GET /auth/b/getLoginUser`
- `GET /sys/userCenter/loginMenu`
- `GET /sys/org/tree`
- `GET /sys/position/page`
- `GET /sys/user/page`
- `GET /biz/task/count`
- `GET /biz/task/page`
- `GET /biz/process/page`

## Stop Conditions

- Do not import SQL into production.
- Do not modify `F:\AI\projects\testJava\OA`.
- Stop before changing `.env`, `.env.example`, or `config/database.php` unless explicitly confirmed.
- Stop before production or online realtime data sync.
- Stop if imported data contains missing schema, incompatible charset, or unexpected destructive SQL.

## Deferred Final Reminder

After the ThinkPHP OA system is complete and accepted, prepare a separate online realtime data sync plan. That plan must cover source database, target database, sync direction, downtime policy, rollback, backup, and verification.
