# Auth Third Read-Only Compatibility

Date: 2026-06-05

Agent: auth-agent / frontend-agent

## Scope

This document records the read-only ThinkPHP compatibility endpoint for third-party user binding pagination.

Java reference:

- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-auth\src\main\java\vip\xiaonuo\auth\modular\third\controller\AuthThirdController.java`
- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-auth\src\main\java\vip\xiaonuo\auth\modular\third\service\impl\AuthThirdServiceImpl.java`
- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-auth\src\main\java\vip\xiaonuo\auth\modular\third\entity\AuthThirdUser.java`

Frontend reference:

- `snowy-admin-web/src/api/auth/thirdApi.js`

## Endpoint

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/auth/third/page` | List third-party user bindings. |
| GET | `/auth/third/render` | Controlled deferred third-party auth render wrapper. |
| GET | `/auth/third/callback` | Controlled deferred third-party auth callback wrapper. |

`/auth/third/page` is protected by `AuthMiddleware`. The render and callback wrappers stay public like the Java login entry points, but return a controlled deferred response instead of starting OAuth provider behavior.

## Supported Filters

- `current`, `size`, `page`, `limit`, `pageNo`, `pageSize`
- `sortField`, `sortOrder`
- `category`
- `searchKey`

## Response Notes

- The response uses the standard page structure: `records`, `total`, `current`, `size`, and `pages`.
- Rows are read from `auth_third_user`.
- Logical deletes are hidden with the same `DELETE_FLAG IS NULL OR DELETE_FLAG = NOT_DELETE` compatibility convention used in the project.
- `searchKey` matches `NAME` or `NICKNAME`.
- Supported row fields include `id`, `thirdId`, `userId`, `avatar`, `name`, `nickname`, `gender`, `category`, `extJson`, and audit fields.

## Deferred

- `/auth/third/render` and `/auth/third/callback` are routed but intentionally return `code = 400` deferred responses.
- OAuth provider configuration, third-party login, user binding writes, user creation, token issuance, Java source changes, database schema changes, Composer files, `.env`, and frontend source are unchanged.

## 2026-06-15 Authenticated Read Smoke

`scripts/auth-index-read-http-smoke.ps1` verifies protected `/auth/third/page` pagination and deliberately skips OAuth render/callback behavior.
