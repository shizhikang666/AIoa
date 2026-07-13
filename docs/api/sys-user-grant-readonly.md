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
| POST | `/sys/user/delete` | Logically delete users and clear affected director references. |
| POST | `/sys/user/grantRole` | Clear and rewrite user role relations. |
| POST | `/sys/user/grantResource` | Clear and rewrite user menu/button resource relations. |
| POST | `/sys/user/grantPermission` | Clear and rewrite user API/data-scope permission relations. |
| POST | `/sys/user/resetPassword` | Reset a user's password to the configured system default password hash. |
| POST | `/biz/user/grantRole` | Clear and rewrite business user role relations with conservative data-scope checks. |
| POST | `/biz/user/resetPassword` | Reset a business user's password with conservative data-scope checks. |

All routes are protected by `AuthMiddleware`.

## Response Notes

- `ownRole` returns a plain role id list for `SYS_USER_HAS_ROLE`.
- `ownResource` returns `{ id, grantInfoList }` using `SYS_USER_HAS_RESOURCE`.
- `ownPermission` returns `{ id, grantInfoList }` using `SYS_USER_HAS_PERMISSION`.
- `grantInfoList` preserves Java-compatible `EXT_JSON` payloads when present.
- Empty or malformed `EXT_JSON` falls back to the relation `TARGET_ID`.
- User list/detail rows continue to remove `PASSWORD`.
- `delete` accepts array payloads such as `[{ id }]`, plus `id`, `ids`, `idList`, and `userIds`.
- Delete writes only `sys_user.DELETE_FLAG = DELETED` for target users, clears affected user/organization director references, and rejects built-in/admin-compatible accounts.
- `grantRole` accepts `{ id, roleIdList }`.
- `roleIdList` may be an empty array to clear direct user role grants.
- Role save writes only `sys_relation` rows where `CATEGORY = SYS_USER_HAS_ROLE`.
- Invalid or tenant-incompatible role ids fail before existing relations are changed.
- `/biz/user/grantRole` allows admin-compatible payloads or route/button permission payloads that pass organization data-scope or self fallback.
- `grantResource` accepts `{ id, grantInfoList: [{ menuId, buttonInfo }] }`.
- Resource save writes only `sys_relation` rows where `CATEGORY = SYS_USER_HAS_RESOURCE`.
- `EXT_JSON` preserves Java-compatible `{ menuId, buttonInfo }` payloads.
- Invalid menu or button resource ids fail before existing relations are changed.
- System-module resources are rejected when the target user does not have the super-admin-compatible role.
- Grant-resource failures return specific Chinese messages, including `当前账号没有用户资源授权权限` for an operator without grant permission and `只有超管角色用户可以授权系统模块资源` when the target user cannot receive system-module resources.
- `grantPermission` accepts `{ id, grantInfoList: [{ apiUrl, scopeCategory, scopeDefineOrgIdList }] }`.
- Permission save writes only `sys_relation` rows where `CATEGORY = SYS_USER_HAS_PERMISSION`.
- `EXT_JSON` preserves Java-compatible `{ apiUrl, scopeCategory, scopeDefineOrgIdList }` payloads.
- `scopeCategory` is constrained to Java/frontend data-scope values.
- Custom data-scope organization ids are validated against active `sys_org` rows.
- `resetPassword` accepts `{ id }`, reads the configured default password from `dev_config`, hashes it with existing SM3 compatibility, and updates only `sys_user.PASSWORD`.
- The reset-password response never returns the default password value or hash.

## Deferred

The following remain intentionally deferred:

- user add/edit
- import/export
- Java source changes
- database schema changes
