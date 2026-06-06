# Auth Session Monitor Compatibility

## Scope

This document tracks authenticated compatibility for session monitor endpoints using ThinkPHP bearer tokens.

## Java Reference

- Java project, read-only: `F:\AI\projects\testJava\OA`
- Controller: `AuthSessionController`
- Service: `AuthSessionServiceImpl`
- Frontend API: `snowy-admin-web/src/api/auth/monitorApi.js`

## Added Routes

Protected routes:

- `GET /auth/session/analysis`
- `GET /auth/session/b/page`
- `GET /auth/session/c/page`
- `POST /auth/session/b/exit`
- `POST /auth/session/c/exit`
- `POST /auth/token/b/exit`
- `POST /auth/token/c/exit`

## Response Shape

`/auth/session/analysis` returns:

- `currentSessionTotalCount`
- `maxTokenCount`
- `oneHourNewlyAdded`
- `proportionOfBAndC`

`/auth/session/b/page` returns a Java-style page:

- `records`
- `total`
- `page`
- `current`
- `limit`
- `size`
- `pages`

Each row includes:

- `id`
- `avatar`
- `account`
- `name`
- `lastLoginIp`
- `lastLoginAddress`
- `lastLoginTime`
- `lastLoginDevice`
- `latestLoginIp`
- `latestLoginAddress`
- `latestLoginTime`
- `latestLoginDevice`
- `sessionId`
- `sessionCreateTime`
- `sessionTimeout`
- `tokenCount`
- `tokenSignList`

## Compatibility Notes

- Java Sa-Token can enumerate all online sessions.
- ThinkPHP now keeps a cache-backed B-side token index for tokens created after the session-exit compatibility slice.
- Tokens created before the index was added can still be revoked by their own bearer token, but they cannot be globally enumerated by user id.
- `/auth/session/b/page` returns indexed B-side sessions for monitor managers and the current user's own session for ordinary users.
- `tokenSignList.tokenValue` is returned as the full token value for Java-compatible token exit. The route remains protected by bearer auth.
- Ordinary users may only operate on their own user id/token. Admin-compatible accounts or roles may manage all indexed B-side sessions.
- C-side client auth is not implemented yet, so `/auth/session/c/page` returns an empty page and C-side exit endpoints return success-compatible no-op data.

## Exit Payloads

Session exit accepts Java-style arrays:

```json
[
  { "userId": "1543837863788879873" }
]
```

Token exit accepts Java-style arrays:

```json
[
  { "tokenValue": "<bearer-token-value>" }
]
```

## Deliberate Exclusions

- No C-side login/client token storage is implemented.
- No third-party OAuth render/callback is implemented.
- No route permission middleware or UI-side permission filtering is added in this slice.
- No Java source files, database schema, `.env`, Composer files, frontend source, or public config files are changed.

## Later Work

Full online-session hardening still needs Redis deployment validation, expired-index cleanup under production cache settings, and a later permission-middleware pass for fine-grained auth monitor access.
