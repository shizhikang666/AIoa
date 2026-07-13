# RBAC Role Compatibility

Date: 2026-05-29

Agent: merge-agent

## Goal

Expose the `/sys/role/*` endpoints used by the existing Vue role management API. The first slice opened read-only endpoints; the 2026-06-11 slices add role grant save compatibility and role metadata add/edit/delete compatibility.

## Java Inputs

- `snowy-admin-web/src/api/sys/roleApi.js`
- `snowy-plugin-sys/.../role/controller/SysRoleController.java`
- `snowy-plugin-sys/.../role/service/impl/SysRoleServiceImpl.java`

## Added Read-Only Endpoints

- `GET /sys/role/page`
- `GET /sys/role/detail`
- `GET /sys/role/ownResource`
- `GET /sys/role/ownMobileMenu`
- `GET /sys/role/ownPermission`
- `GET /sys/role/ownUser`
- `GET /sys/role/orgTreeSelector`
- `GET /sys/role/resourceTreeSelector`
- `GET /sys/role/mobileMenuTreeSelector`
- `GET /sys/role/permissionTreeSelector`
- `GET /sys/role/roleSelector`
- `GET /sys/role/userSelector`

All routes are protected by `AuthMiddleware`.

## Data Sources

- Roles: `sys_role`
- Role relations: `sys_relation`
- Web resources: `sys_resource`
- Mobile resources: `mobile_resource`
- Organization selector: existing `OrgService`
- User selector: existing `UserDirectoryService`

## 2026-06-11 Grant Save Compatibility

Agent: main-agent

The copied Vue role management dialogs can now save role grants:

| Method | Path | Relation Category |
| --- | --- | --- |
| POST | `/sys/role/grantResource` | `SYS_ROLE_HAS_RESOURCE` |
| POST | `/sys/role/grantMobileMenu` | `SYS_ROLE_HAS_MOBILE_MENU` |
| POST | `/sys/role/grantPermission` | `SYS_ROLE_HAS_PERMISSION` |
| POST | `/sys/role/grantUser` | `SYS_USER_HAS_ROLE` |

Compatibility notes:

- Grant saves clear only the matching `sys_relation.CATEGORY` rows, then insert the submitted Java-compatible grant list.
- Resource and mobile-menu grants preserve `EXT_JSON` as `{ menuId, buttonInfo }`.
- Permission grants preserve `EXT_JSON` as `{ apiUrl, scopeCategory, scopeDefineOrgIdList }`.
- Resource and mobile ids are validated against active `sys_resource` or `mobile_resource` rows before existing grants are changed.
- Permission data-scope categories and custom organization ids are validated before existing grants are changed.
- Non-built-in roles cannot be granted system-module resources.
- Built-in roles cannot have all users removed through `grantUser`.
- This slice does not add route-permission middleware, cache invalidation hooks, or Java data-change events.

## 2026-06-11 Role Metadata Write Compatibility

Agent: main-agent with explorer-agent verification

The copied Vue role management page can now create, edit, and delete roles:

| Method | Path | Behavior |
| --- | --- | --- |
| POST | `/sys/role/add` | Creates `sys_role` rows from `name/category/sortCode/orgId/extJson` and generates a 10-character role `CODE` server-side |
| POST | `/sys/role/edit` | Updates ordinary roles while preserving `CODE`, create audit fields, and tenant ownership |
| POST | `/sys/role/delete` | Soft-deletes ordinary roles and removes role relation rows |

Compatibility notes:

- `category` accepts Java-compatible `GLOBAL` and `ORG`; `ORG` requires an active `orgId`, while `GLOBAL` clears `ORG_ID`.
- Role names are unique within the same `ORG_ID`; global roles are unique where `ORG_ID` is null or empty.
- `sortCode` is required and must be numeric.
- `superAdmin` and `tenantAdmin` roles cannot be edited or deleted.
- Delete accepts Java frontend payloads such as `[{ id }]`, plus `id`, `ids`, `idList`, and `roleIds` aliases.
- Delete clears `SYS_USER_HAS_ROLE` by `TARGET_ID` and clears `SYS_ROLE_HAS_RESOURCE`, `SYS_ROLE_HAS_MOBILE_MENU`, and `SYS_ROLE_HAS_PERMISSION` by `OBJECT_ID`.
- Java currently omits `SYS_ROLE_HAS_MOBILE_MENU` cleanup on role delete; ThinkPHP clears it because the PHP compatibility layer now supports mobile-menu grant writes.

Smoke evidence:

- `php -l app/service/auth/RoleService.php`
- `php -l app/controller/sys/RoleController.php`
- `php -l route/app.php`
- `php think route:list` includes `/sys/role/add`, `/sys/role/edit`, and `/sys/role/delete`
- DB smoke created a temporary global role, rejected duplicate global add, rejected missing `orgId` for `ORG`, edited the role, soft-deleted it, verified four role relation categories were removed, and verified built-in role deletion is rejected
