# RBAC Role Read-Only Compatibility

Date: 2026-05-29

Agent: merge-agent

## Goal

Expose the `/sys/role/*` endpoints used by the existing Vue role management API. The first slice opened read-only endpoints; the 2026-06-11 slice adds role grant save compatibility while keeping role add/edit/delete deferred.

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

## Deferred

- `POST /sys/role/add`
- `POST /sys/role/edit`
- `POST /sys/role/delete`

Role metadata mutations still need dedicated validation, built-in role protection, audit logging, and test coverage before implementation.

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
- This slice does not add role metadata add/edit/delete, route-permission middleware, cache invalidation hooks, or Java data-change events.
