# Sys User Grant Compatibility

Date: 2026-06-06

Agent: user-agent / frontend-agent

## Scope

This document records compatibility for the copied Vue system and business user page grant dialogs.

The Java source project remains read-only:

`F:\AI\projects\testJava\OA`

## Added Routes

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/sys/user/list/detail` | Read sanitized user rows by query filters. |
| GET | `/sys/user/ownRole` | Read role ids directly granted to a user. |
| GET | `/sys/user/ownResource` | Read menu and button resource grants directly assigned to a user. |
| GET | `/sys/user/ownPermission` | Read API permission and data-scope grants directly assigned to a user. |
| POST | `/sys/user/grantRole` | Clear and rewrite user role relations. |
| POST | `/biz/user/grantRole` | Clear and rewrite business user role relations with conservative data-scope checks. |

All routes are protected by `AuthMiddleware`.

## Response Notes

- `ownRole` returns a plain role id list for `SYS_USER_HAS_ROLE`.
- `ownResource` returns `{ id, grantInfoList }` using `SYS_USER_HAS_RESOURCE`.
- `ownPermission` returns `{ id, grantInfoList }` using `SYS_USER_HAS_PERMISSION`.
- `grantInfoList` preserves Java-compatible `EXT_JSON` payloads when present.
- Empty or malformed `EXT_JSON` falls back to the relation `TARGET_ID`.
- User list/detail rows continue to remove `PASSWORD`.
- `grantRole` accepts `{ id, roleIdList }`.
- `roleIdList` may be an empty array to clear direct user role grants.
- Role save writes only `sys_relation` rows where `CATEGORY = SYS_USER_HAS_ROLE`.
- Invalid or tenant-incompatible role ids fail before existing relations are changed.
- `/biz/user/grantRole` allows admin-compatible payloads or route/button permission payloads that pass organization data-scope or self fallback.

## Deferred

The following remain intentionally deferred:

- `/sys/user/grantResource`
- `/sys/user/grantPermission`
- user add/edit/delete
- enable/disable user
- reset password
- import/export
- Java source changes
- database schema changes
