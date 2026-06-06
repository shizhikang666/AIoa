# User Reset Password Compatibility

Date: 2026-06-06

Agent: user-agent / frontend-agent

## Scope

This slice supports copied system and business user table reset-password actions:

- `snowy-admin-web/src/api/sys/userApi.js`
- `snowy-admin-web/src/api/biz/bizUserApi.js`
- `snowy-admin-web/src/views/sys/user/index.vue`
- `snowy-admin-web/src/views/biz/user/index.vue`

The Java source project remains read-only:

`F:\AI\projects\testJava\OA`

## Added Routes

| Method | Path | Purpose |
| --- | --- | --- |
| POST | `/sys/user/resetPassword` | Reset a system user's password to the configured system default password hash. |
| POST | `/biz/user/resetPassword` | Reset a business user's password with conservative organization data-scope checks. |

Both routes are protected by `AuthMiddleware`.

## Compatibility Notes

- Requests accept Java-style `{ id }` JSON/body payloads.
- Only `sys_user.PASSWORD` is updated.
- The default password is read from `dev_config.CONFIG_KEY = SNOWY_SYS_DEFAULT_PASSWORD`.
- The stored password uses existing Java-compatible SM3 hashing through `Sm3Hasher`.
- Responses return only the target `id`; the password value and hash are never returned.
- Business reset keeps a conservative organization data-scope or current-user fallback before saving.
- No frontend source change is required for this compatibility slice.

## Deferred

- User add/edit/delete
- Import/export
- Token/session invalidation after reset
- Fine-grained route-permission middleware
- Java source changes
- Database schema changes
- Frontend source changes
