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

1. Start or prepare a local MySQL and Redis runtime.
2. Create an isolated development database named `phpoa20026`.
3. Import the SQL dump into that local database.
4. Configure ThinkPHP database environment variables in a local `.env` file.
5. Run the smoke checks below.

The user-designated local helper script is:

```powershell
Start-Process -FilePath "F:\project\socket\AI\testPhp\files\startServer1.bat" -WorkingDirectory "F:\project\socket\AI\testPhp\files" -WindowStyle Hidden
```

Do not assume `startServer1.bat` exists inside the MySQL subdirectory.

Example commands to run manually after confirming MySQL credentials:

```powershell
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS phpoa20026 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
mysql -u root -p phpoa20026 < F:\AI\projects\testJava\OA\oa2026.sql
```

## Local Environment Keys

Do not commit real passwords or secrets.

```dotenv
APP_DEBUG=true

DB_DRIVER=mysql
DB_TYPE=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=phpoa20026
DB_USER=root
DB_PASS=<local-only>
DB_CHARSET=utf8mb4

CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWD=<local-only>
REDIS_DB=0
REDIS_EXPIRE=11
CACHE_PREFIX=
```

`CACHE_PREFIX` should stay empty unless the token key convention is adjusted, because `TokenService` already writes keys with the `oa:auth:` prefix.

The actual database and Redis passwords must stay only in the ignored local `.env` file and must not be committed.

For legacy Vue login compatibility, `AUTH_SM2_PRIVATE_KEY` may be configured locally so the backend can decrypt SM2 password transport ciphertext. This value is private key material and must remain local/deployment-only.

## Runtime Configuration Change Rule

The user-designated runtime targets for this project are:

- MySQL host: `127.0.0.1`
- MySQL port: `3306`
- MySQL database: `phpoa20026`
- Redis host: `127.0.0.1`
- Redis port: `6379`
- Redis expire setting: `11`

If a later phase needs to change the database name, database account, Redis host, Redis port, Redis password variable, or Redis expiration setting, stop and ask the user to confirm before applying the change.

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

## 2026-05-29 Local Verification Result

- MySQL client used: `F:\project\socket\AI\testPhp\files\tools\mysql\bin\mysql.exe`.
- MySQL server version: `8.0.45`.
- Target database: `phpoa20026`.
- Imported SQL source: `F:\AI\projects\testJava\OA\oa2026.sql`.
- Imported table count: 121.
- Redis checked with `F:\project\socket\AI\testPhp\files\tools\redis\redis-cli.exe`.
- ThinkPHP DB probe returned `sys_user` count 121.
- ThinkPHP Redis probe returned `ok`.
- HTTP smoke server: `http://127.0.0.1:8000`.
- HTTP smoke checks returned `code=200` for captcha, organization tree, user page, task count/page, and process page.
- Login smoke check with the user-provided super admin test account returned:
  - `POST /auth/b/doLogin`: `code=200`
  - `GET /auth/b/getLoginUser`: `code=200`
  - `GET /sys/userCenter/loginMenu`: `code=200`
  - `GET /auth/b/doLogout`: `code=200`
  - Reusing the same token after logout: `code=401`

The login password was not written to this document and must remain local/user-provided only.

SM2 encrypted login was not exercised in this run because the runtime private key is not stored in the repository. Plaintext local smoke login continues to pass.

## 2026-05-29 Frontend Token Compatibility Result

Initial frontend-style token-only smoke checks found that several routes returned `missing userId` because they required the current user id but did not have `AuthMiddleware` attached.

The route groups `sys/userCenter`, `biz/task`, and `biz/process` now use `AuthMiddleware`, so token payload is available to the controllers.

After the fix:

- `GET /sys/userCenter/loginOrgTree`: `code=200` with token.
- `GET /sys/userCenter/loginPositionInfo`: `code=200` with token.
- `GET /biz/task/count`: `code=200` with token.
- `GET /biz/task/page`: `code=200` with token.
- `GET /biz/process/page`: `code=200` with token.
- The same protected routes return `code=401` without token.

## Stop Conditions

- Do not import SQL into production.
- Do not modify `F:\AI\projects\testJava\OA`.
- Stop before changing `.env`, `.env.example`, or `config/database.php` unless explicitly confirmed.
- Stop before production or online realtime data sync.
- Stop if imported data contains missing schema, incompatible charset, or unexpected destructive SQL.

## Deferred Final Reminder

After the ThinkPHP OA system is complete and accepted, prepare a separate online realtime data sync plan. That plan must cover source database, target database, sync direction, downtime policy, rollback, backup, and verification.
