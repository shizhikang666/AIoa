# Sys User Grant Read-Only Compatibility

Date: 2026-06-03

Agent: user-agent / frontend-agent

## Scope

This slice adds read-only compatibility for the copied Vue system user page grant dialogs.

The Java source project remains read-only:

`F:\AI\projects\testJava\OA`

## Added Routes

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/sys/user/list/detail` | Read sanitized user rows by query filters. |
| GET | `/sys/user/ownRole` | Read role ids directly granted to a user. |
| GET | `/sys/user/ownResource` | Read menu and button resource grants directly assigned to a user. |
| GET | `/sys/user/ownPermission` | Read API permission and data-scope grants directly assigned to a user. |

All routes are protected by `AuthMiddleware`.

## Response Notes

- `ownRole` returns a plain role id list for `SYS_USER_HAS_ROLE`.
- `ownResource` returns `{ id, grantInfoList }` using `SYS_USER_HAS_RESOURCE`.
- `ownPermission` returns `{ id, grantInfoList }` using `SYS_USER_HAS_PERMISSION`.
- `grantInfoList` preserves Java-compatible `EXT_JSON` payloads when present.
- Empty or malformed `EXT_JSON` falls back to the relation `TARGET_ID`.
- User list/detail rows continue to remove `PASSWORD`.

## Deferred

The following remain intentionally deferred:

- `/sys/user/grantRole`
- `/sys/user/grantResource`
- `/sys/user/grantPermission`
- user add/edit/delete
- enable/disable user
- reset password
- import/export
- Java source changes
- database schema changes
