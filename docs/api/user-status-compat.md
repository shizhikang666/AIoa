# User Status Compatibility

Date: 2026-06-06

Agent: user-agent / frontend-agent

## Scope

This document records Java-compatible user enable/disable behavior for the copied system and business user pages.

The Java source project remains read-only:

`F:\AI\projects\testJava\OA`

## Added Routes

| Method | Path | Purpose |
| --- | --- | --- |
| POST | `/sys/user/disableUser` | Set `sys_user.USER_STATUS` to `DISABLED`. |
| POST | `/sys/user/enableUser` | Set `sys_user.USER_STATUS` to `ENABLE`. |
| POST | `/biz/user/disableUser` | Set `sys_user.USER_STATUS` to `DISABLED` with business data-scope guard. |
| POST | `/biz/user/enableUser` | Set `sys_user.USER_STATUS` to `ENABLE` with business data-scope guard. |

All routes are protected by `AuthMiddleware`.

## Request Notes

- The copied frontend sends Java-style JSON/body payloads with `{ id }`.
- Only `ENABLE` and `DISABLED` are used for `USER_STATUS`.
- System routes require an admin-compatible payload or matching route/button permission codes.
- Business routes additionally require organization data-scope access or current-user fallback.

## Non-Goals

- No user add/edit/delete.
- No reset-password-by-admin.
- No import/export.
- No token/session invalidation on status change.
- No route-permission middleware implementation.
- No Java source, database schema, Composer, `.env`, or frontend source changes.
